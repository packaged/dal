<?php
namespace Packaged\Dal\Ql;

use Packaged\Dal\Exceptions\DalException;
use Packaged\Helpers\ValueAs;

class PgSQLConnection extends AbstractQlConnection
{
  /** @var resource */
  protected $_connection;

  public function isConnected()
  {
    try
    {
      return $this->_connection && pg_ping($this->_connection);
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
      pg_close($this->_connection);
    }
    $this->_connection = null;
  }

  public function _connect()
  {
    $cnf = $this->_config();

    $connectionString = "host=" . $cnf->getItem('hostname', '127.0.0.1')
      . " port=" . $cnf->getItem('port', 26257);

    $db = $this->_selectedDb ?: $cnf->getItem('database', '');
    if($db)
    {
      $connectionString .= " dbname=" . $db;
    }
    $user = $cnf->getItem('username');
    if($user)
    {
      $connectionString .= " user=" . $user;
      $password = $cnf->getItem('password');
      if($password)
      {
        $connectionString .= " password=" . $password;
      }
    }

    $this->_connection = pg_connect($connectionString);
    pg_set_client_encoding($this->_connection, "UNICODE");
  }

  public function _switchDatabase($db)
  {
    if($this->isConnected())
    {
      // TODO: Better checking on DB name
      if(!preg_match('/^[0-9a-zA-Z_]+$/', $db))
      {
        throw new DalException('Invalid database name');
      }
      pg_query($this->_connection,"USE ". $db);
    }
  }

  protected function _startTransaction()
  {
    // TODO: Transaction support
    //return $this->_connection->begin_transaction();
  }

  protected function _commitTransaction()
  {
    // TODO: Transaction support
    //return $this->_connection->commit();
  }

  protected function _rollbackTransaction()
  {
    // TODO: Transaction support
    //return $this->_connection->rollback();
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
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
