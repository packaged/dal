<?php
namespace Packaged\Dal\Tests\Cache\Memcache;

use Packaged\Dal\Cache\CacheItem;
use Packaged\Dal\Cache\Memcache\MemcacheConnection;

/**
 * @requires extension memcache
 */
class MemcacheConnectionTest extends \PHPUnit_Framework_TestCase
{
  public function testConnection()
  {
    $connection = new MemcacheConnection();
    $this->assertFalse($connection->isConnected());
    $connection->connect();
    $this->assertTrue($connection->isConnected());
    $connection->disconnect();
    $this->assertFalse($connection->isConnected());
  }

  public function testItems()
  {
    $connection = new MemcacheConnection();
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
