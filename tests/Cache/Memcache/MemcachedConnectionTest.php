<?php
namespace Cache\Memcache;

use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Cache\CacheItem;
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

  public function testPersistent()
  {
    $connection = new MemcachedConnection();
    $config = new ConfigSection();
    $config->addItem('pool_name', 'test_pool');
    $connection->configure($config);
    $this->assertFalse($connection->isConnected());
    $connection->connect();
    $this->assertTrue($connection->isConnected());
    $connection->disconnect();
    $this->assertFalse($connection->isConnected());
    $connection->connect();
    $this->assertTrue($connection->isConnected());
  }

  public function testItems()
  {
    $connection = new MemcachedConnection();
    $connection->connect();

    $item = new CacheItem('tester', 'bc');
    $connection->saveItem($item);

    $pull = $connection->getItem('tester');
    $this->assertTrue($pull->exists());
    $this->assertEquals('bc', $pull->get());

    $connection->deleteKey('tester');

    $pull = $connection->getItem('tester');
    $this->assertFalse($pull->exists());

    $connection->saveItem($item, 10);

    $pull = $connection->getItem('tester');
    $this->assertTrue($pull->exists());

    $connection->clear();

    $pull = $connection->getItem('tester');
    $this->assertFalse($pull->exists());

    $connection->disconnect();
  }
}
