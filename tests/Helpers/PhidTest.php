<?php
namespace Packaged\Dal\Tests\Helpers;

use Packaged\Dal\Helpers\Phid;
use Packaged\Dal\Tests\Helpers\Mocks\MyTestClass;
use Packaged\Dal\Tests\Helpers\Mocks\RandomClassOne;
use PHPUnit\Framework\TestCase;

class PhidTest extends TestCase
{
  /**
   * @dataProvider generateProvider
   *
   * @param      $starting
   * @param      $class
   * @param null $append
   * @param null $prefix
   */
  public function testGenerate(
    $starting, $class, $append = null, $prefix = null
  )
  {
    $this->assertStringStartsWith(
      $starting,
      Phid::generate($class, $append, $prefix)
    );
  }

  public static function generateProvider()
  {
    return [
      ['RCO:PHID', new RandomClassOne()],
      ['MTC:PHID', new MyTestClass()],
      ['MTC:PHID:BT', new MyTestClass(), 'BrookeTest'],
      ['XYZ:PHID', new MyTestClass(), '', 'XYZ'],
      ['Xyala:PHID', new MyTestClass(), '', 'Xyala'],
      ['Xya:PHID:PA', new MyTestClass(), 'PostAppend', 'Xya'],
    ];
  }

  public function testEntropy()
  {
    $this->assertLessThan(25, strlen(Phid::generate(new RandomClassOne())));
    $this->assertGreaterThan(
      25,
      strlen(Phid::generate(new RandomClassOne(), null, null, true))
    );
  }
}
