<?php
namespace Packaged\Dal\Tests\DataTypes;

use Packaged\Dal\DataTypes\UniqueList;

class UniqueListTest extends \PHPUnit_Framework_TestCase
{
  public function testUniqueList()
  {
    $counter = new UniqueList(['two', 'one']);
    $this->assertFalse($counter->hasChanged());
    $this->assertFalse($counter->hasFixedValue());
    $this->assertEquals(0, count(array_diff($counter->calculated(), ['one', 'two'])));

    $counter->add('one');
    $this->assertFalse($counter->hasChanged());
    $this->assertFalse($counter->hasFixedValue());
    $this->assertEquals(0, count(array_diff($counter->calculated(), ['one', 'two'])));

    $counter->remove('three');
    $this->assertFalse($counter->hasChanged());
    $this->assertFalse($counter->hasFixedValue());
    $this->assertEquals(0, count(array_diff($counter->calculated(), ['one', 'two'])));

    $counter->add('three');
    $this->assertTrue($counter->hasChanged());
    $this->assertFalse($counter->hasFixedValue());
    $this->assertEquals(0, count(array_diff($counter->calculated(), ['one', 'two', 'three'])));

    $counter->remove('one');
    $this->assertTrue($counter->hasChanged());
    $this->assertFalse($counter->hasFixedValue());
    $this->assertEquals(0, count(array_diff($counter->calculated(), ['two', 'three'])));

    $counter->setValue(['four', 'five']);
    $this->assertTrue($counter->hasFixedValue());
    $this->assertTrue($counter->hasChanged());
    $this->assertEquals(0, count(array_diff($counter->calculated(), ['four', 'five'])));
  }
}
