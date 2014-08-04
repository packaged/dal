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
}
