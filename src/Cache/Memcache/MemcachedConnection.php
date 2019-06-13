<?php
namespace Packaged\Dal\Cache\Memcache;

use Packaged\Dal\Cache\ICacheItem;

class MemcachedConnection extends MemcacheConnection
{
  /**
   * @var \Memcached
   */
  protected $_connection;

  protected function _newConnection()
  {
    $connection = new \Memcached($this->_config()->getItem('pool_name', null));
    $user = $this->_config()->getItem('sasl_user', '');
    $pass = $this->_config()->getItem('sasl_pass', '');
    if($user || $pass)
    {
      $this->_connection->setSaslAuthData($user, $pass);
    }
    return $connection;
  }

  protected function _addServer($server, $port, $persist, $weight, $timeout)
  {
    $list = $this->_connection->getServerList();
    foreach($list as $srv)
    {
      if($srv['host'] === $server
        && (int)$srv['port'] === (int)$port
        && (!isset($srv['weight']) || (int)$srv['weight'] === (int)$weight)
      )
      {
        return;
      }
    }
    $this->_connection->addServer($server, $port, $weight);
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
      if(method_exists($this->_connection, 'quit'))
      {
        $this->_connection->quit();
      }
    }
    $this->_connection = null;
    return $this;
  }

  /**
   * Save cache item
   *
   * @param ICacheItem $item
   * @param int|null   $ttl
   *
   * @return bool
   */
  public function saveItem(ICacheItem $item, $ttl = null)
  {
    return $this->_connection->set($item->getKey(), $item->get(), $ttl);
  }
}
