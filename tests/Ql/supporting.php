<?php
namespace Ql;

use Packaged\Dal\IDataConnection;
use Packaged\Dal\Ql\IQLDataConnection;
use Packaged\Dal\Ql\PdoConnection;
use Packaged\Dal\Ql\QlDao;
use Packaged\Dal\Ql\QlDataStore;

class MockQlDao extends QlDao
{
  protected $_dataStoreName = 'mockql';

  public $id;
  public $username;
  public $display;
  public $boolTest;
}

class MockMultiKeyQlDao extends QlDao
{
  public $id;
  public $username;
  public $display;
  public $boolTest;

  public function getDaoIDProperties()
  {
    return ['id', 'username'];
  }
}

class MockQlDataStore extends QlDataStore
{
  public function setConnection(IDataConnection $connection)
  {
    $this->_connection = $connection;
    return $this;
  }
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
    $this->_values = array_map(
      function ($value)
      {
        if($value === null)
        {
          return 'NULL';
        }
        else
        {
          return '"' . $value . '"';
        }
      },
      $this->_values
    );
    return vsprintf(str_replace('?', '%s', $this->_query), $this->_values);
  }

  public function runQuery($query, array $values = null)
  {
    $this->_query  = $query;
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
  public function setConnection($connection)
  {
    $this->_connection = $connection;
  }
}

class PrepareErrorPdoConnection extends \PDO
{
  private $_errorMessage;
  private $_errorCode;

  public function __construct($message, $code = 0)
  {
    $this->_errorMessage = $message;
    $this->_errorCode    = $code;
  }

  function prepare($statement, $options = null)
  {
    $exception = new \PDOException($this->_errorMessage, $this->_errorCode);

    $exception->errorInfo = ['SQLSTATE_CODE', $this->_errorMessage];
    throw $exception;
  }
}