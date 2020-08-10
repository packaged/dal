<?php
namespace Packaged\Dal\Tests\Cache\Ephemeral;

use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Cache\CacheItem;
use Packaged\Dal\Cache\Ephemeral\EphemeralConnection;
use Packaged\Dal\Cache\ICacheItem;
use PHPUnit\Framework\TestCase;

class EphemeralConnectionTest extends TestCase
{
  public function testStorage()
  {
    $conn = new EphemeralConnection();
    $conn->configure(new ConfigSection('ephemeral', ['pool_name' => 'epher']));
    $conn->disconnect();

    $this->assertFalse($conn->isConnected());
    $conn->connect();
    $this->assertTrue($conn->isConnected());

    $conn->clear();

    $items = $conn->getItems(['abc', 'def']);
    $item = $items['abc'];
    /**
     * @var $item ICacheItem
     */
    $this->assertFalse($item->exists());
    $this->assertEquals('abc', $item->getKey());
    $this->assertNull($item->get());

    //Save
    $itm = new CacheItem('abc', '123');
    $itm2 = new CacheItem('def', '321');
    $itm3 = new CacheItem('sdr', 'jkfh');
    $conn->saveItems([$itm, $itm2, $itm3]);

    $item = $conn->getItem('abc');
    $this->assertTrue($item->exists());
    $this->assertEquals('abc', $item->getKey());
    $this->assertEquals('123', $item->get());

    $item = $conn->getItem('def');
    $this->assertTrue($item->exists());

    //Delete
    $conn->deleteItems(['xyz', 'abc', new CacheItem('def')]);

    $item = $conn->getItem('def');
    $this->assertFalse($item->exists());

    $item = $conn->getItem('abc');
    $this->assertFalse($item->exists());
    $this->assertEquals('abc', $item->getKey());
    $this->assertNull($item->get());

    //Clear
    $item = $conn->getItem('sdr');
    $this->assertTrue($item->exists());
    $conn->clear();
    $item = $conn->getItem('sdr');
    $this->assertFalse($item->exists());

    $this->assertTrue($conn->isConnected());
    $conn->disconnect();
    $this->assertFalse($conn->isConnected());
  }
}
