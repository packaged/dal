<?php
namespace Traits;

use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Traits\ConfigurableTrait;

class ConfigurableTraitTest extends \PHPUnit_Framework_TestCase
{
  public function testTrait()
  {
    $config = new ConfigSection('abstract', ['name' => 'test']);
    $mock   = new MockConfigurableTrait();
    $mock->configure($config);
    $this->assertSame($config, $mock->config());
  }
}

class MockConfigurableTrait
{
  use ConfigurableTrait;

  public function config()
  {
    return $this->_config();
  }
}
