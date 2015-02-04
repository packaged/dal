<?php
namespace Cache;

use Packaged\Dal\Cache\CacheDao;
use Packaged\Dal\Cache\CacheItem;

class CacheItemTest extends \PHPUnit_Framework_TestCase
{
  public function testFromDao()
  {
    $dao = new CacheDao();
    $dao->key = 'cacheKey';
    $dao->data = 'data';

    $item = CacheItem::fromDao($dao);
    $this->assertEquals($dao->key, $item->getKey());
    $this->assertEquals($dao->data, $item->get());
  }

  public function testHydrate()
  {
    $item = new CacheItem('one');
    $item->hydrate('two');
    $this->assertEquals('two', $item->get());
    $this->assertTrue($item->exists());
    $this->assertTrue($item->isHit());
    $item->hydrate(null, false);
    $this->assertEquals(null, $item->get());
    $this->assertFalse($item->exists());
    $this->assertFalse($item->isHit());
  }
}
