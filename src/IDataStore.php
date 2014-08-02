<?php
namespace Packaged\Dal;

use Packaged\Dal\Exceptions\DataStore\DaoNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DataStoreException;

/**
 * Interface IDataStore Responsible for saving and loading DAOs
 *
 * @example A Table within a database
 *
 * @package Packaged\Dal
 */
interface IDataStore
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
  public function save(IDao $dao);

  /**
   * Hydrate a DAO from the data store
   *
   * @param IDao $dao
   *
   * @return IDao Loaded DAO
   *
   * @throws DaoNotFoundException
   */
  public function load(IDao $dao);

  /**
   * Delete the DAO from the data store
   *
   * @param IDao $dao
   *
   * @return IDao
   *
   * @throws DataStoreException
   */
  public function delete(IDao $dao);
}
