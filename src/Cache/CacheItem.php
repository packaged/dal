<?php
namespace Packaged\Dal\Cache;

class CacheItem implements ICacheItem
{
  protected $_exists = false;
  protected $_key;
  protected $_value;

  public function __construct($key, $value = null)
  {
    $this->_key = $key;
    $this->_value = $value;
  }

  public static function fromDao(CacheDao $dao)
  {
    return new static($dao->getId(), $dao->data);
  }

  public function hydrate($value, $exists = true)
  {
    $this->_exists = $exists;
    $this->_value = $value;
    return $this;
  }

  /**
   * Returns the key for the current cache item.
   *
   * The key is loaded by the Implementing Library, but should be available to
   * the higher level callers when needed.
   *
   * @return string
   *   The key string for this cache item.
   */
  public function getKey()
  {
    return $this->_key;
  }

  /**
   * Retrieves the value of the item from the cache associated with this
   * objects key.
   *
   * The value returned must be identical to the value original stored by set().
   *
   * if isHit() returns false, this method MUST return null. Note that null
   * is a legitimate cached value, so the isHit() method SHOULD be used to
   * differentiate between "null value was found" and "no value was found."
   *
   * @return mixed
   *   The value corresponding to this cache item's key, or null if not found.
   */
  public function get()
  {
    return $this->_value;
  }

  /**
   * Confirms if the cache item lookup resulted in a cache hit.
   *
   * Note: This method MUST NOT have a race condition between calling isHit()
   * and calling get().
   *
   * @return boolean
   *   True if the request resulted in a cache hit.  False otherwise.
   */
  public function isHit()
  {
    return $this->_exists;
  }

  /**
   * Confirms if the cache item exists in the cache.
   *
   * Note: This method MAY avoid retrieving the cached value for performance
   * reasons, which could result in a race condition between exists() and get().
   * To avoid that potential race condition use isHit() instead.
   *
   * @return boolean
   *  True if item exists in the cache, false otherwise.
   */
  public function exists()
  {
    return $this->_exists;
  }
}
