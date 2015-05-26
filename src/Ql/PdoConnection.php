<?php
namespace Packaged\Dal\Ql;

use Packaged\Config\ConfigurableInterface;
use Packaged\Config\ConfigurableTrait;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\Connection\PdoException;
use Packaged\Dal\IResolverAware;
use Packaged\Dal\Traits\ResolverAwareTrait;
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

  protected $_prepareCache = [];

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

      $remainingAttempts = ((int)$this->_config()->getItem('connect_retries', 3));

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

          if(!isset($options[\PDO::ATTR_EMULATE_PREPARES]))
          {
            $serverVersion = $this->_connection->getAttribute(
              \PDO::ATTR_SERVER_VERSION
            );
            $this->_connection->setAttribute(
              \PDO::ATTR_EMULATE_PREPARES,
              version_compare($serverVersion, '5.1.17', '<')
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
      \PDO::ATTR_PERSISTENT => true,
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
    $this->_connection = null;
    $this->_prepareCache = [];
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
    $stmt = $this->_connection->prepare($query);
    $this->_prepareCache[$cacheKey] = $stmt;
    return $this->_prepareCache[$cacheKey];
  }

  protected function _clearCache($cacheKey)
  {
    unset($this->_prepareCache[$cacheKey]);
  }

  protected function _runQuery($query, array $values = null, $retries = null)
  {
    if($retries === null)
    {
      $retries = (int)$this->_config()->getItem('retries', 2);
    }
    try
    {
      $stmt = $this->_getStatement($query);
      $values = $this->_prepareValues($values);
      $stmt->execute($values);
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
    if(starts_with($e->getPrevious()->getCode(), 42))
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

  protected function _prepareValues($values)
  {
    if(is_array($values))
    {
      foreach($values as $k => $value)
      {
        if(is_bool($value))
        {
          $values[$k] = $value ? 1 : 0;
        }
      }
    }
    return $values;
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
