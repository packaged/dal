<?php
namespace Packaged\Dal;

use Packaged\Config\ConfigProviderInterface;
use Packaged\Config\Provider\Test\TestConfigProvider;
use Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException;
use Packaged\Dal\Exceptions\DalResolver\DataStoreNotFoundException;
use Packaged\Dal\FileSystem\FileSystemDataStore;

/**
 * Standard Packaged Connection Resolver
 */
class DalResolver implements IConnectionResolver
{
  /**
   * @var ConfigProviderInterface[]
   */
  protected $_config;
  protected $_confed;

  public function __construct(
    ConfigProviderInterface $connectionConfig = null,
    ConfigProviderInterface $datastoreConfig = null
  )
  {
    if($connectionConfig !== null)
    {
      $this->_config['connection'] = $connectionConfig;
    }
    else
    {
      $this->_config['connection'] = new TestConfigProvider();
    }

    if($datastoreConfig !== null)
    {
      $this->_config['datastore'] = $datastoreConfig;
    }
    else
    {
      $this->_config['datastore'] = new TestConfigProvider();
    }

    $this->_confed = ['connection' => [], 'datastore' => []];

    //Filesystem always standard
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
        return $this->_configureDSC(
          $name,
          $this->_connections[$name],
          'connection'
        );
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
    $this->_unsetConfigured('connection', $name);
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
    $this->_unsetConfigured('connection', $name);
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
        return $this->_configureDSC(
          $name,
          $this->_datastores[$name],
          'datastore'
        );
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
    $this->_unsetConfigured('datastore', $name);
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
    $this->_unsetConfigured('datastore', $name);
    $this->_datastores[$name] = $dataStore;
    return $this;
  }

  /**
   * Configure a datastore or connection
   *
   * @param        $name
   * @param        $item
   * @param string $type
   */
  protected function _configureDSC($name, $item, $type = 'connection')
  {
    //Do not configure items multiple times
    if(!isset($this->_confed[$type][$name]) && $item instanceof IConfigurable)
    {
      $item->configure($this->_config[$type]->getSection($name));
      $this->_confed[$type][$name] = true;
    }
    return $item;
  }

  /**
   * Unset the configured flag for an item to allow it to be re-configured
   *
   * @param $type
   * @param $name
   */
  protected function _unsetConfigured($type, $name)
  {
    unset($this->_confed[$type][$name]);
  }
}
