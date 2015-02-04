<?php
namespace Packaged\Dal\Cache;

use Packaged\Dal\IDataConnection;

interface ICacheConnection extends IDataConnection
{
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
  public function getItem($key);

  /**
   * Returns a traversable set of cache items.
   *
   * @param array $keys
   *   An indexed array of keys of items to retrieve.
   *
   * @return array
   *   A traversable collection of Cache Items keyed by the cache keys of
   *   each item. A Cache item will be returned for each key, even if that
   *   key is not found. However, if no keys are specified then an empty
   *   CollectionInterface object MUST be returned instead.
   */
  public function getItems(array $keys = []);

  /**
   * Deletes all items in the pool.
   *
   * @return boolean
   *   True if the pool was successfully cleared. False if there was an error.
   */
  public function clear();

  /**
   * Removes multiple items from the pool.
   *
   * @param array $keys
   *   An array of keys that should be removed from the pool.
   *
   * @return static The invoked object.
   */
  public function deleteItems(array $keys);

  /**
   * Removes a cache item from the pool.
   *
   * @param $key ICacheItem The item that should be removed from the pool.
   *
   * @return static The invoked object.
   */
  public function deleteItem(ICacheItem $key);

  /**
   * Save multiple items
   *
   * @param array $items
   *
   * @return array[key,bool]
   */
  public function saveItems(array $items);

  /**
   * Save cache item
   *
   * @param ICacheItem $item
   * @param int|null   $ttl
   *
   * @return bool
   */
  public function saveItem(ICacheItem $item, $ttl = null);
}
