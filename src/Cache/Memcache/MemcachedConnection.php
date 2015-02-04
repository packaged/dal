<?php
namespace Packaged\Dal\Cache\Memcache;

class MemcachedConnection extends MemcacheConnection
{
  /**
   * @var \Memcached
   */
  protected $_connection;

  protected function _newConnection()
  {
    return new \Memcached($this->_config()->getItem('pool_name', null));
  }

  protected function _addServer($server, $port, $persist, $weight, $timeout)
  {
    $this->_connection->addserver($server, $port, $persist);
  }

  /**
   * Disconnect the open connection
   *
   * @return static
   */
  public function disconnect()
  {
    if($this->_connection !== null)
    {
      $this->_connection->quit();
    }
    $this->_connection = null;
    return $this;
  }
}
