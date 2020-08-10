<?php
namespace Packaged\Dal\Tests\Helpers;

use Packaged\Dal\Helpers\ChronologicalKey;
use PHPUnit\Framework\TestCase;

class ChronologicalKeyTest extends TestCase
{
  public function testGenerate()
  {
    $custom = ChronologicalKey::generate(1234597);
    $now = ChronologicalKey::generate();
    $this->assertLessThan($now, $custom);
    sleep(1);
    $this->assertGreaterThan($now, ChronologicalKey::generate());
  }
}
