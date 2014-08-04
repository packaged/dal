<?php
namespace Packaged\Dal;

use Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException;
use Packaged\Dal\Exceptions\DalResolver\DataStoreNotFoundException;

/**
 * Standard Packaged Connection Resolver
 */
class DalResolver implements IConnectionResolver
{
  /**
   * @var IDataConnection[]
   */
  protected $_connections;

  /**
   * @var IDataStore[]
   */
  protected $_datastores;

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

  /**
   * Retrieve a data store from the resolver by name
   *
   * @param $name
   *
   * @return IDataStore
   *
   * @throws DataStoreNotFoundException;
   */
  public function getDataStore($name)
  {
    if(isset($this->_datastores[$name]))
    {
      return $this->_datastores[$name];
    }

    throw new DataStoreNotFoundException(
      "No data store could be found with the name '$name'", 404
    );
  }

  /**
   * Add a data store to the resolver
   *
   * @param string     $name name for the connection
   * @param IDataStore $dataStore
   *
   * @return $this
   */
  public function addDataStore($name, IDataStore $dataStore)
  {
    $this->_datastores[$name] = $dataStore;
    return $this;
  }
}
