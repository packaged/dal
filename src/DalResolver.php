<?php
namespace Packaged\Dal;

use Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException;
use Packaged\Dal\Exceptions\DalResolver\DataStoreNotFoundException;
use Packaged\Dal\FileSystem\FileSystemDataStore;

/**
 * Standard Packaged Connection Resolver
 */
class DalResolver implements IConnectionResolver
{
  public function __construct()
  {
    $this->addDataStoreCallable(
      'filesystem',
      function ()
      {
        return new FileSystemDataStore();
      }
    );
  }

  /**
   * @var IDataConnection[]|callable[]
   */
  protected $_connections;

  /**
   * @var IDataStore[]|callable[]
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
      if(is_callable($this->_connections[$name]))
      {
        $this->_connections[$name] = $this->_connections[$name]();
      }

      if($this->_connections[$name] instanceof IDataConnection)
      {
        return $this->_connections[$name];
      }
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
   * Add a connection to the resolver
   *
   * @param string   $name name for the connection
   * @param callable $connection
   *
   * @return $this
   */
  public function addConnectionCallable($name, callable $connection)
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
      if(is_callable($this->_datastores[$name]))
      {
        $this->_datastores[$name] = $this->_datastores[$name]();
      }

      if($this->_datastores[$name] instanceof IDataStore)
      {
        return $this->_datastores[$name];
      }
    }

    throw new DataStoreNotFoundException(
      "No data store could be found with the name '$name'", 404
    );
  }

  /**
   * Add a data store to the resolver
   *
   * @param string     $name name for the datastore
   * @param IDataStore $dataStore
   *
   * @return $this
   */
  public function addDataStore($name, IDataStore $dataStore)
  {
    $this->_datastores[$name] = $dataStore;
    return $this;
  }

  /**
   * Add a data store to the resolver
   *
   * @param string   $name name for the datastore
   * @param callable $dataStore
   *
   * @return $this
   */
  public function addDataStoreCallable($name, callable $dataStore)
  {
    $this->_datastores[$name] = $dataStore;
    return $this;
  }
}
