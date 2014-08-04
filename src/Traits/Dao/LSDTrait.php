<?php
namespace Packaged\Dal\Traits\Dao;

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
    return $this->getDataStore()->load($this);
  }

  /**
   * Save the file
   *
   * @return array
   */
  public function save()
  {
    return $this->getDataStore()->save($this);
  }

  /**
   * Delete this file
   *
   * @return FileSystemDao
   */
  public function delete()
  {
    return $this->getDataStore()->delete($this);
  }
}
