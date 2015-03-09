<?php

use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\DalException;

class ConnectionResolverTest extends PHPUnit_Framework_TestCase
{
  public function testGetInvalidConnection()
  {
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException'
    );
    $resolver = new \Packaged\Dal\DalResolver();
    $resolver->getConnection('test');
  }

  public function testSetAndGetConnection()
  {
    $interface = '\Packaged\Dal\IDataConnection';
    $resolver = new \Packaged\Dal\DalResolver();
    $resolver->addConnection('test', $this->getMock($interface));
    $this->assertInstanceOf($interface, $resolver->getConnection('test'));
  }

  public function testGetInvalidDataStore()
  {
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DalResolver\DataStoreNotFoundException'
    );
    $resolver = new \Packaged\Dal\DalResolver();
    $resolver->getDataStore('test');
  }

  public function testSetAndGetDataStore()
  {
    $interface = '\Packaged\Dal\IDataStore';
    $resolver = new \Packaged\Dal\DalResolver();
    $resolver->addDataStore('test', $this->getMock($interface));
    $this->assertInstanceOf($interface, $resolver->getDataStore('test'));
  }

  public function testCallable()
  {
    $resolver = new \Packaged\Dal\DalResolver();

    $resolver->addConnectionCallable(
      'test',
      function ()
      {
        return $this->getMock('\Packaged\Dal\IDataConnection');
      }
    );
    $this->assertInstanceOf(
      '\Packaged\Dal\IDataConnection',
      $resolver->getConnection('test')
    );
    //Second call for cache
    $this->assertInstanceOf(
      '\Packaged\Dal\IDataConnection',
      $resolver->getConnection('test')
    );

    $resolver->addDataStoreCallable(
      'test',
      function ()
      {
        return $this->getMock('\Packaged\Dal\IDataStore');
      }
    );
    $this->assertInstanceOf(
      '\Packaged\Dal\IDataStore',
      $resolver->getDataStore('test')
    );
    //Second call for cache
    $this->assertInstanceOf(
      '\Packaged\Dal\IDataStore',
      $resolver->getDataStore('test')
    );
  }

  public function testInvalidConnectionCallback()
  {
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException'
    );
    $resolver = new \Packaged\Dal\DalResolver();
    $resolver->addConnectionCallable(
      'test',
      function ()
      {
        return 'broken';
      }
    );
    $resolver->getConnection('test');
  }

  public function testInvalidDataStoreCallback()
  {
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DalResolver\DataStoreNotFoundException'
    );
    $resolver = new \Packaged\Dal\DalResolver();
    $resolver->addDataStoreCallable(
      'test',
      function ()
      {
        return 'broken';
      }
    );
    $resolver->getDataStore('test');
  }

  public function testConfigurations()
  {
    $connectionConfig = new \Packaged\Config\Provider\Ini\IniConfigProvider(
      build_path(__DIR__, 'resources', 'connections.ini')
    );
    $datastoreConfig = new \Packaged\Config\Provider\Ini\IniConfigProvider(
      build_path(__DIR__, 'resources', 'datastores.ini')
    );
    $resolver = new \Packaged\Dal\DalResolver(
      $connectionConfig,
      $datastoreConfig
    );

    $this->assertFalse($resolver->hasConnection('conX'));
    $this->assertTrue($resolver->hasConnection('con1'));

    $this->assertFalse($resolver->hasDatastore('ds2'));
    $this->assertFalse($resolver->hasDatastore('ds1'));
    $this->assertTrue($resolver->hasDatastore('qlds'));
    $this->assertTrue($resolver->hasDatastore('filesystem'));

    $connection = $resolver->getConnection('con1');
    /**
     * @var $connection ConfigurableConnection
     */
    $this->assertEquals(
      'Connection Test',
      $connection->getConfig()->getItem('name')
    );

    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException'
    );
    $resolver->getConnection('con2');
  }

  public function testAddConnectionConfig()
  {
    $config = new \Packaged\Config\Provider\ConfigSection('connection_test');
    $config->addItem('construct_class', ConfigurableConnection::class);
    $config->addItem('host', '127.0.0.1');

    $resolver = new \Packaged\Dal\DalResolver();
    $this->assertNull($resolver->getConnectionConfig('invalid_connection'));

    $resolver->addConnectionConfig($config);

    $this->assertSame(
      $config,
      $resolver->getConnectionConfig('connection_test')
    );

    /**
     * @var $connection ConfigurableConnection
     */
    $connection = $resolver->getConnection('connection_test');
    $this->assertInstanceOf(ConfigurableConnection::class, $connection);
    $this->assertEquals('127.0.0.1', $connection->getConfig()->getItem('host'));
  }

  public function testAddDataStoreConfig()
  {
    $config = new \Packaged\Config\Provider\ConfigSection('datastore_test');
    $config->addItem('construct_class', ConfigurableDataStore::class);
    $unique = uniqid();
    $config->addItem('unique', $unique);

    $resolver = new \Packaged\Dal\DalResolver();
    $this->assertNull($resolver->getDataStoreConfig('invalid_datastore'));

    $resolver->addDataStoreConfig($config);

    $this->assertSame(
      $config,
      $resolver->getDataStoreConfig('datastore_test')
    );

    /**
     * @var $dataStore ConfigurableDataStore
     */
    $dataStore = $resolver->getDataStore('datastore_test');
    $this->assertInstanceOf(ConfigurableDataStore::class, $dataStore);
    $this->assertEquals($unique, $dataStore->getConfig()->getItem('unique'));
  }

  public function testPerformanceMetrics()
  {
    $dal = new DalResolver();
    $this->assertFalse($dal->isCollectingPerformanceMetrics());
    $dal->enablePerformanceMetrics();
    $this->assertTrue($dal->isCollectingPerformanceMetrics());
    $dal->disablePerformanceMetrics();
    $this->assertFalse($dal->isCollectingPerformanceMetrics());
    $dal->enablePerformanceMetrics();

    $id = $dal->startPerformanceMetric('test', DalResolver::MODE_READ, 'SLEEP');
    sleep(1);
    $dal->closePerformanceMetric($id);

    $perfData = $dal->getPerformanceMetrics();
    $this->assertCount(1, $perfData);
    $perfData = head($perfData);
    $this->assertEquals('test', $perfData['c']);
    $this->assertEquals('SLEEP', $perfData['q']);
    $this->assertEquals(DalResolver::MODE_READ, $perfData['m']);
    $this->assertGreaterThan(1000, $perfData['t']);

    $this->setExpectedException(
      DalException::class,
      "You cannot close performance metrics that are not open"
    );
    $dal->closePerformanceMetric($id);
  }
}

