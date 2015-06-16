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
}
