<?php
namespace Cache\Memcache;

use Packaged\Dal\Cache\Memcache\MemcachedConnection;

class MemcachedConnectionTest extends \PHPUnit_Framework_TestCase
{
  public function testConnection()
  {
    $connection = new MemcachedConnection();
    $this->assertFalse($connection->isConnected());
    $connection->connect();
    $this->assertTrue($connection->isConnected());
    $connection->disconnect();
    $this->assertFalse($connection->isConnected());
  }
}
