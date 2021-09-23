<?php
namespace Packaged\Dal\Ql;

use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\Connection\DuplicateKeyException;
use Packaged\Dal\Exceptions\Connection\PdoException;
use Packaged\Helpers\Strings;
use Packaged\Helpers\ValueAs;

class PdoConnection extends AbstractQlConnection
{
  /** @var \PDO */
  protected $_connection;
  protected $_emulatedPrepares = false;
  protected $_prepareDelayCount = [];
  protected $_delayedPreparesCount = null;

  public function isConnected()
  {
    return $this->_connection !== null;
  }

  protected function _disconnect()
  {
    $this->_connection = null;
  }

  public function _connect()
  {
    $dsn = $this->_config()->getItem('dsn', null);
    if($dsn === null)
    {
      $socket = $this->_config()->getItem('socket');
      if($socket)
      {
        $db = $this->_selectedDb ?: $this->_config()->getItem('database', '');
        $dsn = sprintf("mysql:unix_socket=%s%s", $socket, $db ? ";dbname=" . $db : "");
      }
      else
      {
        $dsn = sprintf(
          "mysql:host=%s;dbname=%s;port=%d",
          $this->_config()->getItem('hostname', '127.0.0.1'),
          $this->_selectedDb ?: $this->_config()->getItem('database', ''),
          $this->_config()->getItem('port', 3306)
        );
      }
    }

    $options = array_replace(
      $this->_defaultOptions(),
      ValueAs::arr($this->_config()->getItem('options'))
    );

    $connection = new \PDO(
      $dsn,
      $this->_config()->getItem('username', 'root'),
      $this->_config()->getItem('password', ''),
      $options
    );

    if(isset($options[\PDO::ATTR_EMULATE_PREPARES]))
    {
      $this->_emulatedPrepares = $options[\PDO::ATTR_EMULATE_PREPARES];
    }
    else
    {
      $serverVersion = $connection->getAttribute(
        \PDO::ATTR_SERVER_VERSION
      );
      $this->_emulatedPrepares = version_compare(
        $serverVersion,
        '5.1.17',
        '<'
      );
      $connection->setAttribute(
        \PDO::ATTR_EMULATE_PREPARES,
        $this->_emulatedPrepares
      );
    }
    $this->_connection = $connection;
  }

  protected function _defaultOptions()
  {
    return [
      \PDO::ATTR_PERSISTENT => false,
      \PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION,
      \PDO::ATTR_TIMEOUT    => 5,
    ];
  }

  protected function _switchDatabase($db)
  {
    if($this->isConnected())
    {
      $this->runQuery(
        'USE `' . substr($this->_connection->quote($db), 1, -1) . '`'
      );
    }
  }

  protected function _fetchQueryResults($query, array $values = null)
  {
    $stmt = $this->_executeQuery($query, $values);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    return $rows;
  }

  protected function _runQuery($query, array $values = null)
  {
    $stmt = $this->_executeQuery($query, $values);
    $rows = $stmt->rowCount();
    $stmt->closeCursor();
    return $rows;
  }

  protected function _getStatement($query)
  {
    $cacheKey = $this->_stmtCacheKey($query);
    $cached = $this->_getStmtCache($cacheKey);
    if($cached)
    {
      return $cached;
    }

    $stmt = false;

    // Delay preparing the statement for the configured number of calls
    if(!$this->_emulatedPrepares)
    {
      $delayCount = $this->_getDelayedPreparesCount();

      if($delayCount > 0)
      {
        if((!isset($this->_prepareDelayCount[$cacheKey]))
          || ($this->_prepareDelayCount[$cacheKey] < $delayCount)
        )
        {
          // perform an emulated prepare
          $this->_connection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
          try
          {
            $stmt = $this->_connection->prepare($query);
          }
          finally
          {
            $this->_connection->setAttribute(
              \PDO::ATTR_EMULATE_PREPARES,
              false
            );
          }
          if(isset($this->_prepareDelayCount[$cacheKey]))
          {
            $this->_prepareDelayCount[$cacheKey]++;
          }
          else
          {
            $this->_prepareDelayCount[$cacheKey] = 1;
          }
        }
      }
    }

    if(!$stmt)
    {
      // Do a real prepare and cache the statement
      $stmt = $this->_connection->prepare($query);
      if(!$stmt)
      {
        throw new ConnectionException('Unable to prepare statement');
      }
      $this->_addStmtCache($cacheKey, $stmt);
    }

    return $stmt;
  }

  protected function _getDelayedPreparesCount()
  {
    if($this->_delayedPreparesCount === null)
    {
      $value = $this->_config()->getItem('delayed_prepares', 1);
      if(is_numeric($value))
      {
        $this->_delayedPreparesCount = (int)$value;
      }
      else
      {
        if(in_array($value, ['true', true, '1', 1], true))
        {
          $this->_delayedPreparesCount = 1;
        }
        else if(in_array($value, ['false', false, '0', 0], true))
        {
          $this->_delayedPreparesCount = 0;
        }
      }
    }
    return $this->_delayedPreparesCount;
  }

  protected function _startTransaction()
  {
    return $this->_connection->beginTransaction();
  }

  protected function _commitTransaction()
  {
    return $this->_connection->commit();
  }

  protected function _rollbackTransaction()
  {
    return $this->_connection->rollBack();
  }

  protected function _sanitizeException(\Exception $e)
  {
    if(
      stripos($e->getMessage(), "MySQL server has gone")
      || stripos($e->getMessage(), "Error reading result")
    )
    {
      return new PdoException($e->getMessage(), 2006, $e);
    }

    $e = PdoException::from($e);

    if(preg_match("/^.*Duplicate entry .* for key /", $e->getMessage()))
    {
      return DuplicateKeyException::from($e);
    }

    return $e;
  }

  public function getLastInsertId($name = null)
  {
    return $this->_connection->lastInsertId($name);
  }

  protected function _isRecoverableException(\Exception $e)
  {
    if($e instanceof DuplicateKeyException)
    {
      return false;
    }

    $code = $e->getCode();
    $p = $e->getPrevious();
    if(($code == 0) || Strings::startsWith($code, 42)
      || ($p && Strings::startsWith($p->getCode(), 42)))
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

  private function _executeQuery($query, array $values = null)
  {
    $stmt = $this->_getStatement($query);
    if($values)
    {
      $i = 1;
      foreach($values as $value)
      {
        $type = $this->_pdoTypeForPhpVar($value);
        $stmt->bindValue($i, $value, $type);
        $i++;
      }
    }
    $stmt->execute();
    return $stmt;
  }

  private function _pdoTypeForPhpVar(&$var)
  {
    $type = \PDO::PARAM_STR;
    if($var === null)
    {
      $type = \PDO::PARAM_NULL;
    }
    else if(is_bool($var))
    {
      $var = $var ? 1 : 0;
      $type = \PDO::PARAM_INT;
    }
    else if(is_int($var))
    {
      if($var >= pow(2, 31))
      {
        $type = \PDO::PARAM_STR;
      }
      else
      {
        $type = \PDO::PARAM_INT;
      }
    }
    return $type;
  }
}
