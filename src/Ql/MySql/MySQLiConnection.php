<?php
namespace Packaged\Dal\Ql\MySql;

use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\IConfigurable;
use Packaged\Dal\Ql\ILastInsertId;
use Packaged\Dal\Ql\IQLDataConnection;
use Packaged\Dal\Traits\ConfigurableTrait;

class MySQLiConnection
  implements IQLDataConnection, IConfigurable, ILastInsertId
{
  use ConfigurableTrait;

  /**
   * @var \mysqli
   */
  protected $_connection;

  /**
   * Open the connection
   *
   * @return static
   *
   * @throws ConnectionException
   */
  public function connect()
  {
    try
    {
      $this->_connection = new \mysqli(
        $this->_config()->getItem('hostname', '127.0.0.1'),
        $this->_config()->getItem('username', 'root'),
        $this->_config()->getItem('password', ''),
        $this->_config()->getItem('database', 'packaged_dal'),
        $this->_config()->getItem('port', 3306)
      );
    }
    catch(\Exception $e)
    {
      throw new ConnectionException(
        "Failed to connect to MySQL: " . $e->getMessage(),
        $e->getCode(), $e
      );
    }

    return $this;
  }

  /**
   * Check to see if the connection is already open
   *
   * @return bool
   */
  public function isConnected()
  {
    if($this->_connection !== null)
    {
      try
      {
        return $this->_connection->ping();
      }
      catch(\Exception $e)
      {
        return false;
      }
    }
    return false;
  }

  /**
   * Disconnect the open connection
   *
   * @return static
   *
   * @throws ConnectionException
   */
  public function disconnect()
  {
    if($this->isConnected())
    {
      try
      {
        $this->_connection->close();
      }
      catch(\Exception $e)
      {
        throw new ConnectionException(
          "Unable to disconnect from MySQL: " . $e->getMessage(),
          $e->getCode(), $e
        );
      }
      $this->_connection = null;
    }
    return $this;
  }

  /**
   * Execute a query
   *
   * @param       $query
   * @param array $values
   *
   * @return int number of affected rows
   */
  public function runQuery($query, array $values = null)
  {
    $stmt = $this->_connection->prepare($query);
    array_unshift($values, str_repeat('s', count($values)));
    call_user_func_array([$stmt, 'bind_param'], self::_refValues($values));

    $stmt->execute();
    $rows = $stmt->affected_rows;
    $stmt->close();
    return $rows;
  }

  /**
   * Fetch the results of the query
   *
   * @param       $query
   * @param array $values
   *
   * @return array
   */
  public function fetchQueryResults($query, array $values = null)
  {
    $stmt = $this->_connection->prepare($query);
    foreach($values as $value)
    {
      $stmt->bind_param('s', $value);
    }
    $stmt->execute();
    $res     = $stmt->get_result();
    $results = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();
    $stmt->close();
    return $results;
  }

  /**
   * Retrieve the last inserted ID
   *
   * @return mixed
   */
  public function getLastInsertId()
  {
    return $this->_connection->insert_id;
  }

  /**
   * Hack for MySQLi to receive values as references :(
   *
   * @param $arr
   *
   * @return array
   */
  protected function _refValues($arr)
  {
    $refs = [];
    foreach($arr as $key => $value)
    {
      $refs[$key] = & $arr[$key];
    }
    return $refs;
  }
}
