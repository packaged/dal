<?php
namespace Packaged\Dal\Ql\Cql;

use cassandra\AuthenticationRequest;
use cassandra\CassandraClient;
use cassandra\Column;
use cassandra\Compression;
use cassandra\ConsistencyLevel;
use cassandra\CqlResult;
use cassandra\CqlResultType;
use cassandra\CqlRow;
use cassandra\InvalidRequestException;
use cassandra\NotFoundException;
use cassandra\TimedOutException;
use Packaged\Config\ConfigurableInterface;
use Packaged\Config\ConfigurableTrait;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Cache\CacheItem;
use Packaged\Dal\Cache\Ephemeral\EphemeralConnection;
use Packaged\Dal\Cache\ICacheConnection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\Connection\CqlException;
use Packaged\Dal\IResolverAware;
use Packaged\Dal\Ql\IQLDataConnection;
use Packaged\Dal\Traits\ResolverAwareTrait;
use Packaged\Helpers\RetryHelper;
use Packaged\Helpers\Strings;
use Packaged\Helpers\ValueAs;
use Thrift\Exception\TException;
use Thrift\Exception\TTransportException;
use Thrift\Protocol\TBinaryProtocolAccelerated;
use Thrift\Transport\TFramedTransport;
use Thrift\Transport\TSocket;
use Thrift\Transport\TTransport;

