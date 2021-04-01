<?php
namespace Packaged\Dal\Ql;

use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\Connection\DuplicateKeyException;
use Packaged\Helpers\ValueAs;

class MySQLiConnection extends AbstractQlConnection
{
  /** @var \mysqli */
  protected $_connection;

  protected $_lastPing;
  protected $_pingCacheTime = 5;

  public function isConnected()
  {
    if(!$this->_connection)
    {
      return false;
    }
    try
    {
      $t = time();
      if($this->_lastPing === null || $this->_lastPing < $t - $this->_pingCacheTime)
      {
        if(!$this->_connection->ping())
        {
          $this->_lastPing = null;
          return false;
        }
        $this->_lastPing = $t;
      }

      return true;
    }
    catch(\Exception $e)
    {
      $this->disconnect();
      return false;
    }
  }

  protected function _disconnect()
  {
    if($this->_connection)
    {
      $this->_connection->close();
    }
    $this->_lastPing = $this->_connection = null;
  }

  public function _connect()
  {
    $options = array_replace($this->_defaultOptions(), ValueAs::arr($this->_config()->getItem('options')));
    $this->_pingCacheTime = $this->getConfig()->getItem('ping_cache', 5);

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $this->_connection = null;
    $connection = new \mysqli();
    $connection->init();

    foreach($options as $key => $value)
    {
      $connection->options($key, $value);
    }

    $socket = $this->_config()->getItem('socket');
    if($socket)
    {
      $connection->real_connect(
        null,
        $this->_config()->getItem('username', 'root'),
        $this->_config()->getItem('password', ''),
        $this->_selectedDb ?: $this->_config()->getItem('database', ''),
        null,
        $socket
      );
    }
    else
    {
      $connection->real_connect(
        $this->_config()->getItem('hostname', '127.0.0.1'),
        $this->_config()->getItem('username', 'root'),
        $this->_config()->getItem('password', ''),
        $this->_selectedDb ?: $this->_config()->getItem('database', ''),
        $this->_config()->getItem('port', 3306)
      );
    }

    $charSet = $this->_config()->getItem('charset');
    if($charSet)
    {
      $connection->set_charset($charSet);
    }

    $this->_lastPing = time();

    $this->_connection = $connection;
  }

  public function _switchDatabase($db)
  {
    if($this->isConnected())
    {
      $this->_connection->select_db($db);
    }
  }

  protected function _getStatement($query)
  {
    $cacheKey = $this->_stmtCacheKey($query);
    $cached = $this->_getStmtCache($cacheKey);
    if($cached)
    {
      return $cached;
    }
    // Do a real prepare and cache the statement
    try
    {
      $stmt = $this->_connection->prepare($query);
    }
    catch(\Exception $e)
    {
      throw ConnectionException::from($e);
    }
    if(!$stmt)
    {
      throw new ConnectionException('Unable to prepare statement');
    }

    $this->_addStmtCache($cacheKey, $stmt);
    return $stmt;
  }

  protected function _startTransaction()
  {
    return $this->_connection->begin_transaction();
  }

  protected function _commitTransaction()
  {
    return $this->_connection->commit();
  }

  protected function _rollbackTransaction()
  {
    return $this->_connection->rollback();
  }

  public function getLastInsertId($name = null)
  {
    return $this->_connection->insert_id;
  }

  protected function _isRecoverableException(\Exception $e)
  {
    if($e instanceof DuplicateKeyException)
    {
      return false;
    }
    $code = (string)$e->getCode();
    if(($code === '0') || ($code === '1064'))
    {
      return false;
    }
    return true;
  }

  protected function _shouldReconnectAfterException(\Exception $e)
  {
    // 2006  = MySQL server has gone away
    // 1047  = ER_UNKNOWN_COM_ERROR - happens when a PXC node is resyncing:
    //          "WSREP has not yet prepared node for application use"
    // HY000 = General SQL error
    $codes = ['2006', '1047', 'HY000'];
    $p = $e->getPrevious();
    return
      in_array((string)$e->getCode(), $codes, true)
      || ($p && in_array((string)$p->getCode(), $codes, true));
  }

  protected function _runQuery($query, array $values = null)
  {
    $stmt = $this->_executeQuery($query, $values);
    $rows = $stmt->affected_rows;
    $stmt->free_result();
    return $rows;
  }

  protected function _fetchQueryResults($query, array $values = null)
  {
    $stmt = $this->_executeQuery($query, $values);
    $result = $stmt->get_result();
    if($result === false) //get_result() returns false on error
    {
      throw new ConnectionException(
        $this->_connection->error, $this->_connection->errno
      );
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->free_result();
    return $rows;
  }

  private function _executeQuery($query, array $values = null)
  {
    $stmt = $this->_getStatement($query);
    if($values)
    {
      $arr = [];
      $types = '';
      foreach($values as $k => $value)
      {
        $types .= $this->_typeForPhpVar($value);
        $arr[] = &$values[$k];
      }
      $stmt->bind_param($types, ...$arr);
    }
    $stmt->execute();
    return $stmt;
  }

  private function _typeForPhpVar(&$var)
  {
    $type = 's';
    if(is_bool($var))
    {
      $var = $var ? 1 : 0;
      $type = 'i';
    }
    else if(is_int($var) && ($var < pow(2, 31)))
    {
      $type = 'i';
    }
    return $type;
  }
}
