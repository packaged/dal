<?php
namespace Packaged\Dal\Tests\Connection;

class ConfigurableConnection
  implements \Packaged\Dal\IDataConnection,
             \Packaged\Config\ConfigurableInterface
{
  use \Packaged\Config\ConfigurableTrait;

  public static function create()
  {
    return new static();
  }

  public function getConfig()
  {
    return $this->_config();
  }

  /**
   * Open the connection
   *
   * @return static
   *
   * @throws \Packaged\Dal\Exceptions\Connection\ConnectionException
   */
  public function connect()
  {
    return $this;
  }

  /**
   * Check to see if the connection is already open
   *
   * @return bool
   */
  public function isConnected()
  {
    return true;
  }

  /**
   * Disconnect the open connection
   *
   * @return static
   *
   * @throws \Packaged\Dal\Exceptions\Connection\ConnectionException
   */
  public function disconnect()
  {
    return $this;
  }
}
