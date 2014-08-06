<?php
namespace Packaged\Dal\Traits;

use Packaged\Config\ConfigSectionInterface;

trait ConfigurableTrait
{
  /**
   * @var ConfigSectionInterface
   */
  protected $_configuration;

  /**
   * Configure the data connection
   *
   * @param ConfigSectionInterface $configuration
   *
   * @return static
   */
  public function configure(ConfigSectionInterface $configuration)
  {
    $this->_configuration = $configuration;
  }

  /**
   * Retrieve the configuration
   *
   * @return ConfigSectionInterface
   */
  protected function _config()
  {
    return $this->_configuration;
  }
}
