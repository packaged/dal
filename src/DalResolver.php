<?php
namespace Packaged\Dal;

use Packaged\Config\ConfigProviderInterface;
use Packaged\Config\ConfigSectionInterface;
use Packaged\Config\ConfigurableInterface;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Config\Provider\Test\TestConfigProvider;
use Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException;
use Packaged\Dal\Exceptions\DalResolver\DataStoreNotFoundException;
use Packaged\Dal\Foundation\Dao;

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

    if(!$this->_config['datastore']->sectionExists('filesystem'))
    {
      $this->_config['datastore']->addSection(
        new ConfigSection(
          'filesystem',
          ['construct_class' => '\Packaged\Dal\FileSystem\FileSystemDataStore']
        )
      );
    }
  }

  /**
   * Set this DalResolver to be the resolver on all DAOs
   */
  public function boot()
  {
    Dao::setDalResolver($this);
  }

  public function shutdown()
  {
    Dao::unsetDalResolver();
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
    if(!isset($this->_connections[$name]))
    {
      $this->_connections[$name] = $this->_fromConfiguration(
        'connection',
        $name
      );
    }

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
    if(!isset($this->_datastores[$name]))
    {
      $this->_datastores[$name] = $this->_fromConfiguration('datastore', $name);
    }

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
   * Add a config section to the resolver as a connection
   *
   * @param ConfigSectionInterface $config
   */
  public function addConnectionConfig(ConfigSectionInterface $config)
  {
    $this->_config['connection']->addSection($config);
  }

  /**
   * Get a connection config
   *
   * @param $name
   *
   * @return ConfigSectionInterface
   */
  public function getConnectionConfig($name)
  {
    if($this->_config['connection']->sectionExists($name))
    {
      return $this->_config['connection']->getSection($name);
    }
    return null;
  }

  /**
   * Add a config section to the resolver as a data store
   *
   * @param ConfigSectionInterface $config
   */
  public function addDataStoreConfig(ConfigSectionInterface $config)
  {
    $this->_config['datastore']->addSection($config);
  }

  /**
   * Get a data store config
   *
   * @param $name
   *
   * @return ConfigSectionInterface
   */
  public function getDataStoreConfig($name)
  {
    if($this->_config['datastore']->sectionExists($name))
    {
      return $this->_config['datastore']->getSection($name);
    }
    return null;
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
    if(!isset($this->_confed[$type][$name]) && $item instanceof ConfigurableInterface)
    {
      if($this->_config[$type]->sectionExists($name))
      {
        $item->configure($this->_config[$type]->getSection($name));
      }
      else
      {
        $item->configure(new ConfigSection($name));
      }
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

  /**
   * Attempt to create an item from the configuration
   *
   * @param $type
   * @param $name
   *
   * @return IDataConnection|IDataStore|null|mixed
   */
  protected function _fromConfiguration($type, $name)
  {
    if($this->_config[$type]->sectionExists($name))
    {
      $section = $this->_config[$type]->getSection($name);

      $class = $section->getItem('construct_class');
      if($class)
      {
        return new $class;
      }

      $callable = $section->getItem('construct_callable');
      if(is_scalar($callable) && stristr($callable, '::'))
      {
        $callable = explode('::', $callable);
      }
      if($callable && is_callable($callable))
      {
        return $callable();
      }
    }
    return null;
  }

  /**
   * Check to see if a datastore is defined by name
   *
   * @param $name
   *
   * @return bool
   */
  public function hasDatastore($name)
  {
    if(!isset($this->_datastores[$name]))
    {
      $this->_datastores[$name] = $this->_fromConfiguration('datastore', $name);
    }

    if(isset($this->_datastores[$name]))
    {
      return true;
    }
    return false;
  }

  /**
   * Check to see if a connection is defined by name
   *
   * @param $name
   *
   * @return bool
   */
  public function hasConnection($name)
  {
    if(!isset($this->_connections[$name]))
    {
      $this->_connections[$name] = $this->_fromConfiguration(
        'connection',
        $name
      );
    }

    if(isset($this->_connections[$name]))
    {
      return true;
    }
    return false;
  }
}