class ConfigurableConnection
  implements \Packaged\Dal\IDataConnection,
             \Packaged\Config\ConfigurableInterface
{
  use \Packaged\Config\ConfigurableTrait;

  public static function create()
  {
    return new static;
  }

  public function getConfig()
  {
    return $this->_config();
  }

  /**
   * Open the connection
   *
   * @return static
   *
   * @throws \Packaged\Dal\Exceptions\Connection\ConnectionException
   */
  public function connect()
  {
    return $this;
  }

  /**
   * Check to see if the connection is already open
   *
   * @return bool
   */
  public function isConnected()
  {
    return true;
  }

  /**
   * Disconnect the open connection
   *
   * @return static
   *
   * @throws \Packaged\Dal\Exceptions\Connection\ConnectionException
   */
  public function disconnect()
  {
    return $this;
  }
}

class ConfigurableDataStore implements \Packaged\Dal\IDataStore,
                                       \Packaged\Config\ConfigurableInterface
{
  use \Packaged\Config\ConfigurableTrait;

  public function getConfig()
  {
    return $this->_config();
  }

  /**
   * Save a DAO to the data store
   *
   * @param \Packaged\Dal\IDao $dao
   *
   * @return array of changed properties
   *
   * @throws \Packaged\Dal\Exceptions\DataStore\DataStoreException
   */
  public function save(\Packaged\Dal\IDao $dao)
  {
  }

  /**
   * Hydrate a DAO from the data store
   *
   * @param \Packaged\Dal\IDao $dao
   *
   * @return \Packaged\Dal\IDao Loaded DAO
   *
   * @throws \Packaged\Dal\Exceptions\DataStore\DaoNotFoundException
   */
  public function load(\Packaged\Dal\IDao $dao)
  {
  }

  /**
   * Delete the DAO from the data store
   *
   * @param \Packaged\Dal\IDao $dao
   *
   * @return \Packaged\Dal\IDao
   *
   * @throws \Packaged\Dal\Exceptions\DataStore\DataStoreException
   */
  public function delete(\Packaged\Dal\IDao $dao)
  {
  }

  /**
   * Does the object exist in the data store
   *
   * @param \Packaged\Dal\IDao $dao
   *
   * @return bool
   */
  public function exists(\Packaged\Dal\IDao $dao)
  {
  }
}
