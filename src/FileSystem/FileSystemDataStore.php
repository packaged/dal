<?php
namespace Packaged\Dal\FileSystem;

use Packaged\Dal\Exceptions\DataStore\DaoNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\IDao;
use Packaged\Dal\IDataStore;

class FileSystemDataStore implements IDataStore
{
  /**
   * Save a file to the filesystem
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
    file_put_contents($dao->filepath, $dao->content);
    $changes = $dao->getDaoChanges();
    $dao->markDaoDatasetAsSaved();
    $dao->markDaoAsLoaded();
    return $changes;
  }

  /**
   * Load the content of a FileSystemDao
   *
   * @param IDao $dao
   *
   * @return FileSystemDao Loaded DAO
   *
   * @throws DaoNotFoundException
   */
  public function load(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);
    if(file_exists($dao->filepath))
    {
      $dao->content  = file_get_contents($dao->filepath);
      $dao->filesize = mb_strlen($dao->content);
    }
    else
    {
      throw new DaoNotFoundException(
        "The file '$dao->filepath' does not exist", 404
      );
    }
    $dao->markDaoDatasetAsSaved();
    $dao->markDaoAsLoaded(true);
    return $dao;
  }

  /**
   * Delete a file from the filesystem
   *
   * @param IDao $dao
   *
   * @return FileSystemDao
   *
   * @throws DataStoreException
   */
  public function delete(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);
    unlink($dao->filepath);
    return $dao;
  }

  /**
   * @param IDao $dao
   *
   * @return FileSystemDao
   *
   * @throws \Packaged\Dal\Exceptions\DataStore\DataStoreException
   */
  protected function _verifyDao(IDao $dao)
  {
    if($dao instanceof FileSystemDao)
    {
      return $dao;
    }

    throw new DataStoreException(
      "You must pass a FileSystemDao to FileSystemDataStore", 500
    );
  }

  /**
   * Retrieve the filesize of a dao file
   *
   * @param IDao $dao
   *
   * @return int
   * @throws \Packaged\Dal\Exceptions\DataStore\DataStoreException
   */
  public function getFilesize(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);
    if(file_exists($dao->filepath))
    {
      $dao->filesize = filesize($dao->filepath);
    }
    else
    {
      throw new DaoNotFoundException(
        "The file '$dao->filepath' does not exist", 404
      );
    }
    return $dao;
  }
}
