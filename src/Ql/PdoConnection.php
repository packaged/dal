<?php
namespace Packaged\Dal\Ql;

use Packaged\Config\ConfigurableInterface;
use Packaged\Config\ConfigurableTrait;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\Connection\PdoException;
use Packaged\Dal\IResolverAware;
use Packaged\Dal\Traits\ResolverAwareTrait;
use Packaged\Helpers\Strings;
use Packaged\Helpers\ValueAs;

class PdoConnection
  implements IQLDataConnection, ConfigurableInterface, ILastInsertId,
             IResolverAware
{
  use ConfigurableTrait;
  use ResolverAwareTrait;

  /**
   * @var \PDO
   */
  protected $_connection;
  protected $_prepareDelayCount = [];
  protected $_prepareCache = [];
  protected $_lastConnectTime = 0;
  protected $_emulatedPrepares = false;
  protected $_delayedPreparesCount = null;
  protected $_inTransaction = false;
  protected $_lastRetryCount = 0;
  protected $_maxPreparedStatements = null;

  /**
   * Open the connection
   *
   * @return static
   *
   * @throws ConnectionException
   */
  public function connect()
  {
    if(!$this->isConnected())
    {
      $this->_clearStmtCache();

      $dsn = $this->_config()->getItem('dsn', null);
      if($dsn === null)
      {
        $dsn = sprintf(
          "mysql:host=%s;dbname=%s;port=%d",
          $this->_config()->getItem('hostname', '127.0.0.1'),
          $this->_config()->getItem('database'),
          $this->_config()->getItem('port', 3306)
        );
      }

      $remainingAttempts = ((int)$this->_config()->getItem(
        'connect_retries',
        3
      ));

      while(($remainingAttempts > 0) && ($this->_connection === null))
      {
        try
        {
          $options = array_replace(
            $this->_defaultOptions(),
            ValueAs::arr($this->_config()->getItem('options'))
          );

          $this->_connection = new \PDO(
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
            $serverVersion = $this->_connection->getAttribute(
              \PDO::ATTR_SERVER_VERSION
            );
            $this->_emulatedPrepares = version_compare(
              $serverVersion,
              '5.1.17',
              '<'
            );
            $this->_connection->setAttribute(
              \PDO::ATTR_EMULATE_PREPARES,
              $this->_emulatedPrepares
            );
          }

          $remainingAttempts = 0;
        }
        catch(\Exception $e)
        {
          $remainingAttempts--;
          $this->_connection = null;
          if($remainingAttempts > 0)
          {
            usleep(mt_rand(1000, 5000));
          }
          else
          {
            throw new ConnectionException(
              "Failed to connect to PDO: " . $e->getMessage(),
              $e->getCode(), $e
            );
          }
        }
      }
      $this->_lastConnectTime = time();
    }
    return $this;
  }

  /**
   * Default options for the PDO Connection
   *
   * @return array
   */
  protected function _defaultOptions()
  {
    return [
      \PDO::ATTR_PERSISTENT => false,
      \PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION,
      \PDO::ATTR_TIMEOUT    => 5,
    ];
  }

  /**
   * Check to see if the connection is already open
   *
   * @return bool
   */
  public function isConnected()
  {
    return $this->_connection !== null;
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
    $this->_clearStmtCache();
    $this->_connection = null;
    return $this;
  }

  /**
   * Execute a query
   *
   * @param       $query
   * @param array $values
   *
   * @return int number of affected rows
   *
   * @throws ConnectionException
   */
  public function runQuery($query, array $values = null)
  {
    $perfId = $this->getResolver()->startPerformanceMetric(
      $this,
      DalResolver::MODE_WRITE,
      $query
    );
    $result = $this->_runQuery($query, $values)->rowCount();
    $this->getResolver()->closePerformanceMetric($perfId);
    return $result;
  }

  /**
   * Fetch the results of the query
   *
   * @param       $query
   * @param array $values
   *
   * @return array
   *
   * @throws ConnectionException
   */
  public function fetchQueryResults($query, array $values = null)
  {
    $perfId = $this->getResolver()->startPerformanceMetric(
      $this,
      DalResolver::MODE_READ,
      $query
    );
    $result = $this->_runQuery($query, $values)->fetchAll(\PDO::FETCH_ASSOC);
    $this->getResolver()->closePerformanceMetric($perfId);
    return $result;
  }

  /**
   * Start a transaction
   */
  public function startTransaction()
  {
    return $this->_performWithRetries(
      function ()
      {
        $result = $this->_connection->beginTransaction();
        $this->_inTransaction = true;
        return $result;
      }
    );
  }

  /**
   * Commit the current transaction
   */
  public function commit()
  {
    if($this->_inTransaction)
    {
      try
      {
        $result = $this->_connection->commit();
        return $result;
      }
      catch(\Exception $e)
      {
        try
        {
          $this->rollback();
        }
        catch(\Exception $e)
        {
        }
      }
      finally
      {
        $this->_inTransaction = false;
      }
    }
    throw new PdoException('Not currently in a transaction');
  }

  /**
   * Roll back the current transaction
   */
  public function rollback()
  {
    if($this->_inTransaction)
    {
      try
      {
        $result = $this->_connection->rollBack();
        return $result;
      }
      finally
      {
        $this->_inTransaction = false;
      }
    }
    throw new PdoException('Not currently in a transaction');
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

  /**
   * @param $query
   *
   * @return \PDOStatement
   */
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
      $this->_addStmtCache($cacheKey, $stmt);
    }

    return $stmt;
  }

  /**
   * Delete everything from the prepared statement cache
   */
  protected function _clearStmtCache()
  {
    $this->_prepareCache = [];
  }

  /**
   * @param $query
   *
   * @return string
   */
  protected function _stmtCacheKey($query)
  {
    return md5($query) . strlen($query);
  }

  /**
   * @param string $cacheKey
   */
  protected function _deleteStmtCache($cacheKey)
  {
    unset($this->_prepareCache[$cacheKey]);
  }

  /**
   * @param string        $cacheKey
   * @param \PDOStatement $statement
   */
  protected function _addStmtCache($cacheKey, \PDOStatement $statement)
  {
    if($this->_maxPreparedStatements === null)
    {
      $this->_maxPreparedStatements = $this->_config()
        ->getItem('max_prepared_statements', 10);
    }

    if($this->_maxPreparedStatements > 0)
    {
      $this->_prepareCache[$cacheKey] = $statement;

      while(count($this->_prepareCache) > $this->_maxPreparedStatements)
      {
        array_shift($this->_prepareCache);
      }
    }
  }

  /**
   * @param string $cacheKey
   *
   * @return \PDOStatement
   */
  protected function _getStmtCache($cacheKey)
  {
    return isset(
      $this->_prepareCache[$cacheKey]
    ) ? $this->_prepareCache[$cacheKey] : null;
  }

  protected function _recycleConnectionIfRequired()
  {
    if($this->isConnected())
    {
      if(($this->_lastConnectTime > 0) && (!$this->_inTransaction))
      {
        $recycleTime = (int)$this->_config()
          ->getItem('connection_recycle_time', 900);

        if(($recycleTime > 0)
          && ((time() - $this->_lastConnectTime) >= $recycleTime)
        )
        {
          $this->disconnect()->connect();
        }
      }
    }
    else
    {
      $this->connect();
    }
  }

  protected function _runQuery($query, array $values = null, $retries = null)
  {
    return $this->_performWithRetries(
      function () use ($query, $values)
      {
        $stmt = $this->_getStatement($query);
        if($values)
        {
          $this->_bindValues($stmt, $values);
        }
        $stmt->execute();
        return $stmt;
      },
      function () use ($query)
      {
        $this->_deleteStmtCache($this->_stmtCacheKey($query));
      },
      $retries
    );
  }

  /**
   * @param callable      $func
   * @param callable|null $onError
   * @param int|null      $retryCount
   *
   * @return mixed
   * @throws ConnectionException
   * @throws PdoException
   */
  protected function _performWithRetries(
    callable $func, callable $onError = null, $retryCount = null
  )
  {
    $this->_lastRetryCount = 0;
    $this->_recycleConnectionIfRequired();

    if($retryCount === null)
    {
      $retryCount = (int)$this->_config()->getItem('retries', 2);
    }

    /** @var null|PdoException $exception */
    $exception = null;
    $retries = $retryCount;
    do
    {
      try
      {
        $this->_lastRetryCount++;
        return $func();
      }
      catch(\Exception $sourceException)
      {
        if($onError)
        {
          $onError();
        }

        $recoverable = false;
        if(!($sourceException instanceof \PDOException))
        {
          if(
            stripos($sourceException->getMessage(), "MySQL server has gone")
            || stripos($sourceException->getMessage(), "Error reading result")
          )
          {
            $recoverable = true;
            $exception = new PdoException(
              $sourceException->getMessage(), 2006,
              $sourceException
            );
          }
          else
          {
            $exception = $sourceException;
          }
        }
        else
        {
          $exception = PdoException::from($sourceException);
          $recoverable = $this->_isRecoverableException($exception);
        }
        if($retries > 0 && $recoverable)
        {
          if($this->_shouldReconnectAfterException($exception))
          {
            if($this->_inTransaction)
            {
              error_log(
                'PdoConnection error during transaction: '
                . '(' . $exception->getCode() . ') ' . $exception->getMessage()
              );
              throw $exception;
            }
            $this->disconnect();
          }
          //Sleep 1ms > 30ms
          usleep(mt_rand(1000, 30000));
          $this->connect();
        }
        else
        {
          error_log(
            'PdoConnection Error: (' . $exception->getCode() . ') '
            . $exception->getMessage()
          );
          throw $exception;
        }
      }
      $retries--;
    }
    while($retries > 0);

    if($exception)
    {
      throw $exception;
    }
    else
    {
      throw new PdoException(
        'An unknown error occurred performing a PDO operation. '
        . 'The operation failed after ' . $retryCount . ' retries'
      );
    }
  }

  /**
   * @param PdoException $e
   *
   * @return bool
   */
  private function _isRecoverableException(PdoException $e)
  {
    $code = $e->getPrevious()->getCode();
    if(($code === 0) || Strings::startsWith($code, 42))
    {
      return false;
    }
    return true;
  }

  /**
   * Should we reconnect to the database after this sort of error?
   *
   * @param PdoException $e
   *
   * @return bool
   */
  private function _shouldReconnectAfterException(PdoException $e)
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

  protected function _bindValues(\PDOStatement $stmt, array $values)
  {
    $i = 1;
    foreach($values as $value)
    {
      $type = $this->_pdoTypeForPhpVar($value);
      $stmt->bindValue($i, $value, $type);
      $i++;
    }
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

  /**
   * Retrieve the last inserted ID
   *
   * @param string $name Name of the sequence object from which the ID should
   *                     be returned.
   *
   * @return mixed
   */
  public function getLastInsertId($name = null)
  {
    return $this->_connection->lastInsertId($name);
  }
}
