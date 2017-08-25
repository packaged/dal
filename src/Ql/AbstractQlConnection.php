<?php
namespace Packaged\Dal\Ql;

use Packaged\Config\ConfigurableInterface;
use Packaged\Config\ConfigurableTrait;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\IResolverAware;
use Packaged\Dal\Traits\ResolverAwareTrait;

abstract class AbstractQlConnection
  implements IQLDataConnection, ConfigurableInterface, ILastInsertId,
             IResolverAware
{
  use ConfigurableTrait;
  use ResolverAwareTrait;

  protected $_selectedDb;
  protected $_prepareCache = [];
  protected $_lastConnectTime = 0;
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
      $remainingAttempts = ((int)$this->_config()->getItem(
        'connect_retries',
        3
      ));

      while(($remainingAttempts > 0) && (!$this->isConnected()))
      {
        try
        {
          $this->_makeConnection();
          $remainingAttempts = 0;
        }
        catch(\Exception $e)
        {
          $remainingAttempts--;
          $this->disconnect();
          if($remainingAttempts > 0)
          {
            usleep(mt_rand(1000, 5000));
          }
          else
          {
            throw new ConnectionException(
              "Failed to connect: " . $e->getMessage(),
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
   * Create connection
   *
   * @return mixed
   */
  abstract public function _makeConnection();

  /**
   * Default options for the Connection
   *
   * @return array
   */
  protected function _defaultOptions()
  {
    return [];
  }

  /**
   * Check to see if the connection is already open
   *
   * @return bool
   */
  abstract public function isConnected();

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
    $this->_disconnect();
    return $this;
  }

  abstract protected function _disconnect();

  abstract protected function _switchDatabase($db);

  public function switchDatabase($db)
  {
    $this->_selectedDb = $db;
    if($this->isConnected())
    {
      $this->_switchDatabase($db);
    }
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
    $stmt = $this->_runQuery($query, $values);
    $result = $this->_affectedRows($stmt);
    $this->_freeStatement($stmt);
    $this->getResolver()->closePerformanceMetric($perfId);
    return $result;
  }

  /**
   * @param mixed $stmt
   *
   * @return integer
   */
  abstract protected function _affectedRows($stmt);

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
    $stmt = $this->_runQuery($query, $values);
    $result = $this->_fetchAll($stmt);
    $this->_freeStatement($stmt);
    $this->getResolver()->closePerformanceMetric($perfId);
    return $result;
  }

  abstract protected function _fetchAll($stmt);

  abstract protected function _freeStatement($stmt);

  /**
   * Open a transaction on the connection
   *
   * @return bool Transaction opening was successful
   */
  abstract protected function _startTransaction();

  /**
   * Commit the pending queries
   *
   * @return bool
   */
  abstract protected function _commitTransaction();

  /**
   * Rollback the transaction
   *
   * @return bool
   */
  abstract protected function _rollbackTransaction();

  /**
   * Start a transaction
   */
  public function startTransaction()
  {
    return $this->_performWithRetries(
      function () {
        $result = $this->_startTransaction();
        if($result)
        {
          $this->_inTransaction = true;
        }
        return $result;
      }
    );
  }

  /**
   * Commit the current transaction
   *
   * @return bool
   * @throws ConnectionException
   */
  public function commit()
  {
    if($this->_inTransaction)
    {
      try
      {
        return $this->_commitTransaction();
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
    throw new ConnectionException('Not currently in a transaction');
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
        return $this->_rollbackTransaction();
      }
      finally
      {
        $this->_inTransaction = false;
      }
    }
    throw new ConnectionException('Not currently in a transaction');
  }

  /**
   * @param $query
   *
   * @return mixed
   */
  abstract protected function _getStatement($query);

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
   * @param string $cacheKey
   * @param mixed  $statement
   */
  protected function _addStmtCache($cacheKey, $statement)
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
   * @return mixed
   */
  protected function _getStmtCache($cacheKey)
  {
    return isset($this->_prepareCache[$cacheKey])
      ? $this->_prepareCache[$cacheKey] : null;
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
      function () use ($query, $values) {
        $stmt = $this->_getStatement($query);
        if($values)
        {
          $this->_bindValues($stmt, $values);
        }
        $stmt->execute();
        return $stmt;
      },
      function () use ($query) {
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

    /** @var null|ConnectionException $exception */
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

        $exception = $this->_sanitizeException($sourceException);
        $recoverable = $this->_isRecoverableException($exception);
        if($retries > 0 && $recoverable)
        {
          if($this->_shouldReconnectAfterException($exception))
          {
            if($this->_inTransaction)
            {
              error_log(
                'Connection error during transaction: '
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
          $this->disconnect();
          error_log(
            'Connection Error: (' . $exception->getCode() . ') '
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
      throw new ConnectionException(
        'An unknown error occurred performing an operation. '
        . 'The operation failed after ' . $retryCount . ' retries'
      );
    }
  }

  /**
   * @param \Exception $e
   *
   * @return \Exception
   */
  protected function _sanitizeException(\Exception $e)
  {
    return ConnectionException::from($e);
  }

  /**
   * @param \Exception $e
   *
   * @return bool
   */
  abstract protected function _isRecoverableException(\Exception $e);

  /**
   * Should we reconnect to the database after this sort of error?
   *
   * @param \Exception $e
   *
   * @return bool
   */
  private function _shouldReconnectAfterException(\Exception $e)
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

  /**
   * @param mixed $stmt
   * @param array $values
   *
   * @return mixed
   */
  abstract protected function _bindValues($stmt, array $values);

  /**
   * Retrieve the last inserted ID
   *
   * @param string $name Name of the sequence object from which the ID should
   *                     be returned.
   *
   * @return mixed
   */
  abstract public function getLastInsertId($name = null);
}
