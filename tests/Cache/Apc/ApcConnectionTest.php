<?php
namespace Packaged\Dal\Tests\Cache\Apc;

use Packaged\Dal\Cache\Apc\ApcConnection;
use Packaged\Dal\Cache\CacheItem;
use Packaged\Dal\Cache\ICacheItem;

class ApcConnectionTest extends \PHPUnit_Framework_TestCase
{
  protected function setUp()
  {
    if(!((extension_loaded('apc') || extension_loaded('apcu'))
      && ini_get('apc.enabled'))
    )
    {
      $this->markTestSkipped('The APC extension is not available.');
    }
  }

  public function testStorage()
  {
    $conn = new ApcConnection();

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

    $conn->disconnect();
  }
}
