<?php
namespace Packaged\Dal\Ql;

use Packaged\Config\ConfigurableInterface;
use Packaged\Config\ConfigurableTrait;
use Packaged\Dal\DataTypes\Counter;
use Packaged\Dal\DataTypes\UniqueList;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\DalException;
use Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DaoNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\Exceptions\DataStore\TooManyResultsException;
use Packaged\Dal\Foundation\AbstractDataStore;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\IDao;
use Packaged\QueryBuilder\Assembler\MySQL\MySQLAssembler;
use Packaged\QueryBuilder\Builder\QueryBuilder;
use Packaged\QueryBuilder\Expression\DecrementExpression;
use Packaged\QueryBuilder\Expression\IncrementExpression;
use Packaged\QueryBuilder\Expression\NumericExpression;
use Packaged\QueryBuilder\Expression\StringExpression;
use Packaged\QueryBuilder\SelectExpression\AllSelectExpression;
use Packaged\QueryBuilder\SelectExpression\ConcatSelectExpression;
use Packaged\QueryBuilder\SelectExpression\FieldSelectExpression;
use Packaged\QueryBuilder\SelectExpression\ReplaceSelectExpression;
use Packaged\QueryBuilder\SelectExpression\TrimSelectExpression;
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
   * @throws ConnectionNotFoundException
   * @throws ConnectionException
   */
  public function save(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);

    if(!$this->_getDaoChanges($dao))
    {
      return [];
    }

    $statement = $this->_getStatement($dao);
    if(!($statement instanceof IStatement))
    {
      return [];
    }

    $this->_prepareQuery($statement, $dao);
    $assembler = $this->_assemble($statement);

    $connection = $this->_connectedConnection();
    $connection->runQuery($assembler->getQuery(), $assembler->getParameters());

    if(!$this->_hasIds($dao) && $connection instanceof ILastInsertId)
    {
      $ids = $dao->getDaoIDProperties();
      foreach($ids as $idField)
      {
        if($dao->{$idField} === null)
        {
          $id = $connection->getLastInsertId($idField);
          if(!empty($id))
          {
            $dao->setDaoProperty($idField, $id);
          }
          break;
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
    foreach($dao->getId(true) as $value)
    {
      if($value === null)
      {
        return false;
      }
    }
    return true;
  }

  protected function _prepareQuery(IStatement $stmt, QlDao $dao)
  {
  }

  /**
   * @param QlDao $dao
   *
   * @return null|IStatement
   */
  protected function _getStatement(QlDao $dao)
  {
    $qb = static::_getQueryBuilderClass();
    if($dao->isDaoLoaded())
    {
      $data = $this->_getDaoChanges($dao);
      foreach($data as $field => $value)
      {
        $data[$field] = $this->_getQlExpression($dao, $field, $value);
      }
      $qb = static::_getQueryBuilderClass();
      $statement = $qb::update($dao->getTableName(), $data)
        ->where($dao->getLoadedDaoId());
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
          $statement->onDuplicateKeyUpdate(
            $field,
            $this->_getQlExpression($dao, $field, $value)
          );
        }
      }
    }
    return $statement;
  }

  protected function _getQlExpression(QlDao $dao, $fieldName, $value)
  {
    $newValue = $dao->{$fieldName};
    if($newValue instanceof Counter)
    {
      if($newValue->isFixedValue())
      {
        return NumericExpression::create($newValue->calculated());
      }
      else if($newValue->isIncrement())
      {
        return IncrementExpression::create($fieldName, $newValue->getIncrement());
      }
      else if($newValue->isDecrement())
      {
        return DecrementExpression::create($fieldName, $newValue->getDecrement());
      }
      throw new DalException('Unable to determine expression for Counter');
    }

    if($newValue instanceof UniqueList)
    {
      if($newValue->isFixedValue())
      {
        return StringExpression::create($dao->getPropertySerialized($fieldName, $newValue->calculated()));
      }
      else
      {
        if($newValue->hasAdditions())
        {
          $value = ConcatSelectExpression::create(
            FieldSelectExpression::create($fieldName),
            StringExpression::create(','),
            StringExpression::create($dao->getPropertySerialized($fieldName, $newValue->getAdditions()))
          );

          if($newValue->hasRemovals())
          {
            return $this->_uniqueListRemovalExpression($dao, $fieldName, $value, $newValue->getRemovals());
          }
          return $value;
        }
        else if($newValue->hasRemovals())
        {
          return $this->_uniqueListRemovalExpression(
            $dao,
            $fieldName,
            FieldSelectExpression::create($fieldName),
            $newValue->getRemovals()
          );
        }
      }
      throw new DalException('Unable to determine expression for Unique List');
    }

    return $value;
  }

  protected function _uniqueListRemovalExpression(QlDao $dao, $fieldName, $value, $removals)
  {
    foreach($removals as $removal)
    {
      $subject = ConcatSelectExpression::create($value, StringExpression::create(','));
      $search = ConcatSelectExpression::create(
        StringExpression::create($dao->getPropertySerialized($fieldName, $removal)),
        StringExpression::create(',')
      );
      $replace = StringExpression::create('');

      $value = TrimSelectExpression::create(
        ReplaceSelectExpression::create($subject, $search, $replace),
        ',',
        'trailing'
      );
    }
    return $value;
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
   * @throws DataStoreException
   * @throws ConnectionNotFoundException
   * @throws TooManyResultsException
   * @throws DaoNotFoundException
   * @throws ConnectionException
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
        throw new TooManyResultsException("Too many results located");
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
   * @throws ConnectionNotFoundException
   * @throws ConnectionException
   */
  protected function _doDelete(IDao $dao)
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
      $dao->markDaoAsLoaded(false);
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
   * @throws ConnectionNotFoundException
   * @throws DataStoreException
   * @throws ConnectionException
   */
  public function exists(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);
    if($dao->isDaoLoaded())
    {
      return true;
    }
    try
    {
      $this->load($dao);
      return $dao->isDaoLoaded();
    }
    catch(DaoNotFoundException $e)
    {
    }
    catch(TooManyResultsException $e)
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

  /**
   * @param IStatement $statement
   *
   * @return array
   * @throws ConnectionNotFoundException
   * @throws ConnectionException
   */
  public function getData(IStatement $statement)
  {
    $assembler = $this->_assemble($statement);
    $results = $this->_connectedConnection()->fetchQueryResults(
      $assembler->getQuery(),
      $assembler->getParameters()
    );
    return $results;
  }

  /**
   * @param IStatement $statement
   *
   * @return int
   * @throws ConnectionNotFoundException
   * @throws ConnectionException
   */
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
   * @throws ConnectionNotFoundException
   * @throws ConnectionException
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
      return Dao::getDalResolver()->getConnection($conn);
    }
    return $this->_connection;
  }

  /**
   * Force this datastore to use a specific connection,
   * instead of looking up from dal resolver
   *
   * @param IQLDataConnection $connection
   *
   * @return $this
   */
  public function setConnection(IQLDataConnection $connection)
  {
    $this->_connection = $connection;
    return $this;
  }

  /**
   * Returns the configuration for this datastore
   *
   * @return \Packaged\Config\ConfigSectionInterface
   */
  public function getConfig()
  {
    return $this->_config();
  }
}
