<?php
namespace Packaged\Dal\Ql;

use Packaged\Config\ConfigurableInterface;
use Packaged\Config\ConfigurableTrait;
use Packaged\Dal\DataTypes\Counter;
use Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DaoNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\Foundation\AbstractDataStore;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\IDao;
use Packaged\QueryBuilder\Assembler\MySQL\MySQLAssembler;
use Packaged\QueryBuilder\Builder\QueryBuilder;
use Packaged\QueryBuilder\Expression\DecrementExpression;
use Packaged\QueryBuilder\Expression\IncrementExpression;
use Packaged\QueryBuilder\Expression\NumericExpression;
use Packaged\QueryBuilder\SelectExpression\AllSelectExpression;
use Packaged\QueryBuilder\Statement\IStatement;

class QlDataStore extends AbstractDataStore implements ConfigurableInterface
{
  use ConfigurableTrait;

  protected $_connection;

  /**
   * @return QueryBuilder
   */
  protected function _getQueryBuilderClass()
  {
    return QueryBuilder::class;
  }

  /**
   * Save a DAO to the data store
   *
   * @param IDao $dao
   *
   * @return array of changed properties
   *
   * @throws DataStoreException
   */
  public function save(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);

    if(!$this->_getDaoChanges($dao))
    {
      return [];
    }

    $statement = $this->_getStatement($dao);
    $this->_prepareQuery($statement, $dao);
    $assembler = $this->_assemble($statement);

    $connection = $this->_connectedConnection();
    $connection->runQuery($assembler->getQuery(), $assembler->getParameters());

