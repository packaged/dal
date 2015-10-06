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
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\Connection\CqlException;
use Packaged\Dal\IResolverAware;
use Packaged\Dal\Ql\IQLDataConnection;
use Packaged\Dal\Traits\ResolverAwareTrait;
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

  protected $_prepareCache = [];

  protected $_availableHosts = [];
  protected $_availableHostCount = 0;

  /**
   * Open the connection
   *
   * @return static
   *
   * @throws ConnectionException
   */
  public function connect()
  {
    if($this->_client === null)
    {
      if(empty($this->_availableHosts))
      {
        $this->_availableHosts = ValueAs::arr(
          $this->_config()->getItem('hosts', 'localhost')
        );
        $this->_availableHostCount = count($this->_availableHosts);
      }
      $this->_prepareCache = [];

      if($this->_availableHostCount < 1)
      {
        throw new ConnectionException('Could not find any configured hosts');
      }

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
          shuffle($this->_availableHosts);
          $host = reset($this->_availableHosts);

          $this->_socket = new DalSocket(
            $host,
            (int)$this->_config()->getItem('port', 9160),
            ValueAs::bool($this->_config()->getItem('persist', false))
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
                  ]
                ]
              )
            );
          }
          //@codeCoverageIgnoreEnd

          $keyspace = $this->_config()->getItem('keyspace');
          if($keyspace)
          {
            $this->_setKeyspace($keyspace);
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
              $exception->getMessage(),
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
   *
   * @throws ConnectionException
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
    return $this;
  }

  protected function _setKeyspace($keyspace)
  {
    try
    {
      $this->_client->set_keyspace($keyspace);
    }
    catch(\Exception $e)
    {
      throw CqlException::from($e);
    }
  }

  /**
   * Execute a query
   *
   * @param       $query
   * @param array $values
   *
   * @return int number of affected rows
   */
  public function runQuery($query, array $values = [])
  {
    $perfId = $this->getResolver()->startPerformanceMetric(
      $this,
      DalResolver::MODE_WRITE,
      $query
    );
    $prep = $this->prepare($query);
    $this->execute(
      $prep,
      $values,
      $this->_config()->getItem('write_consistency', ConsistencyLevel::ONE)
    );
    $this->getResolver()->closePerformanceMetric($perfId);
    return 1;
  }

  /**
   * Fetch the results of the query
   *
   * @param       $query
   * @param array $values
   *
   * @return array
   */
  public function fetchQueryResults($query, array $values = [])
  {
    $perfId = $this->getResolver()->startPerformanceMetric(
      $this,
      DalResolver::MODE_READ,
      $query
    );
    $prep = $this->prepare($query);
    $results = $this->execute(
      $prep,
      $values,
      $this->_config()->getItem('read_consistency', ConsistencyLevel::ONE)
    );
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
      $this->connect();
      return $this->_getStatement($query, $compression)->prepare();
    }
    catch(\Exception $exception)
    {
      $this->_clearCache($query, $compression);
      $e = CqlException::from($exception);
      if(Strings::startsWith(
          $e->getMessage(),
          'No keyspace has been specified.'
        )
        && $this->_config()->has('keyspace')
      )
      {
        $this->_setKeyspace($this->_config()->getItem('keyspace'));
        return $this->prepare($query, $compression, $retries - 1);
      }

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
      $this->connect();
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
        && !Strings::startsWith($e->getMessage(), 'Prepared query with ID'))
      || ($e->getPrevious() instanceof NotFoundException)
    )
    {
      return false;
    }
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
