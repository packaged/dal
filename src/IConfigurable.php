<?php
namespace Packaged\Dal;

use Packaged\Config\ConfigSectionInterface;

interface IConfigurable
{
  /**
   * Configure the data connection
   *
   * @param ConfigSectionInterface $configuration
   *
   * @return static
   */
  public function configure(ConfigSectionInterface $configuration);
}
