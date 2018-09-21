<?php
namespace Packaged\Dal\Cache;

use Packaged\Config\ConfigurableInterface;
use Packaged\Config\ConfigurableTrait;
use Packaged\Dal\IResolverAware;
use Packaged\Dal\Traits\ResolverAwareTrait;

abstract class AbstractCacheConnection
  implements ICacheConnection, ConfigurableInterface, IResolverAware
{
  use ConfigurableTrait;
  use ResolverAwareTrait;

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
  public function getItems(array $keys = [])
  {
    $return = [];
    foreach($keys as $key)
    {
      $return[$key] = $this->getItem($key);
    }
    return $return;
  }

  /**
   * Removes multiple items from the pool.
   *
   * @param array $keys
   *   An array of keys that should be removed from the pool.
   *
   * @return array[key,bool]
   */
  public function deleteItems(array $keys)
  {
    $results = [];
    foreach($keys as $key)
    {
      if($key instanceof ICacheItem)
      {
        $results[$key->getKey()] = $this->deleteItem($key);
      }
      else
      {
        $results[$key] = $this->deleteKey($key);
      }
    }
    return $results;
  }

  /**
   * Removes a cache item from the pool.
   *
   * @param $key ICacheItem The item that should be removed.
   *
   * @return bool
   */
  public function deleteItem(ICacheItem $key)
  {
    return $this->deleteKey($key->getKey());
  }

  /**
   * Save multiple items
   *
   * @param array $items
   *
   * @return array[key,bool]
   */
  public function saveItems(array $items)
  {
    $results = [];
    foreach($items as $item)
    {
      if($item instanceof ICacheItem)
      {
        $results[$item->getKey()] = $this->saveItem($item);
      }
    }
    return $results;
  }
}
