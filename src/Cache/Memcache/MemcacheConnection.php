<?php
namespace Packaged\Dal\Cache\Memcache;

use Packaged\Dal\Cache\AbstractCacheConnection;
use Packaged\Dal\Cache\CacheItem;
use Packaged\Dal\Cache\ICacheItem;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Helpers\ValueAs;

class MemcacheConnection extends AbstractCacheConnection
{
  /**
   * @var \Memcache
   */
  protected $_connection;

  protected function _newConnection()
  {
    return new \Memcache();
  }

  /**
   * Open the connection
   *
   * @return static
   *
   * @throws ConnectionException
   */
  public function connect()
  {
    $cfg = $this->_config();
    $this->_connection = $this->_newConnection();
    $servers = ValueAs::arr($cfg->getItem('servers', 'localhost'));
    foreach($servers as $alias => $server)
    {
      $port = (int)$cfg->getItem(
        $alias . '.port',
        $cfg->getItem('port', 11211)
      );
      $persist = ValueAs::bool(
        $cfg->getItem(
          $alias . '.persist',
          $cfg->getItem('persist', true)
        )
      );
      $weight = (int)$cfg->getItem(
        $alias . '.weight',
        $cfg->getItem('weight', 1)
      );
      $timeout = (int)$cfg->getItem(
        $alias . '.timeout',
        $cfg->getItem('timeout', 1)
      );

      $this->_addServer($server, $port, $persist, $weight, $timeout);
    }
    return $this;
  }

  protected function _addServer($server, $port, $persist, $weight, $timeout)
  {
    $this->_connection->addserver($server, $port, $persist, $weight, $timeout);
  }

  /**
   * Check to see if the connection is already open
   *
   * @return bool
   */
  public function isConnected()
  {
    return $this->_connection !== null;
  }

  /**
   * Disconnect the open connection
   *
   * @return static
   *
   * @throws ConnectionException
   */
  public function disconnect()
  {
    if($this->_connection !== null)
    {
      $this->_connection->close();
    }
    $this->_connection = null;
    return $this;
  }

  /**
   * Delete a key from the cache pool
   *
   * @param $key
   *
   * @return bool
   */
  public function deleteKey($key)
  {
    return $this->_connection->delete($key);
  }

  /**
   * Returns a Cache Item representing the specified key.
   *
   * This method must always return an ItemInterface object, even in case of
   * a cache miss. It MUST NOT return null.
   *
   * @param string $key
   *   The key for which to return the corresponding Cache Item.
   * @param bool $throw true to throw backend exceptions
   *
   * @return ICacheItem
   *   The corresponding Cache Item.
   * @throws \RuntimeException
   *   If the $key string is not a legal value
   * @throws \Exception
   */
  public function getItem($key, $throw = false)
  {
    $item = new CacheItem($key);
    $item->hydrate(null, false);
    try
    {
      $value = $this->_connection->get($key);
      if($value !== false)
      {
        $item->hydrate($value, true);
      }
    }
    catch(\Exception $e)
    {
      if($throw)
      {
        throw $e;
      }
    }
    return $item;
  }

  /**
   * Deletes all items in the pool.
   *
   * @return boolean
   *   True if the pool was successfully cleared. False if there was an error.
   */
  public function clear()
  {
    $this->_connection->flush();
    return true;
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
    $value = $item->get();
    $compress = is_bool($value) || is_int($value) || is_float($value)
      ? false : MEMCACHE_COMPRESSED;
    return $this->_connection->set(
      $item->getKey(),
      $item->get(),
      $compress,
      $ttl
    );
  }
}
