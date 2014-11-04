<?php
namespace Packaged\Dal\Ql;

use Packaged\Config\ConfigurableInterface;
use Packaged\Config\ConfigurableTrait;
use Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DaoNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\IDao;
use Packaged\Dal\IDataStore;
use Packaged\QueryBuilder\Assembler\MySQL\MySQLAssembler;
use Packaged\QueryBuilder\Statement\IStatement;
use Packaged\QueryBuilder\Statement\QueryStatement;

class QlDataStore implements IDataStore, ConfigurableInterface
{
  use ConfigurableTrait;

  protected $_connection;
  protected $_query;
  protected $_queryValues;

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
    $this->_clearQuery();
    $dao = $this->_verifyDao($dao);

    $ids    = array_filter(
      $dao->getId(true),
      function ($value)
      {
        return $value !== null;
      }
    );
    $hasIds = !empty($ids);

    if(!$hasIds)
    {
      $this->_saveInsert($dao);
    }
    else if($dao->isDaoLoaded())
    {
      $this->_saveUpdate($dao);
    }
    else
    {
      $this->_saveInsertDuplicate($dao);
    }

    $connection = $this->_connectedConnection();
    $connection->runQuery($this->_query, $this->_queryValues);

    if(!$hasIds && $connection instanceof ILastInsertId)
    {
      $id = $connection->getLastInsertId();
      foreach($dao->getDaoIDProperties(true) as $property)
      {
        $dao->setDaoProperty($property, $id);
      }
    }
  }

  protected function _saveInsertDuplicate(QlDao $dao)
  {
    //ATTEMPT
    $this->_saveInsert($dao);
    $this->_query .= " ON DUPLICATE KEY UPDATE ";
    $this->_subUpdate($dao, false);
  }

  protected function _saveUpdate(QlDao $dao)
  {
    //UPDATE
    $this->_query = "UPDATE ";
    $this->_query .= $this->escapeTableName($dao->getTableName());
    $this->_query .= " SET ";
    $this->_subUpdate($dao);
    $this->_query .= " WHERE ";
    $this->_appendIdWhere($dao);
  }

  protected function _subUpdate(QlDao $dao, $includeIds = true)
  {
    $updates = [];
    foreach($dao->getDaoChanges() as $column => $value)
    {
      if(!$includeIds && in_array($column, $dao->getDaoIDProperties()))
      {
        continue;
      }
      $updates[]            = $this->escapeColumn($column) . ' = ?';
      $this->_queryValues[] = $value['to'];
    }

    $this->_query .= implode(', ', $updates);
  }

  protected function _saveInsert(QlDao $dao)
  {
    $this->_query = "INSERT INTO ";
    $this->_query .= $this->escapeTableName($dao->getTableName());
    $colCount = 0;
    $columns  = [];
    foreach($dao->getDaoPropertyData() as $column => $data)
    {
      $colCount++;
      $columns[]            = $this->escapeColumn($column);
      $this->_queryValues[] = $data;
    }

    $this->_query .= ' (' . implode(', ', $columns) . ') ';
    $this->_query .= 'VALUES(';
    $this->_query .= implode(', ', array_fill(0, $colCount, '?'));
    $this->_query .= ')';
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
    $this->_clearQuery();
    $dao          = $this->_verifyDao($dao);
    $this->_query = "SELECT * FROM ";
    $this->_query .= $this->escapeTableName($dao->getTableName());
    $this->_query .= " WHERE ";
    $this->_appendIdWhere($dao);

    //Limit the result set to 2, for validation against multiple results
    $this->_query .= " LIMIT 2";

    $results = $this->_connectedConnection()->fetchQueryResults(
      $this->_query,
      $this->_queryValues
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
    $this->_clearQuery();
    $dao          = $this->_verifyDao($dao);
    $this->_query = "DELETE FROM ";
    $this->_query .= $this->escapeTableName($dao->getTableName());
    $this->_query .= " WHERE ";
    $this->_appendIdWhere($dao);
    $del = $this->_connectedConnection()
      ->runQuery($this->_query, $this->_queryValues);

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
      "You must pass a QlDao to SqlDataStore", 500
    );
  }

  protected function _clearQuery()
  {
    $this->_queryValues = [];
    $this->_query       = '';
  }

  protected function _appendIdWhere(IDao $dao)
  {
    $queryParts = [];
    foreach($dao->getId(true) as $column => $value)
    {
      $queryParts[]         = $this->escapeColumn($column) . ' = ?';
      $this->_queryValues[] = $value;
    }
    $this->_query .= implode(' AND ', $queryParts);
  }

  public function getData(IStatement $statement)
  {
    $results = $this->_connectedConnection()->fetchQueryResults(
      MySQLAssembler::stringify($statement),
      []
    );
    return $results;
  }

  public function escapeTableName($table)
  {
    return "`$table`";
  }

  public function escapeColumn($column)
  {
    return "`$column`";
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
