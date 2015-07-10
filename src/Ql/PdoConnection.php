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
      $this->_prepareCache = [];

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
   * @return array
   */
  protected function _defaultOptions()
  {
    return [
      \PDO::ATTR_PERSISTENT => false,
      \PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION,
      \PDO::ATTR_TIMEOUT    => 5
    ];
  }

  /**
   * Check to see if the connection is already open
   *
   * @return bool
   */
  public function isConnected()
  {
    if($this->_connection === null)
    {
      return false;
    }
    else
    {
      try
      {
        $this->_connection->query("SELECT 1");
        return true;
      }
      catch(\Exception $e)
      {
        $this->_connection = null;
        return false;
      }
    }
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
    $this->_prepareCache = [];
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
   * @param $query
   *
   * @return string
   */
  protected function _cacheKey($query)
  {
    return md5($query) . strlen($query);
  }

  /**
   * @param $queryCacheKey
   *
   * @return bool
   */
  protected function _isCached($queryCacheKey)
  {
    return isset($this->_prepareCache[$queryCacheKey]);
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
    $cacheKey = $this->_cacheKey($query);
    if($this->_isCached($cacheKey))
    {
      return $this->_prepareCache[$cacheKey];
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
          } finally
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
      $this->_prepareCache[$cacheKey] = $stmt;
    }

    return $stmt;
  }

  protected function _clearCache($cacheKey)
  {
    unset($this->_prepareCache[$cacheKey]);
  }

  protected function _recycleConnectionIfRequired()
  {
    if($this->_lastConnectTime > 0)
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

  protected function _runQuery($query, array $values = null, $retries = null)
  {
    $this->_recycleConnectionIfRequired();

    if($retries === null)
    {
      $retries = (int)$this->_config()->getItem('retries', 2);
    }
    try
    {
      $stmt = $this->_getStatement($query);
      if($values)
      {
        $this->_bindValues($stmt, $values);
      }
      $stmt->execute();
    }
    catch(\PDOException $sourceException)
    {
      $this->_clearCache($this->_cacheKey($query));
      $e = PdoException::from($sourceException);
      if($retries > 0 && $this->_isRecoverableException($e))
      {
        if($this->_shouldReconnectAfterException($e))
        {
          $this->disconnect()->connect();
        }
        else if($this->_shouldDelayAfterException($e))
        {
          // Sleep for between 0.1 and 3 milliseconds
          usleep(mt_rand(100, 3000));
        }
        return $this->_runQuery($query, $values, $retries - 1);
      }
      error_log(
        'PdoConnection Error: (' . $e->getCode() . ') ' . $e->getMessage()
      );
      throw $e;
    }
    return $stmt;
  }

  /**
   * @param PdoException $e
   *
   * @return bool
   */
  private function _isRecoverableException(PdoException $e)
  {
    if(Strings::startsWith($e->getPrevious()->getCode(), 42))
    {
      return false;
    }
    return true;
  }

  /**
   * Should we delay for a random time before retrying this query?
   *
   * @param PdoException $e
   *
   * @return bool
   */
  private function _shouldDelayAfterException(PdoException $e)
  {
    // Deadlock errors: MySQL error 1213, SQLSTATE code 40001
    return in_array($e->getCode(), [1213, 40001]);
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
    // "MySQL server has gone away"
    return $e->getCode() == 2006;
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
      if($var >= (2 ^ 31))
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
