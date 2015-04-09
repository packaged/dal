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
use Packaged\Config\ConfigurableInterface;
use Packaged\Config\ConfigurableTrait;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\Connection\CqlException;
use Packaged\Dal\IResolverAware;
use Packaged\Dal\Ql\IQLDataConnection;
use Packaged\Dal\Traits\ResolverAwareTrait;
use Packaged\Helpers\ValueAs;
use Thrift\Exception\TException;
use Thrift\Protocol\TBinaryProtocolAccelerated;
use Thrift\Transport\TFramedTransport;
use Thrift\Transport\TSocketPool;
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
   * @var TSocketPool
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
      $this->_socket = new TSocketPool(
        ValueAs::arr($this->_config()->getItem('hosts', 'localhost')),
        (int)$this->_config()->getItem('port', 9160),
        ValueAs::bool($this->_config()->getItem('persist', false))
      );

      $this->_socket->setSendTimeout(
        (int)$this->_config()->getItem('connect_timeout', 1000)
      );
      $this->_socket->setRetryInterval(
        (int)$this->_config()->getItem('retry_interval', 0)
      );
      $this->_socket->setNumRetries(
        (int)$this->_config()->getItem('retries', 1)
      );

      $this->_transport = new TFramedTransport($this->_socket);
      $this->_protocol = new TBinaryProtocolAccelerated($this->_transport);
      $this->_client = new CassandraClient($this->_protocol);

      try
      {
        $this->_transport->open();
        $this->_connected = true;

        $this->_socket->setRecvTimeout(
          (int)$this->_config()->getItem('receive_timeout', 1000)
        );
        $this->_socket->setSendTimeout(
          (int)$this->_config()->getItem('send_timeout', 1000)
        );

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
        $exception = CqlException::from($e);
        $this->_connected = false;
        throw new ConnectionException(
          $exception->getMessage(),
          $exception->getCode(),
          $e
        );
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
    $this->_connected = false;
    return $this;
  }

  protected function _setKeyspace($keyspace)
  {
    try
    {
      $this->connect()->_client->set_keyspace($keyspace);
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
      $stmt = new CqlStatement($this->connect()->_client, $query, $compression);
      $stmt->prepare();
      return $stmt;
    }
    catch(\Exception $exception)
    {
      $e = CqlException::from($exception);
      if(starts_with($e->getMessage(), 'No keyspace has been specified.')
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
      $retries = (int)$this->_config()->getItem('retries', 2);
    }
    $return = [];
    try
    {
      $packedParameters = [];
      foreach($parameters as $k => $value)
      {
        $packedParameters[] = CqlDataType::pack(
          $statement->getStatement()->variable_types[$k],
          $value
        );
      }
      $result = $this->connect()->_client->execute_prepared_cql3_query(
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
      $e = CqlException::from($exception);
      if($retries > 0 && $this->_isRecoverableException($e))
      {
        $this->disconnect();
        if(starts_with($e->getMessage(), 'Prepared query with ID'))
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
      throw $e;
    }
    return $return;
  }

  /**
   * @param CqlException $e
   *
   * @return bool
   */
  private function _isRecoverableException(CqlException $e)
  {
    if(($e->getPrevious() instanceof InvalidRequestException
        && !starts_with($e->getMessage(), 'Prepared query with ID'))
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
    if($this->_socket instanceof TSocketPool)
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
    if($this->_socket instanceof TSocketPool)
    {
      $this->_socket->setSendTimeout((int)$timeout);
    }
    return $this;
  }
}
