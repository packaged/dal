<?php
namespace Packaged\Dal\Traits\Dao;

use Packaged\Dal\Exceptions\DataStore\DaoNotFoundException;
use Packaged\Dal\Foundation\AbstractDao;
use Packaged\Dal\IDao;
use Packaged\Dal\IDataStore;

/**
 * @method IDataStore getDataStore
 */
trait LSDTrait
{
  /**
   * @return IDao
   * @throws DaoNotFoundException
   */
  public function load()
  {
    /**
     * @var $this AbstractDao
     */
    return $this->getDataStore()->load($this);
  }

  /**
   * Load a DAO by its ID(s)
   *
   * @param ...$id
   *
   * @return static
   * @throws DaoNotFoundException
   */
  public static function loadById(...$id)
  {
    $dao = new static;
    /**
     * @var $dao AbstractDao
     */
    $dao->hydrateDao(array_combine($dao->getDaoIDProperties(), $id));
    /**
     * @var $dao LsdTrait
     */
    $dao->load();
    return $dao;
  }

  /**
   * Load a dao by id, or return a new dao
   *
   * @param ...$id
   *
   * @return static
   */
  public static function loadOrNew(...$id)
  {
    try
    {
      return static::loadById(...$id);
    }
    catch(DaoNotFoundException $e)
    {
      $dao = new static;
      /**
       * @var $dao AbstractDao
       */
      $dao->hydrateDao(array_combine($dao->getDaoIDProperties(), $id));
      return $dao;
    }
  }

  /**
   * Save this object
   *
   * @return array
   */
  public function save()
  {
    /**
     * @var $this AbstractDao
     */
    return $this->getDataStore()->save($this);
  }

  /**
   * Delete this object
   *
   * @return IDao
   */
  public function delete()
  {
    /**
     * @var $this AbstractDao
     */
    return $this->getDataStore()->delete($this);
  }

  public function exists()
  {
    /**
     * @var $this AbstractDao
     */
    return $this->getDataStore()->exists($this);
  }
}