class CqlConnection
  implements IQLDataConnection, ConfigurableInterface, IResolverAware
{
  use ConfigurableTrait;
  use ResolverAwareTrait;

  /**
   * @var CassandraClient
   */
  protected $_client;
  /**
   * @var DalSocket
   */
  protected $_socket;
  /**
   * @var TFramedTransport
   */

  protected $_transport;
  /**
   * @var TBinaryProtocolAccelerated
   */
  protected $_protocol;

  /**
   * @var bool
   */
  protected $_connected = false;
  protected $_strictRecoverable = false;

  protected $_prepareCache = [];

  protected $_availableHosts = [];
  protected $_availableHostCount = 0;

  /**
   * @var ICacheConnection
   */
  private static $_keyspaceCache;
  private $_keyspaceCacheLocal;

  public function setStrictRecoverable($flag)
  {
    $this->_strictRecoverable = (bool)$flag;
    return $this;
  }

  /**
   * Open the connection
   *
   * @return static
   *
   * @throws ConnectionException
   * @throws \Exception
   */
  public function connect()
  {
    if($this->_client === null)
    {
      $this->_prepareCache = [];

      $remainingAttempts = (int)$this->_config()->getItem(
        'connect_attempts',
        1
      );

      while($remainingAttempts > 0)
      {
        $remainingAttempts--;
        $exception = null;
        try
        {
          if(empty($this->_availableHosts))
          {
            $this->_availableHosts = ValueAs::arr(
              $this->_config()->getItem('hosts', 'localhost')
            );
            $this->_availableHostCount = count($this->_availableHosts);
            if($this->_availableHostCount < 1)
            {
              throw new ConnectionException(
                'Could not find any configured hosts'
              );
            }
          }

          shuffle($this->_availableHosts);
          $host = reset($this->_availableHosts);

          $this->_socket = new DalSocket(
            $host,
            (int)$this->_config()->getItem('port', 9160),
            $this->_isPersistent()
          );
          $this->_socket->setConnectTimeout(
            (int)$this->_config()->getItem('connect_timeout', 1000)
          );
          $this->_socket->setRecvTimeout(
            (int)$this->_config()->getItem('receive_timeout', 1000)
          );
          $this->_socket->setSendTimeout(
            (int)$this->_config()->getItem('send_timeout', 1000)
          );

          $this->_transport = new TFramedTransport($this->_socket);
          $this->_protocol = new TBinaryProtocolAccelerated($this->_transport);
          $this->_client = new CassandraClient($this->_protocol);

          $this->_transport->open();
          $this->_connected = true;

          $username = $this->_config()->getItem('username');
          // @codeCoverageIgnoreStart
          if($username)
          {
            $this->_client->login(
              new AuthenticationRequest(
                [
                  'credentials' => [
                    'username' => $username,
                    'password' => $this->_config()->getItem('password', ''),
                  ],
                ]
              )
            );
          }
          //@codeCoverageIgnoreEnd

          $keyspace = $this->_config()->getItem('keyspace');
          if($keyspace)
          {
            $this->_setKeyspace($keyspace, !$this->_socket->isPersistent());
          }
        }
        catch(TException $e)
        {
          $exception = $e;
        }
        catch(CqlException $e)
        {
          $exception = $e;
        }

        if($exception)
        {
          $this->_removeCurrentHost();
          $this->disconnect();
          if($remainingAttempts < 1)
          {
            if(!($exception instanceof CqlException))
            {
              $exception = CqlException::from($exception);
            }
            throw new ConnectionException(
              'Failed to connect: ' . $exception->getMessage(),
              $exception->getCode(),
              $exception->getPrevious()
            );
          }
        }
        else
        {
          break;
        }
      }
    }
    return $this;
  }

  /**
   * @return string|bool
   */
  protected function _getKeyspaceCacheKey()
  {
    if($this->_socket)
    {
      return md5($this->_socket->getHost() . $this->_socket->getPort());
    }
    return false;
  }

  /**
   * Check to see if the connection is already open
   *
   * @return bool
   */
  public function isConnected()
  {
    return (bool)$this->_connected;
  }

  /**
   * Disconnect the open connection
   *
   * @return static
   */
  public function disconnect()
  {
    $this->_client = null;
    if($this->_transport instanceof TTransport)
    {
      $this->_transport->close();
    }
    $this->_transport = null;
    $this->_protocol = null;
    $this->_prepareCache = [];
    $this->_connected = false;
    $keyspaceCacheKey = $this->_getKeyspaceCacheKey();
    if($keyspaceCacheKey)
    {
      $this->_getKeyspaceCache()->deleteKey($keyspaceCacheKey);
    }
    return $this;
  }

  /**
   * @return bool
   */
  private function _isPersistent()
  {
    try
    {
      return ValueAs::bool($this->_config()->getItem('persist', false));
    }
    catch(\Exception $e)
    {
      return false;
    }
  }

  public function setKeyspaceCache(ICacheConnection $cache)
  {
    if($this->_isPersistent())
    {
      self::$_keyspaceCache = $cache;
    }
    else
    {
      $this->_keyspaceCacheLocal = $cache;
    }
  }

  /**
   * @return ICacheConnection
   */
  private function _getKeyspaceCache()
  {
    if($this->_isPersistent())
    {
      if(!self::$_keyspaceCache)
      {
        self::$_keyspaceCache = new EphemeralConnection();
        self::$_keyspaceCache->configure(new ConfigSection('cql_keyspace', ['pool_name' => 'cql_keyspace']));
      }
      return self::$_keyspaceCache;
    }
    else
    {
      if(!$this->_keyspaceCacheLocal)
      {
        $this->_keyspaceCacheLocal = new EphemeralConnection();
        $this->_keyspaceCacheLocal->configure(new ConfigSection('cql_keyspace', ['pool_name' => 'cql_keyspace']));
      }
      return $this->_keyspaceCacheLocal;
    }
  }

  /**
   * @param string $keyspace
   * @param bool   $force
   *
   * @throws CqlException
   */
  protected function _setKeyspace($keyspace, $force = false)
  {
    if($keyspace)
    {
      try
      {
        $cacheStore = $this->_getKeyspaceCache();
        $cacheKey = $this->_getKeyspaceCacheKey();
        /** @var CacheItem $cacheItem */
        $cacheItem = $cacheStore->getItem($cacheKey);
        if($force || $cacheItem->get() !== $keyspace)
        {
          $this->_client->set_keyspace($keyspace);
          $cacheStore->saveItem($cacheItem->hydrate($keyspace));
        }
      }
      catch(\Exception $e)
      {
        throw CqlException::from($e);
      }
    }
  }

  /**
   * Execute a query
   *
   * @param       $query
   * @param array $values
   *
   * @return int number of affected rows
   * @throws CqlException
   * @throws \Exception
   * @throws \Packaged\Dal\Exceptions\DalException
   */
  public function runQuery($query, array $values = null)
  {
    $this->_prepareAndExecute(
      DalResolver::MODE_WRITE,
      $this->_config()->getItem('write_consistency', ConsistencyLevel::ONE),
      $query,
      $values
    );
    return 1;
  }

  /**
   * Fetch the results of the query
   *
   * @param       $query
   * @param array $values
   *
   * @return array
   * @throws CqlException
   * @throws \Exception
   * @throws \Packaged\Dal\Exceptions\DalException
   */
  public function fetchQueryResults($query, array $values = null)
  {
    return $this->_prepareAndExecute(
      DalResolver::MODE_READ,
      $this->_config()->getItem('read_consistency', ConsistencyLevel::ONE),
      $query,
      $values
    );
  }

  /**
   * @param string     $mode
   * @param int        $consistency
   * @param string     $query
   * @param array|null $values
   *
   * @return array|mixed
   * @throws CqlException
   * @throws \Exception
   * @throws \Packaged\Dal\Exceptions\DalException
   */
  protected function _prepareAndExecute($mode, $consistency, $query, array $values = null)
  {
    $perfId = $this->getResolver()->startPerformanceMetric($this, $mode, $query);
    if($values)
    {
      $prep = $this->prepare($query);
      $results = $this->execute($prep, $values, $consistency);
    }
    else
    {
      $results = $this->runRawQuery($query, $consistency);
    }
    $this->getResolver()->closePerformanceMetric($perfId);
    return $results;
  }

  /**
   * @param $query
   * @param $compression
   *
   * @return string
   */
  protected function _cacheKey($query, $compression)
  {
    return $compression . '~' . $query;
  }

  /**
   * @param $query
   * @param $compression
   *
   * @return bool
   */
  protected function _isCached($query, $compression)
  {
    return isset($this->_prepareCache[$this->_cacheKey($query, $compression)]);
  }

  /**
   * @param $query
   * @param $compression
   *
   * @return CqlStatement
   */
  protected function _getStatement($query, $compression)
  {
    if($this->_isCached($query, $compression))
    {
      return $this->_prepareCache[$this->_cacheKey($query, $compression)];
    }
    $stmt = new CqlStatement($this->_client, $query, $compression);
    $this->_prepareCache[$this->_cacheKey($query, $compression)] = $stmt;
    return $this->_prepareCache[$this->_cacheKey($query, $compression)];
  }

  protected function _clearCache($query, $compression)
  {
    unset($this->_prepareCache[$this->_cacheKey($query, $compression)]);
  }

  /**
   * @param string $query
   * @param int    $compression
   * @param int    $retries
   *
   * @return CqlStatement
   * @throws CqlException
   * @throws \Exception
   */
  public function prepare(
    $query, $compression = Compression::NONE, $retries = null
  )
  {
    if($retries === null)
    {
      $retries = (int)$this->_config()->getItem('retries', 2);
    }
    try
    {
      $this->connect()->_setKeyspace($this->_config()->getItem('keyspace'));
      return $this->_getStatement($query, $compression)->prepare();
    }
    catch(\Exception $exception)
    {
      $this->_clearCache($query, $compression);
      $e = CqlException::from($exception);

      if($retries > 0 && $this->_isRecoverableException($e))
      {
        $this->disconnect();
        return $this->prepare($query, $compression, $retries - 1);
      }
      error_log(
        'CqlConnection Error: (' . $e->getCode() . ') ' . $e->getMessage()
      );
      $this->disconnect();
      throw $e;
    }
  }

  /**
   * Run a query without preparing it and without retries
   *
   * @param string $query
   * @param int    $consistency
   * @param int    $retries
   *
   * @return array The query results
   * @throws \Exception
   */
  public function runRawQuery(
    $query, $consistency = ConsistencyLevel::QUORUM, $retries = null
  )
  {
    if($retries === null)
    {
      $retries = (int)$this->_config()->getItem('retries', 2);
    }
    return RetryHelper::retry(
      $retries,
      function () use ($query, $consistency) {
        $this->connect()->_setKeyspace($this->_config()->getItem('keyspace'));
        $result = $this->_client->execute_cql3_query(
          $query,
          Compression::NONE,
          $consistency
        );

        /**
         * @var $result CqlResult
         */
        if($result->type == CqlResultType::VOID)
        {
          return true;
        }

        $return = [];
        foreach($result->rows as $row)
        {
          /**
           * @var $row CqlRow
           */
          $resultRow = [];
          foreach($row->columns as $column)
          {
            /**
             * @var $column Column
             */
            $resultRow[$column->name] = CqlDataType::unpack(
              $result->schema->value_types[$column->name],
              $column->value
            );
          }

          $return[] = $resultRow;
        }

        return $return;
      }
    );
  }

  /**
   * @param CqlStatement $statement
   * @param array        $parameters
   * @param int          $consistency
   * @param int          $retries
   *
   * @return array|mixed
   * @throws CqlException
   * @throws \Exception
   */
  public function execute(
    CqlStatement $statement, array $parameters = [],
    $consistency = ConsistencyLevel::ONE, $retries = null
  )
  {
    if($retries === null)
    {
      $retries = (int)$this->_config()->getItem(
        'retries',
        min(max($this->_availableHostCount, 2), 10)
      );
    }
    $return = [];
    try
    {
      $this->connect()->_setKeyspace($this->_config()->getItem('keyspace'));
      $packedParameters = [];
      foreach($parameters as $k => $value)
      {
        $packedParameters[] = CqlDataType::pack(
          $statement->getStatement()->variable_types[$k],
          $value
        );
      }
      $result = $this->_client->execute_prepared_cql3_query(
        $statement->getStatement()->itemId,
        $packedParameters,
        $consistency
      );

      /**
       * @var $result CqlResult
       */
      if($result->type == CqlResultType::VOID)
      {
        return true;
      }

      foreach($result->rows as $row)
      {
        /**
         * @var $row CqlRow
         */
        $resultRow = [];
        foreach($row->columns as $column)
        {
          /**
           * @var $column Column
           */
          $resultRow[$column->name] = CqlDataType::unpack(
            $result->schema->value_types[$column->name],
            $column->value
          );
        }

        $return[] = $resultRow;
      }
    }
    catch(\Exception $exception)
    {
      if($exception instanceof TimedOutException
        && (!(Strings::startsWith($statement->getQuery(), 'SELECT', false)))
      )
      {
        // TimedOutException on writes is NOT a failure
        // http://www.datastax.com/dev/blog/how-cassandra-deals-with-replica-failure
        return true;
      }

      $this->_clearCache($statement->getQuery(), $statement->getCompression());
      $e = CqlException::from($exception);

      if($retries > 0 && $this->_isRecoverableException($e))
      {
        $this->disconnect();
        if(Strings::startsWith($e->getMessage(), 'Prepared query with ID'))
        {
          // re-prepare statement
          $statement = $this->prepare(
            $statement->getQuery(),
            $statement->getCompression()
          );
        }
        return $this->execute(
          $statement,
          $parameters,
          $consistency,
          $retries - 1
        );
      }
      error_log(
        'CqlConnection Error: (' . $e->getCode() . ') ' . $e->getMessage()
      );
      $this->disconnect();
      throw $e;
    }
    return $return;
  }

  protected function _removeCurrentHost()
  {
    if($this->_socket)
    {
      $this->_availableHosts = array_diff(
        (array)$this->_availableHosts,
        [$this->_socket->getHost()]
      );
    }
  }

  /**
   * @param CqlException $e
   *
   * @return bool
   */
  private function _isRecoverableException(CqlException $e)
  {
    if($e->getPrevious() instanceof TTransportException
      || Strings::startsWith(
        $e->getMessage(),
        'TSocketPool: All hosts in pool are down.'
      )
    )
    {
      $this->_removeCurrentHost();
      return true;
    }

    if(($e->getPrevious() instanceof InvalidRequestException
      && Strings::startsWith($e->getMessage(), 'Prepared query with ID'))
    )
    {
      return true;
    }

    if($this->_strictRecoverable
      || $e->getPrevious() instanceof NotFoundException
      || $e->getPrevious() instanceof InvalidRequestException
    )
    {
      return false;
    }

    error_log(
      'Exception As Recoverable: (' . $e->getCode() . ') ' . $e->getMessage()
    );

    return true;
  }

  /**
   * Set an item on the configuration for this connection
   *
   * @param $item
   * @param $value
   *
   * @return $this
   */
  public function setConfig($item, $value)
  {
    $this->_config()->addItem($item, $value);
    return $this;
  }

  /**
   * Set the receive timeout in milliseconds
   *
   * @param $timeout
   *
   * @return $this
   */
  public function setReceiveTimeout($timeout)
  {
    $this->_config()->addItem('receive_timeout', (int)$timeout);
    if($this->_socket instanceof TSocket)
    {
      $this->_socket->setRecvTimeout((int)$timeout);
    }
    return $this;
  }

  /**
   * Set the send timeout in milliseconds
   *
   * @param $timeout
   *
   * @return $this
   */
  public function setSendTimeout($timeout)
  {
    $this->_config()->addItem('send_timeout', (int)$timeout);
    if($this->_socket instanceof TSocket)
    {
      $this->_socket->setSendTimeout((int)$timeout);
    }
    return $this;
  }
}
