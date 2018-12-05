<?php
namespace Tests\Helpers;

use Packaged\Dal\Helpers\Phid;

class PhidTest extends \PHPUnit_Framework_TestCase
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

  public function generateProvider()
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

class RandomClassOne
{
}

class MyTestClass
{
}
