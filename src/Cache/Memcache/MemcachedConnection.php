<?php
namespace Packaged\Dal\Cache\Memcache;

class MemcachedConnection extends MemcacheConnection
{
  protected function _newConnection()
  {
    return new \Memcached($this->_config()->getItem('pool_name', null));
  }

  protected function _addServer($server, $port, $persist, $weight, $timeout)
  {
    $this->_connection->addserver($server, $port, $persist);
  }
}
