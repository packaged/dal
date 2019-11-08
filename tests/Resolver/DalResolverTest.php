<?php

namespace Packaged\Dal\Tests\Resolver;

use Packaged\Config\Provider\ConfigProvider;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Config\Provider\Ini\IniConfigProvider;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\DalException;
use Packaged\Helpers\Path;
use PHPUnit_Framework_TestCase;
use Packaged\Dal\Tests\Connection\ConfigurableConnection;
use Packaged\Dal\Tests\DataStore\ConfigurableDataStore;

class ConnectionResolverTest extends PHPUnit_Framework_TestCase
{
  public function testGetInvalidConnection()
  {
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException'
    );
    $resolver = new DalResolver();
    $resolver->getConnection('test');
  }

  public function testSetAndGetConnection()
  {
    $interface = '\Packaged\Dal\IDataConnection';
    $resolver = new DalResolver();
    $resolver->addConnection('test', $this->getMock($interface));
    $this->assertInstanceOf($interface, $resolver->getConnection('test'));
  }

  public function testGetInvalidDataStore()
  {
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DalResolver\DataStoreNotFoundException'
    );
    $resolver = new DalResolver();
    $resolver->getDataStore('test');
  }

  public function testSetAndGetDataStore()
  {
    $interface = '\Packaged\Dal\IDataStore';
    $resolver = new DalResolver();
    $resolver->addDataStore('test', $this->getMock($interface));
    $this->assertInstanceOf($interface, $resolver->getDataStore('test'));
  }

  public function testCallable()
  {
    $resolver = new DalResolver();

    $resolver->addConnectionCallable(
      'test',
      function () {
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
      function () {
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
    $resolver = new DalResolver();
    $resolver->addConnectionCallable(
      'test',
      function () {
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
    $resolver = new DalResolver();
    $resolver->addDataStoreCallable(
      'test',
      function () {
        return 'broken';
      }
    );
    $resolver->getDataStore('test');
  }

  public function testConfigurations()
  {
    $connectionConfig = new IniConfigProvider(
      Path::system(__DIR__, '../resources', 'connections.ini')
    );
    $datastoreConfig = new IniConfigProvider(
      Path::system(__DIR__, '../resources', 'datastores.ini')
    );
    $resolver = new DalResolver($connectionConfig, $datastoreConfig);

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
    $config = new ConfigSection('connection_test');
    $config->addItem('construct_class', ConfigurableConnection::class);
    $config->addItem('host', '127.0.0.1');

    $resolver = new DalResolver();
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
    $config = new ConfigSection('datastore_test');
    $config->addItem('construct_class', ConfigurableDataStore::class);
    $unique = uniqid();
    $config->addItem('unique', $unique);

    $resolver = new DalResolver();
    $this->assertNull($resolver->getDataStoreConfig('invalid_datastore'));

    $resolver->addDataStoreConfig($config);

    $this->assertSame(
      $config,
      $resolver->getDataStoreConfig('datastore_test')
    );

    $config2 = new ConfigSection('datastore_test');
    $config2->addItem('construct_class', ConfigurableDataStore::class);
    $unique = uniqid();
    $config2->addItem('unique', $unique);
    $resolver->addDataStoreConfig($config2);

    $this->assertNotSame(
      $config,
      $resolver->getDataStoreConfig('datastore_test')
    );
    $this->assertSame(
      $config2,
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
    $perfData = reset($perfData);
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

  public function testSlowQueries()
  {
    $config = new ConfigProvider();
    $config->addSection(
      new ConfigSection("log", ["slow_queries" => 400])
    );
    $dal = new DalResolver(null, null, $config);
    $this->assertFalse($dal->isCollectingPerformanceMetrics());
    $this->assertEmpty($dal->getPerformanceMetrics());

    //Check fast queries are not logged
    $id = $dal->startPerformanceMetric(
      'test',
      DalResolver::MODE_READ,
      'USLEEP'
    );
    usleep(100);
    $dal->closePerformanceMetric($id);
    $this->assertEmpty($dal->getPerformanceMetrics());

    //Check slow queries are logged
    $id = $dal->startPerformanceMetric('test', DalResolver::MODE_READ, 'SLEEP');
    sleep(1);
    $dal->closePerformanceMetric($id);
    $perfData = $dal->getPerformanceMetrics();
    $this->assertCount(1, $perfData);
    $perfData = reset($perfData);
    $this->assertEquals('test', $perfData['c']);
    $this->assertEquals('SLEEP', $perfData['q']);
  }
}
