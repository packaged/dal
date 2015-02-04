<?php
namespace Packaged\Dal\Cache\Memcache;

class MemcachedConnection extends MemcacheConnection
{
  protected function _newConnection()
  {
    return new \Memcached($this->_config()->getItem('pool_name', null));
  }
}
