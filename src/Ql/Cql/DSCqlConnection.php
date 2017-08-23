<?php
namespace Packaged\Dal\Ql\Cql;

use Cassandra\Cluster;
use Cassandra\ExecutionOptions;
use Cassandra\PreparedStatement;
use Cassandra\Session;
use Cassandra\SimpleStatement;
use Packaged\Config\ConfigurableInterface;
use Packaged\Config\ConfigurableTrait;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\Connection\CqlException;
use Packaged\Dal\IResolverAware;
use Packaged\Dal\Ql\IQLDataConnection;
use Packaged\Dal\Traits\ResolverAwareTrait;
use Packaged\Helpers\ValueAs;

class DSCqlConnection
  implements IQLDataConnection, ConfigurableInterface, IResolverAware
{
  use ConfigurableTrait;
  use ResolverAwareTrait;

  /** @var Cluster */
  protected $_cluster = null;
  /** @var Session */
  protected $_session = null;

  /**
   * Open the connection
   *
   * @return static
   *
   * @throws ConnectionException
   */
  public function connect()
  {
    if((!$this->_session) || (!$this->_cluster))
    {
      $this->disconnect();

      $hosts = ValueAs::arr($this->_config()->getItem('hosts', '127.0.0.1'));
      $connectTimeoutMs = (int)$this->_config()->getItem(
        'connect_timeout',
        1000
      );
      $requestTimeoutMs = (int)$this->_config()->getItem(
        'request_timeout',
        1000
      );

      $builder = \Cassandra::cluster()
        ->withContactPoints(...$hosts)
        ->withPort((int)$this->_config()->getItem('port', 9042))
        ->withPersistentSessions(
          ValueAs::bool($this->_config()->getItem('persist', true))
        )
        ->withConnectTimeout($connectTimeoutMs / 1000)
        ->withRequestTimeout($requestTimeoutMs / 1000)
        ->withDefaultConsistency(\Cassandra::CONSISTENCY_QUORUM);

      $username = $this->_config()->getItem('username');
      if($username)
      {
        $builder->withCredentials(
          $username,
          $this->_config()->getItem('password', '')
        );
      }

      $this->_cluster = $builder->build();

      $this->_session = $this->_cluster->connect(
        $this->_config()->getItem('keyspace')
      );
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
    return $this->_session ? true : false;
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
    if($this->_session)
    {
      $this->_session->close();
      $this->_session = null;
    }
    if($this->_cluster)
    {
      $this->_cluster = null;
    }
    return $this;
  }

  /**
   * Execute a query
   *
   * @param       $query
   * @param array $values
   *
   * @return int number of affected rows
   */
  public function runQuery($query, array $values = null)
  {
    $this->connect();
    $this->fetchQueryResults($query, $values);
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
  public function fetchQueryResults($query, array $values = null)
  {
    $this->connect();

    try
    {
      if($values)
      {
        $stmt = $this->_prepare($query);

        /*$rObj = new \ReflectionObject($stmt);
        $methods = $rObj->getMethods();
        $props = $rObj->getProperties();*/

        //$cols = $stmt->columnCount();
        //error_log('Column count: ' . $cols);
        //$firstType = $stmt->columnType(0);
        //error_log('First column type: ' . $firstType);
        //die;

        $values = $this->_packValues($values, $stmt);

        $rows = $this->_session->execute(
          $stmt,
          ['arguments' => $values]
          //new ExecutionOptions(['arguments' => $values])
        );
        /*
        $rows = $this->_session->execute(
          new SimpleStatement($query),
          new ExecutionOptions(['arguments' => $values])
        );
        */
      }
      else
      {
        $rows = $this->_session->execute(new SimpleStatement($query));
      }
    }
    catch(\Exception $e)
    {
      throw $e;
    }

    $result = [];
    foreach($rows as $row)
    {
      $result[] = $row;
    }
    return $result;
  }

  /**
   * @param string $stmt
   *
   * @return \Cassandra\PreparedStatement
   */
  private function _prepare($stmt)
  {
    return $this->_session->prepare($stmt);
  }

  private function _packValues($values, PreparedStatement $stmt)
  {
    $values = array_values($values);
    $packed = [];

    $types = [];
    $i = 0;
    while(true)
    {
      $type = $stmt->columnType($i);
      if($type === null)
      {
        break;
      }
      $types[$i] = $type;
      $i++;
    }

    if(count($values) != count($types))
    {
      throw new CqlException(
        "Incorrect number of arguments. Expected " . count($types)
        . " got " . count($values)
      );
    }

    for($i = 0; $i < count($types); $i++)
    {
      $packed[$i] = DSCqlDataType::packValue($values[$i], $types[$i]);
    }
    return $packed;
  }

  private function _simplePackValues($values)
  {
    if(!$values)
    {
      return $values;
    }

    $packedValues = [];
    foreach($values as $v)
    {
      $type = gettype($v);
      switch($type)
      {
        case 'boolean':
          $packed = new \Cassandra\Tinyint($v ? 1 : 0);
          break;
        case 'integer':
          $packed = new \Cassandra\Bigint($v);
          break;
        case 'double':
          $packed = new \Cassandra\Float($v);
          break;
        case 'string':
          $packed = $v;
          break;
        case 'NULL':
          $packed = null;
          break;
        default:
          throw new \Exception('Unable to pack PHP type ' . $type);
      }
      $packedValues[] = $packed;
    }
    return $packedValues;
  }

  /**
   * Set an item on the configuration for this connection.
   * NOTE: Changing config items will cause a disconnect
   *
   * @param $item
   * @param $value
   *
   * @return $this
   */
  public function setConfig($item, $value)
  {
    $this->disconnect();
    $this->_config()->addItem($item, $value);
    return $this;
  }

  /**
   * Set the request timeout in milliseconds
   *
   * @param int $milliseconds
   *
   * @return $this
   */
  public function setRequestTimeout($milliseconds)
  {
    $this->setConfig('request_timeout', $milliseconds);
    return $this;
  }

  /**
   * Set the connect timeout in milliseconds
   *
   * @param int $milliseconds
   *
   * @return $this
   */
  public function setConnectTimeout($milliseconds)
  {
    $this->setConfig('connect_timeout', $milliseconds);
    return $this;
  }

  //public function setStrictRecoverable($flag)
  //public function setKeyspaceCache(ICacheConnection $cache)
  //public function prepare(
  //public function execute(
  //public function setConfig($item, $value)
  //public function setReceiveTimeout($timeout)
  //public function setSendTimeout($timeout)
}
