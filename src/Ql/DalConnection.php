<?php
namespace Packaged\Dal\Ql;

use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Cache\CacheItem;
use Packaged\Dal\Cache\Ephemeral\EphemeralConnection;
use Packaged\Dal\Cache\ICacheConnection;

abstract class DalConnection
{
  /**
   * @var ICacheConnection
   */
  protected static $_databaseCache = null;

  /**
   * Get a key for this connection's database cache. This is most likely to
   * consist of the connected hostname and port
   *
   * @return string
   */
  abstract protected function _getDatabaseCacheKey();

  /**
   * Switch database/keyspace
   *
   * @param string $database
   */
  abstract protected function _selectDatabase($database);

  /**
   * @return string
   */
  abstract protected function _getDatabaseName();

  public function __construct()
  {
    $config = new ConfigSection(
      'dal_database', ['pool_name' => 'dal_database']
    );
    if(self::$_databaseCache === null)
    {
      self::$_databaseCache = new EphemeralConnection();
      self::$_databaseCache->configure($config);
    }
  }

  public function setKeyspaceCache(ICacheConnection $cache)
  {
    self::$_databaseCache = $cache;
  }

  protected function _clearDatabaseCache()
  {
    $key = $this->_getInternalDatabaseCacheKey();
    if($key)
    {
      self::$_databaseCache->deleteKey($key);
    }
  }

  private function _getCurrentDatabase()
  {
    $key = $this->_getInternalDatabaseCacheKey();
    if($key)
    {
      return self::$_databaseCache->getItem($key)->get();
    }
    return null;
  }

  protected function _switchDatabase($database = null, $force = false)
  {
    $database = $database ?: $this->_getDatabaseName();
    if($database && ($force || ($this->_getCurrentDatabase() != $database)))
    {
      $this->_selectDatabase($database);

      $key = $this->_getInternalDatabaseCacheKey();
      if($key)
      {
        self::$_databaseCache->saveItem(new CacheItem($key, $database));
      }
    }
  }

  private function _getInternalDatabaseCacheKey()
  {
    $key = $this->_getDatabaseCacheKey();
    return $key ? get_called_class() . '-' . $key : null;
  }
}
