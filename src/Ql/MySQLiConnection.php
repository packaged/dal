<?php
namespace Packaged\Dal\Ql;

use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Helpers\ValueAs;

class MySQLiConnection extends AbstractQlConnection
{
  /** @var \mysqli */
  protected $_connection;

  public function isConnected()
  {
    try
    {
      return $this->_connection && $this->_connection->ping();
    }
    catch(\Exception $e)
    {
      $this->disconnect();
      return false;
    }
  }

  protected function _disconnect()
  {
    $this->_connection = null;
  }

  public function _makeConnection()
  {
    $options = array_replace(
      $this->_defaultOptions(),
      ValueAs::arr($this->_config()->getItem('options'))
    );

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new \mysqli();

    foreach($options as $key => $value)
    {
      $connection->options($key, $value);
    }

    $connection->real_connect(
      $this->_config()->getItem('hostname', '127.0.0.1'),
      $this->_config()->getItem('username', 'root'),
      $this->_config()->getItem('password', ''),
      $this->_selectedDb ?: $this->_config()->getItem('database', ''),
      $this->_config()->getItem('port', 3306)
    );

    $this->_connection = $connection;
  }

  public function _switchDatabase($db)
  {
    if($this->isConnected())
    {
      $this->_connection->select_db($db);
    }
  }

  protected function _freeStatement($stmt)
  {
    if($stmt instanceof \mysqli_stmt)
    {
      $stmt->free_result();
    }
    else
    {
      throw new ConnectionException('Incorrect type passed to free statement');
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
    $code = (string)$e->getCode();
    if(($code === '0') || ($code === '1064'))
    {
      return false;
    }
    return true;
  }

  protected function _affectedRows($stmt)
  {
    if($stmt instanceof \mysqli_stmt)
    {
      return $stmt->affected_rows;
    }
    else
    {
      throw new ConnectionException(
        'Incorrect statement type passed to row count.'
      );
    }
  }

  protected function _fetchAll($stmt)
  {
    if($stmt instanceof \mysqli_stmt)
    {
      $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      return $result;
    }
    else
    {
      throw new ConnectionException('Incorrect type passed to fetch.');
    }
  }

  protected function _bindValues($stmt, array $values)
  {
    if($stmt instanceof \mysqli_stmt)
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
    else
    {
      throw new ConnectionException('Incorrect statement type passed to bind.');
    }
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
