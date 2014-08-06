<?php

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
    $resolver  = new \Packaged\Dal\DalResolver();
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
    $resolver  = new \Packaged\Dal\DalResolver();
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
    $datastoreConfig  = new \Packaged\Config\Provider\Ini\IniConfigProvider(
      build_path(__DIR__, 'resources', 'datastores.ini')
    );
    $resolver         = new \Packaged\Dal\DalResolver(
      $connectionConfig,
      $datastoreConfig
    );

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
}

class ConfigurableConnection
  implements \Packaged\Dal\IDataConnection, \Packaged\Dal\IConfigurable
{
  use \Packaged\Dal\Traits\ConfigurableTrait;

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
