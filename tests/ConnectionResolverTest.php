<?php

class ConnectionResolverTest extends PHPUnit_Framework_TestCase
{
  public function testGetInvalidConnection()
  {
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\ConnectionResolver\ConnectionNotFoundException'
    );
    $resolver = new \Packaged\Dal\ConnectionResolver();
    $resolver->getConnection('test');
  }

  public function testSetAndGet()
  {
    $interface = '\Packaged\Dal\IDataConnection';
    $resolver  = new \Packaged\Dal\ConnectionResolver();
    $resolver->addConnection('test', $this->getMock($interface));
    $this->assertInstanceOf($interface, $resolver->getConnection('test'));
  }
}
