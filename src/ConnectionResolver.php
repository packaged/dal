<?php
namespace Packaged\Dal;

use Packaged\Dal\Exceptions\ConnectionResolver\ConnectionNotFoundException;

/**
 * Standard Packaged Connection Resolver
 */
class ConnectionResolver implements IConnectionResolver
{
  /**
   * @var IDataConnection[]
   */
  protected $_connections;

  /**
   * Retrieve a connection from the resolver by name
   *
   * @param $name
   *
   * @return IDataConnection
   *
   * @throws ConnectionNotFoundException;
   */
  public function getConnection($name)
  {
    if(isset($this->_connections[$name]))
    {
      return $this->_connections[$name];
    }

    throw new ConnectionNotFoundException(
      "No connection could be found with the name '$name'", 404
    );
  }

  /**
   * Add a connection to the resolver
   *
   * @param string          $name name for the connection
   * @param IDataConnection $connection
   *
   * @return $this
   */
  public function addConnection($name, IDataConnection $connection)
  {
    $this->_connections[$name] = $connection;
    return $this;
  }
}
