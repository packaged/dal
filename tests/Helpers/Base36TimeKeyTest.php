<?php
namespace Helpers;

use Packaged\Dal\Helpers\Base36TimeKey;

class Base36TimeKeyTest extends \PHPUnit_Framework_TestCase
{
  public function testGenerate()
  {
    Base36TimeKey::generate(1234597);
    Base36TimeKey::generate(microtime(true));
    Base36TimeKey::generate();
    echo Base36TimeKey::generate();
    echo " - " . strlen(Base36TimeKey::generate());
    for($x = 0; $x < 10; $x++)
    {
      $found = [];
      $limit = 100000;
      for($i = 0; $i < $limit; $i++)
      {
        $found[] = Base36TimeKey::generate() . "\n";
      }
      $generated = count($found);
      $unique = count(array_unique($found));
      //One Duplicate is an acceptable rate
      $this->assertLessThan(2, $generated - $unique);
    }
  }
}
