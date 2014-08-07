<?php
namespace Ql;

use Packaged\Dal\IDataConnection;
use Packaged\Dal\Ql\IQLDataConnection;
use Packaged\Dal\Ql\QlDao;
use Packaged\Dal\Ql\QlDataStore;

class MockQlDao extends QlDao
{
  public $id;
  public $username;
  public $display;
}

class MockMultiKeyQlDao extends QlDao
{
  public $id;
  public $username;
  public $display;

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
