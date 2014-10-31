<?php
namespace Helpers;

use Packaged\Dal\Helpers\ChronologicalKey;

class ChronologicalKeyTest extends \PHPUnit_Framework_TestCase
{
  public function testGenerate()
  {
    $custom = ChronologicalKey::generate(1234597);
    $now    = ChronologicalKey::generate();
    $this->assertLessThan($now, $custom);
    sleep(1);
    $this->assertGreaterThan($now, ChronologicalKey::generate());
  }
}