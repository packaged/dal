<?php
namespace Packaged\Dal\Tests\Cache;

use Packaged\Dal\Cache\CacheDao;
use PHPUnit\Framework\TestCase;

class CacheDaoTest extends TestCase
{
  public function testTtl()
  {
    $dao = new CacheDao();
    $dao->key = 123;
    $this->assertEquals(123, $dao->getId());
    $dao->setTtl(15);
    $this->assertEquals(15, $dao->getTtl());
  }
}
