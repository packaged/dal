<?php
namespace Packaged\Dal;

use Packaged\Config\ConfigProviderInterface;
use Packaged\Config\ConfigSectionInterface;
use Packaged\Config\ConfigurableInterface;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Config\Provider\Test\TestConfigProvider;
use Packaged\Dal\Exceptions\DalException;
use Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException;
use Packaged\Dal\Exceptions\DalResolver\DataStoreNotFoundException;
use Packaged\Dal\Foundation\Dao;
use Packaged\Log\Log;

/**
 * Standard Packaged Connection Resolver
 */
class DalResolver implements IConnectionResolver
{
  const TYPE_CONNECTION = 'connection';
  const TYPE_DATASTORE = 'datastore';
  const CONFIG_KEY = 'config';

  /**
   * @var ConfigProviderInterface[]
   */
  protected $_config;
  protected $_confed;
  protected $_perfData = [];
  protected $_currentPerf = [];
  protected $_storePerformanceData = false;

  const MODE_READ = 'r';
  const MODE_WRITE = 'w';

  public function __construct(
    ConfigProviderInterface $connectionConfig = null,
    ConfigProviderInterface $datastoreConfig = null,
    ConfigProviderInterface $dalConfig = null
  )
  {
    if($connectionConfig !== null)
    {
      $this->_config[self::TYPE_CONNECTION] = $connectionConfig;
    }
    else
    {
      $this->_config[self::TYPE_CONNECTION] = new TestConfigProvider();
    }

    if($datastoreConfig !== null)
    {
      $this->_config[self::TYPE_DATASTORE] = $datastoreConfig;
    }
    else
    {
      $this->_config[self::TYPE_DATASTORE] = new TestConfigProvider();
    }

    if($dalConfig !== null)
    {
      $this->_config[self::CONFIG_KEY] = $dalConfig;
    }
    else
    {
      $this->_config[self::CONFIG_KEY] = new TestConfigProvider();
    }

    $this->_confed = [self::TYPE_CONNECTION => [], self::TYPE_DATASTORE => []];

    if(!$this->_config[self::TYPE_DATASTORE]->sectionExists('filesystem'))
    {
      $this->_config[self::TYPE_DATASTORE]->addSection(
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

  protected $_objectCache = [
    self::TYPE_CONNECTION => [],
    self::TYPE_DATASTORE  => [],
  ];

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
    if(!isset($this->_objectCache[self::TYPE_CONNECTION][$name]))
    {
      $this->_objectCache[self::TYPE_CONNECTION][$name] = $this->_fromConfiguration(
        self::TYPE_CONNECTION,
        $name
      );
    }

    if(isset($this->_objectCache[self::TYPE_CONNECTION][$name]))
    {
      if(is_callable($this->_objectCache[self::TYPE_CONNECTION][$name]))
      {
        $this->_objectCache[self::TYPE_CONNECTION][$name]
          = $this->_objectCache[self::TYPE_CONNECTION][$name]();
      }

      if($this->_objectCache[self::TYPE_CONNECTION][$name] instanceof IResolverAware)
      {
        $this->_objectCache[self::TYPE_CONNECTION][$name]
          = $this->_objectCache[self::TYPE_CONNECTION][$name]
          ->setResolver($this);
      }

      if($this->_objectCache[self::TYPE_CONNECTION][$name] instanceof IDataConnection)
      {
        return $this->_configureDSC(
          $name,
          $this->_objectCache[self::TYPE_CONNECTION][$name],
          self::TYPE_CONNECTION
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
    $this->_unsetConfigured(self::TYPE_CONNECTION, $name);
    $this->_objectCache[self::TYPE_CONNECTION][$name] = $connection;
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
    $this->_unsetConfigured(self::TYPE_CONNECTION, $name);
    $this->_objectCache[self::TYPE_CONNECTION][$name] = $connection;
    return $this;
  }

  /**
   * Add a config section to the resolver as a connection
   *
   * @param ConfigSectionInterface $config
   */
  public function addConnectionConfig(ConfigSectionInterface $config)
  {
    $this->_unsetConfigured(self::TYPE_CONNECTION, $config->getName());
    $this->_config[self::TYPE_CONNECTION]->setSection($config);
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
    if($this->_config[self::TYPE_CONNECTION]->sectionExists($name))
    {
      return $this->_config[self::TYPE_CONNECTION]->getSection($name);
    }
    return null;
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
    if(!isset($this->_objectCache[self::TYPE_CONNECTION][$name]))
    {
      $this->_objectCache[self::TYPE_CONNECTION][$name] = $this->_fromConfiguration(
        self::TYPE_CONNECTION,
        $name
      );
    }

    if(isset($this->_objectCache[self::TYPE_CONNECTION][$name]))
    {
      return true;
    }
    return false;
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
    if(!isset($this->_objectCache[self::TYPE_DATASTORE][$name]))
    {
      $this->_objectCache[self::TYPE_DATASTORE][$name] = $this->_fromConfiguration(
        self::TYPE_DATASTORE,
        $name
      );
    }

    if(isset($this->_objectCache[self::TYPE_DATASTORE][$name]))
    {
      if(is_callable($this->_objectCache[self::TYPE_DATASTORE][$name]))
      {
        $this->_objectCache[self::TYPE_DATASTORE][$name] = $this->_objectCache[self::TYPE_DATASTORE][$name](
        );
      }

      if($this->_objectCache[self::TYPE_DATASTORE][$name] instanceof IDataStore)
      {
        return $this->_configureDSC(
          $name,
          $this->_objectCache[self::TYPE_DATASTORE][$name],
          self::TYPE_DATASTORE
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
    $this->_unsetConfigured(self::TYPE_DATASTORE, $name);
    $this->_objectCache[self::TYPE_DATASTORE][$name] = $dataStore;
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
    $this->_unsetConfigured(self::TYPE_DATASTORE, $name);
    $this->_objectCache[self::TYPE_DATASTORE][$name] = $dataStore;
    return $this;
  }

  /**
   * Add a config section to the resolver as a data store
   *
   * @param ConfigSectionInterface $config
   */
  public function addDataStoreConfig(ConfigSectionInterface $config)
  {
    $this->_unsetConfigured(self::TYPE_DATASTORE, $config->getName());
    $this->_config[self::TYPE_DATASTORE]->setSection($config);
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
    if($this->_config[self::TYPE_DATASTORE]->sectionExists($name))
    {
      return $this->_config[self::TYPE_DATASTORE]->getSection($name);
    }
    return null;
  }

  /**
   * Get DAL config
   *
   * @param      $section
   * @param      $item
   * @param null $default
   *
   * @return mixed
   */
  public function getConfigItem($section, $item, $default = null)
  {
    return $this->_config[self::CONFIG_KEY]->getItem($section, $item, $default);
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
    if(!isset($this->_objectCache[self::TYPE_DATASTORE][$name]))
    {
      $this->_objectCache[self::TYPE_DATASTORE][$name] = $this->_fromConfiguration(
        self::TYPE_DATASTORE,
        $name
      );
    }

    if(isset($this->_objectCache[self::TYPE_DATASTORE][$name]))
    {
      return true;
    }
    return false;
  }

  /**
   * Configure a datastore or connection
   *
   * @param        $name
   * @param        $item
   * @param string $type
   */
  protected function _configureDSC($name, $item, $type)
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
    unset($this->_objectCache[$type][$name]);
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
        return new $class();
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

  public function enablePerformanceMetrics()
  {
    $this->_storePerformanceData = true;
    return $this;
  }

  public function disablePerformanceMetrics()
  {
    $this->_storePerformanceData = false;
    return $this;
  }

  public function isCollectingPerformanceMetrics()
  {
    return (bool)$this->_storePerformanceData;
  }

  public function startPerformanceMetric($connection, $mode, $query = null)
  {
    $uniqueId = uniqid('', true);
    $this->_currentPerf[$uniqueId] = [
      'c' => $connection,
      'm' => $mode,
      'q' => $query,
      's' => microtime(true),
    ];
    return $uniqueId;
  }

  public function closePerformanceMetric($uniqueid)
  {

    //Slow Query Threshold in ms
    $slowQueryTime = $this->getConfigItem("log", "slow_queries", null);
    if($this->_storePerformanceData || $slowQueryTime !== null)
    {
      if(isset($this->_currentPerf[$uniqueid]))
      {
        $met = $this->_currentPerf[$uniqueid];
        $time = microtime(true) - $met['s'];

        if($this->_storePerformanceData || ($time * 1000) > $slowQueryTime)
        {
          $this->writePerformanceMetric($met['c'], $time, $met['m'], $met['q']);
        }
      }
      else
      {
        throw new DalException(
          "You cannot close performance metrics that are not open"
        );
      }
    }
    unset($this->_currentPerf[$uniqueid]);
    return true;
  }

  public function writePerformanceMetric(
    $connection, $processTime, $mode = self::MODE_READ, $query = null
  )
  {
    if(!is_scalar($connection))
    {
      $conn = array_search(
        $connection,
        (array)$this->_objectCache[self::TYPE_CONNECTION],
        true
      );
      $connection = !$conn ? get_class($connection) : $conn;
    }

    $data = [
      't' => $processTime * 1000,
      'q' => $query,
      'c' => $connection,
      'm' => $mode,
    ];

    $log = $this->getConfigItem("log", "location", 'memory');
    if($log == 'error_log')
    {
      Log::debug("DAL-PERF: " . json_encode($data));
    }
    else if($log == 'memory')
    {
      $this->_perfData[] = $data;
    }
    else if(file_exists($log))
    {
      file_put_contents($log, json_encode($data) . "\n", FILE_APPEND);
    }

    return $this;
  }

  public function getPerformanceMetrics()
  {
    return $this->_perfData;
  }
}
