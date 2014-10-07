<?php
namespace Packaged\Dal\Traits\Dao;

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
   * @throws \Packaged\Dal\Exceptions\DataStore\DaoNotFoundException
   */
  public function load()
  {
    /**
     * @var $this AbstractDao
     */
    return $this->getDataStore()->load($this);
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
}
