<?php
namespace Packaged\Dal\Cache\Ephemeral;

use Packaged\Dal\Cache\AbstractCacheConnection;
use Packaged\Dal\Cache\CacheItem;
use Packaged\Dal\Cache\ICacheItem;
use Packaged\Dal\Exceptions\Connection\ConnectionException;

class EphemeralConnection extends AbstractCacheConnection
{
  protected static $cachePool;
  protected $_pool;

  /**
   * Open the connection
   *
   * @return static
   */
  public function connect()
  {
    if(!$this->isConnected())
    {
      try
      {
        $this->_pool = $this->_config()->getItem('pool_name', 'misc');
      }
      catch(\Exception $e)
      {
        $this->_pool = 'misc';
      }
      if(!isset(self::$cachePool[$this->_pool]))
      {
        self::$cachePool[$this->_pool] = [];
      }
    }
    return $this;
  }

  /**
   * Check to see if the connection is already open
   *
   * @return bool
   */
  public function isConnected()
  {
    return $this->_pool !== null;
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
    $this->_pool = null;
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
    $this->connect();
    unset(self::$cachePool[$this->_pool][$key]);
    return true;
  }

  /**
   * Returns a Cache Item representing the specified key.
   *
   * This method must always return an ItemInterface object, even in case of
   * a cache miss. It MUST NOT return null.
   *
   * @param string $key
   *   The key for which to return the corresponding Cache Item.
   *
   * @return ICacheItem
   *   The corresponding Cache Item.
   * @throws \RuntimeException
   *   If the $key string is not a legal value
   */
  public function getItem($key)
  {
    $this->connect();
    $item = new CacheItem($key);
    if(isset(self::$cachePool[$this->_pool][$key]))
    {
      $item->hydrate(self::$cachePool[$this->_pool][$key], true);
    }
    else
    {
      $item->hydrate(null, false);
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
    $this->connect();
    self::$cachePool[$this->_pool] = [];
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
    $this->connect();
    self::$cachePool[$this->_pool][$item->getKey()] = $item->get();
    return true;
  }
}
