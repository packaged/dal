<?php
namespace Cache;

use Packaged\Dal\Cache\CacheDao;

class CacheDaoTest extends \PHPUnit_Framework_TestCase
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
