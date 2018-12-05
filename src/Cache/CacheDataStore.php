<?php
namespace Packaged\Dal\Cache;

use Packaged\Config\ConfigurableInterface;
use Packaged\Config\ConfigurableTrait;
use Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DaoNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\Foundation\AbstractDataStore;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\IDao;

class CacheDataStore extends AbstractDataStore implements ConfigurableInterface
{
  use ConfigurableTrait;

  protected $_connection;

  /**
   * Save a DAO to the data store
   *
   * @param IDao $dao
   *
   * @return array of changed properties
   *
   * @throws DataStoreException
   */
  public function save(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);
    $this->_connectedConnection()->saveItem(
      CacheItem::fromDao($dao),
      $dao->getTtl()
    );
  }

  /**
   * Hydrate a DAO from the data store
   *
   * @param IDao $dao
   *
   * @return IDao Loaded DAO
   *
   * @throws DaoNotFoundException
   */
  public function load(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);
    $item = $this->_connectedConnection()->getItem($dao->getId());

    if(!$item->exists())
    {
      throw new DaoNotFoundException("Cache Item Not Found");
    }

    $dao->hydrateDao(['data' => $item->get()], true);
    $dao->markDaoDatasetAsSaved();
    $dao->markDaoAsLoaded();
    return $dao;
  }

  /**
   * @param IDao $dao
   *
   * @throws ConnectionNotFoundException
   * @throws DataStoreException
   */
  protected function _doDelete(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);
    $this->_connectedConnection()->deleteItem(CacheItem::fromDao($dao));
  }

  /**
   * Does the object exist in the data store
   *
   * @param IDao $dao
   *
   * @return bool
   */
  public function exists(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);
    $item = $this->_connectedConnection()->getItem($dao->getId());
    return $item->exists();
  }

  /**
   * @param IDao $dao
   *
   * @return CacheDao
   *
   * @throws \Packaged\Dal\Exceptions\DataStore\DataStoreException
   */
  protected function _verifyDao(IDao $dao)
  {
    if($dao instanceof CacheDao)
    {
      return $dao;
    }

    throw new DataStoreException(
      "You must pass a CacheDao to CacheDataStore", 500
    );
  }

  /**
   * Get the connection, and connect if not connected
   *
   * @return ICacheConnection
   * @throws \Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException
   */
  protected function _connectedConnection()
  {
    if(!$this->getConnection()->isConnected())
    {
      $this->getConnection()->connect();
    }
    return $this->getConnection();
  }

  /**
   * Retrieve the connection for this data store
   *
   * @return ICacheConnection
   * @throws ConnectionNotFoundException
   */
  public function getConnection()
  {
    if($this->_connection === null)
    {
      $conn = $this->_config()->getItem('connection');
      if($conn === null)
      {
        throw new ConnectionNotFoundException(
          "No connection has been configured on this datastore"
        );
      }
      $this->_connection = Dao::getDalResolver()->getConnection($conn);
    }
    return $this->_connection;
  }
}
