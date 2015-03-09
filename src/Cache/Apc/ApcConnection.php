<?php
namespace Packaged\Dal\Cache\Apc;

use Packaged\Dal\Cache\AbstractCacheConnection;
use Packaged\Dal\Cache\CacheItem;
use Packaged\Dal\Cache\ICacheItem;
use Packaged\Dal\Exceptions\Connection\ConnectionException;

class ApcConnection extends AbstractCacheConnection
{
  /**
   * Delete a key from the cache pool
   *
   * @param $key
   *
   * @return bool
   */
  public function deleteKey($key)
  {
    return apc_delete($key);
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
    $success = false;
    $value = apc_fetch($key, $success);
    $item = new CacheItem($key);
    $item->hydrate($success ? $value : null, $success);
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
    return apc_clear_cache("user");
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
    return apc_store($item->getKey(), $item->get(), (int)$ttl);
  }

  /**
   * Open the connection
   *
   * @return static
   *
   * @throws ConnectionException
   *
   * @codeCoverageIgnore
   */
  public function connect()
  {
    if(extension_loaded('apc'))
    {
      if(ini_get('apc.enabled'))
      {
        return $this;
      }
      throw new ConnectionException(
        "APC has not been enabled"
      );
    }
    throw new ConnectionException(
      "APC extension has not been loaded"
    );
  }

  /**
   * Check to see if the connection is already open
   *
   * @return bool
   */
  public function isConnected()
  {
    return extension_loaded('apc') && ini_get('apc.enabled');
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
    return $this;
  }
}
