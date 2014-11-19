<?php
namespace Packaged\Dal\Foundation;

use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\IDao;
use Packaged\Dal\IDataStore;

abstract class AbstractDataStore implements IDataStore
{
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
    $dao->markDaoDatasetAsSaved();
    $dao->markDaoAsLoaded();
  }
}
