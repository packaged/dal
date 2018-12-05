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

  /**
   * @param IDao $dao
   *
   * @throws DataStoreException
   */
  protected function _assertCanDelete(IDao $dao)
  {
    if($dao->isDaoLoaded())
    {
      $changes = $dao->getDaoChanges();
      if($changes)
      {
        foreach($dao->getDaoIDProperties() as $idKey)
        {
          if(array_key_exists($idKey, $changes))
          {
            throw new DataStoreException("Cannot delete object.  ID property has changed.");
          }
        }
      }
    }
  }

  /**
   * @param IDao $dao
   *
   * @return IDao
   * @throws DataStoreException
   */
  public function delete(IDao $dao)
  {
    $this->_assertCanDelete($dao);
    $this->_doDelete($dao);
    return $dao;
  }

  /**
   * @param IDao $dao
   */
  abstract protected function _doDelete(IDao $dao);
}
