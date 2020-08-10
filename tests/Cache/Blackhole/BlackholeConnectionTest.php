<?php
namespace Packaged\Dal\Tests\Cache\Blackhole;

use Packaged\Dal\Cache\Blackhole\BlackholeConnection;
use PHPUnit\Framework\TestCase;

class BlackholeConnectionTest extends TestCase
{
  public function testDoesNothing()
  {
    $connection = new BlackholeConnection();
    $connection->connect();
    $connection->disconnect();
    $this->assertTrue($connection->isConnected());
    $this->assertTrue($connection->deleteKey('few'));
    $item = $connection->getItem('one');
    $this->assertFalse($item->exists());
    $this->assertNull($item->get());
    $this->assertEquals('one', $item->getKey());
    $connection->clear();
    $this->assertTrue($connection->saveItem($item, 10));
  }
}
