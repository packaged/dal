<?php
namespace Ql;

use Packaged\Dal\Ql\IQLDataConnection;
use Packaged\Dal\Ql\PdoConnection;
use Packaged\Dal\Ql\QlDao;
use Packaged\Dal\Ql\QlDataStore;

class MockQlDao extends QlDao
{
  protected $_dataStoreName = 'mockql';

  /**
   * @bigint
   */
  public $id;
  public $username;
  public $display;
  public $boolTest;

  public function setTableName($table)
  {
    $this->_tableName = $table;
    return $this;
  }
}

class MockNonUniqueKeyDao extends MockQlDao
{
  public function getDaoIDProperties()
  {
    return ['username'];
  }

  public function getTableName()
  {
    return 'mock_ql_daos';
  }
}

class MockMultiKeyQlDao extends MockQlDao
{
  public function getDaoIDProperties()
  {
    return ['id', 'username'];
  }
}

class MockQlDataStore extends QlDataStore
{
}

class MockAbstractQlDataConnection implements IQLDataConnection
{
  protected $_query;
  protected $_values;

  protected $_fetchResult = [['username' => 'x', 'display' => 'y', 'id' => 3]];
  protected $_runResult = 1;

  public function setFetchResult($result)
  {
    $this->_fetchResult = $result;
    return $this;
  }

  public function setRunResult($result)
  {
    $this->_runResult = $result;
    return $this;
  }

  public function getExecutedQuery()
  {
    return $this->_query;
  }

  public function getExecutedQueryValues()
  {
    return $this->_values;
  }

  public function runQuery($query, array $values = null)
  {
    $this->_query = $query;
    $this->_values = $values;
    return $this->_runResult;
  }

  public function fetchQueryResults($query, array $values = null)
  {
    $this->runQuery($query, $values);
    return $this->_fetchResult;
  }

  public function connect()
  {
    return $this;
  }

  public function isConnected()
  {
    return true;
  }

  public function disconnect()
  {
    return $this;
  }
}

class MockPdoConnection extends PdoConnection
{
  private $_queryRunCount = 0;

  public function setConnection($connection)
  {
    $this->_connection = $connection;
  }

  public function addConfig($key, $value)
  {
    $this->_config()->addItem($key, $value);
    return $this;
  }

  public function config()
  {
    $this->_config()->addItem('database', 'packaged_dal');
    return $this->_config();
  }

  protected function _runQuery($query, array $values = null, $retries = null)
  {
    $this->_queryRunCount++;
    return parent::_runQuery($query, $values, $retries);
  }

  public function getRunCount()
  {
    return $this->_queryRunCount;
  }
}

class PrepareErrorPdoConnection extends \PDO
{
  private $_errorMessage;
  private $_errorCode;

  public function __construct($message, $code = 0)
  {
    $this->_errorMessage = $message;
    $this->_errorCode = $code;
  }

  public function prepare($statement, $options = null)
  {
    $exception = new \PDOException($this->_errorMessage, $this->_errorCode);

    $exception->errorInfo = ['SQLSTATE_CODE', $this->_errorMessage];
    throw $exception;
  }

  public function setAttribute($attribute, $value)
  {
  }
}

class DelayedPreparesPdoConnection extends MockPdoConnection
{
  protected $_lastQueryCacheKey;

  protected function _getStatement($query)
  {
    $res = parent::_getStatement($query);
    $this->_lastQueryCacheKey = $this->_cacheKey($query);
    return $res;
  }

  public function getLastQueryDelayCount()
  {
    return isset($this->_prepareDelayCount[$this->_lastQueryCacheKey])
      ? $this->_prepareDelayCount[$this->_lastQueryCacheKey] : 0;
  }
}
