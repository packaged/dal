<?php
namespace Packaged\Dal\Ql;

use Packaged\Config\ConfigurableInterface;
use Packaged\Config\ConfigurableTrait;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Helpers\ValueAs;

class PdoConnection
  implements IQLDataConnection, ConfigurableInterface, ILastInsertId
{
  use ConfigurableTrait;

  /**
   * @var \PDO
   */
  protected $_connection;

  /**
   * Open the connection
   *
   * @return static
   *
   * @throws ConnectionException
   */
  public function connect()
  {
    $dsn = $this->_config()->getItem('dsn', null);

    if($dsn === null)
    {
      $dsn = sprintf(
        "mysql:host=%s;dbname=%s;port=%d",
        $this->_config()->getItem('hostname', '127.0.0.1'),
        $this->_config()->getItem('database', 'packaged_dal'),
        $this->_config()->getItem('port', 3306)
      );
    }

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
    }
    catch(\Exception $e)
    {
      throw new ConnectionException(
        "Failed to connect to PDO: " . $e->getMessage(),
        $e->getCode(), $e
      );
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
    try
    {
      $statement = $this->_connection->prepare($query);
      $statement->execute($values);
    }
    catch(\PDOException $e)
    {
      if(isset($e->errorInfo[2]))
      {
        throw new ConnectionException($e->errorInfo[2], $e->errorInfo[1]);
      }
      throw new ConnectionException($e->getMessage(), $e->getCode());
    }

    return $statement->rowCount();
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
    try
    {
      $statement = $this->_connection->prepare($query);
      $statement->execute($values);
      $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
    }
    catch(\PDOException $e)
    {
      throw new ConnectionException($e->errorInfo[2], $e->errorInfo[1]);
    }
    return (array)$results;
  }

  /**
   * Retrieve the last inserted ID
   *
   * @return mixed
   */
  public function getLastInsertId()
  {
    return $this->_connection->lastInsertId();
  }
}
