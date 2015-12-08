<?php
namespace Packaged\Dal\Ql;

use Packaged\Config\ConfigurableInterface;
use Packaged\Config\ConfigurableTrait;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Cache\CacheItem;
use Packaged\Dal\Cache\Ephemeral\EphemeralConnection;
use Packaged\Dal\Cache\ICacheConnection;
use Packaged\Dal\IResolverAware;
use Packaged\Dal\Traits\ResolverAwareTrait;

abstract class DalConnection
  implements IQLDataConnection, ConfigurableInterface, IResolverAware
{
  use ConfigurableTrait;
  use ResolverAwareTrait;

  /**
   * @var ICacheConnection
   */
  protected static $_databaseCache = null;

  /**
   * @var array
   */
  protected static $_stmtCache = [];

  /**
   * Get a key for this connection's database cache. This is most likely to
   * consist of the connected hostname and port
   *
   * @return string
   */
  abstract protected function _getConnectionId();

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

  public function disconnect()
  {
    $this->_clearStmtCache();
    $this->_clearDatabaseCache();
    return $this;
  }

  protected function _clearDatabaseCache()
  {
    $key = $this->_getConnectionId();
    if($key)
    {
      self::$_databaseCache->deleteKey($key);
    }
  }

  private function _getCurrentDatabase()
  {
    $key = $this->_getConnectionId();
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
      $this->_clearStmtCache();
      $this->_selectDatabase($database);

      $key = $this->_getConnectionId();
      if($key)
      {
        self::$_databaseCache->saveItem(new CacheItem($key, $database));
      }
    }
  }

  protected function _addCachedStmt($key, $stmt)
  {
    $connId = $this->_getConnectionId();
    if($connId)
    {
      if(isset(self::$_stmtCache[$connId]))
      {
        self::$_stmtCache[$connId][$key] = $stmt;
      }
      else
      {
        self::$_stmtCache[$connId] = [$key => $stmt];
      }
    }
  }

  protected function _getCachedStmt($key)
  {
    $connId = $this->_getConnectionId();
    return ($connId && isset(self::$_stmtCache[$connId][$key]))
      ? self::$_stmtCache[$connId][$key] : null;
  }

  protected function _deleteCachedStmt($key)
  {
    $connId = $this->_getConnectionId();
    if($connId && isset(self::$_stmtCache[$connId][$key]))
    {
      unset(self::$_stmtCache[$connId][$key]);
    }
  }

  protected function _clearStmtCache()
  {
    $connId = $this->_getConnectionId();
    if($connId)
    {
      self::$_stmtCache[$connId] = [];
    }
  }

  protected function &_getStmtCache()
  {
    $connId = $this->_getConnectionId();
    if($connId)
    {
      if(!isset(self::$_stmtCache[$connId]))
      {
        self::$_stmtCache[$connId] = [];
      }
      return self::$_stmtCache[$connId];
    }
    return [];
  }
}
