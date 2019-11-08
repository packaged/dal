<?php
namespace Packaged\Dal\Tests\DataTypes;

use Packaged\Dal\DataTypes\Counter;

class CounterTest extends \PHPUnit_Framework_TestCase
{
  public function testCounter()
  {
    $counter = new Counter(25);
    $this->assertFalse($counter->hasChanged());

    $counter->increment(100);
    $counter->decrement(50);
    $this->assertEquals(75, $counter->calculated());
    $this->assertEquals(25, $counter->current());
    $this->assertEquals(25, (string)$counter);
    $this->assertFalse($counter->isFixedValue());
    $this->assertTrue($counter->hasChanged());

    $counter->setValue(5);
    $this->assertTrue($counter->isFixedValue());
    $this->assertTrue($counter->hasChanged());

    $counter->increment(5);
    $this->assertEquals(5, (string)$counter);
  }
}