    if(!$this->_hasIds($dao) && $connection instanceof ILastInsertId)
    {
      $ids = $dao->getDaoIDProperties();
      foreach($ids as $idField)
      {
        $id = $connection->getLastInsertId($idField);
        if(!empty($id))
        {
          $dao->setDaoProperty($idField, $id);
        }
      }
    }
    $changes = $dao->getDaoChanges();
    $dao->resetCounters();
    parent::save($dao);
    return $changes;
  }

  protected function _hasIds(QlDao $dao)
  {
    $ids = array_filter(
      $dao->getId(true),
      function ($value)
      {
        return $value !== null;
      }
    );
    return !empty($ids);
  }

  protected function _prepareQuery(IStatement $stmt, QlDao $dao)
  {
  }

  /**
   * @param QlDao $dao
   *
   * @return IStatement
   */
  protected function _getStatement(QlDao $dao)
  {
    $qb = static::_getQueryBuilderClass();
    if($dao->isDaoLoaded())
    {
      $data = $this->_getDaoChanges($dao);
      $qb = static::_getQueryBuilderClass();
      $statement = $qb::update($dao->getTableName(), $data)
        ->where($dao->getId(true));
    }
    else
    {
      $data = $dao->getDaoPropertyData();
      $statement = $qb::insertInto(
        $dao->getTableName(),
        ...array_keys($data)
      )->values(...array_values($data));

      if($this->_hasIds($dao))
      {
        foreach($this->_getDaoChanges($dao, false) as $field => $value)
        {
          if($dao->$field instanceof Counter)
          {
            if($dao->$field->isIncrement())
            {
              $value = IncrementExpression::create(
                $field,
                $dao->$field->getIncrement()
              );
            }
            elseif($dao->$field->isDecrement())
            {
              $value = DecrementExpression::create(
                $field,
                $dao->$field->getDecrement()
              );
            }
            elseif($dao->$field->isFixedValue())
            {
              $value = NumericExpression::create($dao->$field->calculated());
            }
          }
          $statement->onDuplicate($field, $value);
        }
      }
    }
    return $statement;
  }

  protected function _getDaoChanges(QlDao $dao, $includeIds = true)
  {
    $changes = $dao->getDaoChanges();
    foreach($changes as $column => $value)
    {
      if(!$includeIds && in_array($column, $dao->getDaoIDProperties()))
      {
        unset($changes[$column]);
      }
      else
      {
        $changes[$column] = $value['to'];
      }
    }
    return $changes;
  }

  /**
   * Hydrate a DAO from the data store
   *
   * @param IDao $dao
   *
   * @return IDao Loaded DAO
   *
   * @throws DaoNotFoundException
   * @throws DataStoreException
   */
  public function load(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);
    $qb = static::_getQueryBuilderClass();

    //Limit the result set to 2, for validation against multiple results
    $assembler = $this->_assemble(
      $qb::select(AllSelectExpression::create())
        ->from($dao->getTableName())
        ->where($dao->getId(true))
        ->limit(2)
    );

    $results = $this->_connectedConnection()->fetchQueryResults(
      $assembler->getQuery(),
      $assembler->getParameters()
    );

    switch(count($results))
    {
      case 1:
        $dao->hydrateDao(reset($results), true);
        $dao->markDaoAsLoaded();
        $dao->markDaoDatasetAsSaved();
        break;
      case 0:
        throw new DaoNotFoundException("Unable to locate Dao");
      default:
        throw new DataStoreException("Too many results located");
    }
    return $dao;
  }

  /**
   * Delete the DAO from the data store
   *
   * @param IDao $dao
   *
   * @return IDao
   *
   * @throws DataStoreException
   */
  public function delete(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);
    $qb = static::_getQueryBuilderClass();

    $assembler = $this->_assemble(
      $qb::deleteFrom($dao->getTableName())
        ->where($dao->getId(true))
    );

    $del = $this->_connectedConnection()->runQuery(
      $assembler->getQuery(),
      $assembler->getParameters()
    );

    if($del === 1)
    {
      return $dao;
    }
    else if($del === 0)
    {
      throw new DataStoreException("The delete query executed affected 0 rows");
    }
    else
    {
      throw new DataStoreException("Looks like we deleted multiple rows :(");
    }
  }

  /**
   * Does the object exist in the data store
   *
   * @param IDao $dao
   *
   * @return bool
   */
  public function exists(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);
    try
    {
      $this->load($dao);
      return $dao->isDaoLoaded();
    }
    catch(\Exception $e)
    {
    }
    return false;
  }

  /**
   * @param IDao $dao
   *
   * @return QlDao
   *
   * @throws \Packaged\Dal\Exceptions\DataStore\DataStoreException
   */
  protected function _verifyDao(IDao $dao)
  {
    if($dao instanceof QlDao)
    {
      return $dao;
    }

    throw new DataStoreException(
      "You must pass a QlDao to QlDataStore", 500
    );
  }

  public function getData(IStatement $statement)
  {
    $assembler = $this->_assemble($statement);
    $results = $this->_connectedConnection()->fetchQueryResults(
      $assembler->getQuery(),
      $assembler->getParameters()
    );
    return $results;
  }

  public function execute(IStatement $statement)
  {
    $assembler = $this->_assemble($statement);
    $results = $this->_connectedConnection()->runQuery(
      $assembler->getQuery(),
      $assembler->getParameters()
    );
    return $results;
  }

  protected function _assemble(IStatement $statement, $forPrepare = true)
  {
    return new MySQLAssembler($statement, $forPrepare);
  }

  /**
   * Get the connection, and connect if not connected
   *
   * @return IQlDataConnection
   * @throws \Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException
   */
  protected function _connectedConnection()
  {
    if(!$this->getConnection()->isConnected())
    {
      $this->getConnection()->connect();
    }
    return $this->getConnection();
  }

  /**
   * Retrieve the connection for this data store
   *
   * @return IQlDataConnection
   * @throws ConnectionNotFoundException
   */
  public function getConnection()
  {
    if($this->_connection === null)
    {
      $conn = $this->_config()->getItem('connection');
      if($conn === null)
      {
        throw new ConnectionNotFoundException(
          "No connection has been configured on this datastore"
        );
      }
      $this->_connection = Dao::getDalResolver()->getConnection($conn);
    }
    return $this->_connection;
  }
}
