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
}
