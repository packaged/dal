<?php
namespace Packaged\Dal;

/**
 * Interface IDao Data Access Object
 *
 * A representation of a specific item of data within a data store
 *
 * @example A single row within a database table
 *
 * @package Packaged\Dal
 */
interface IDao extends \JsonSerializable
{
  /**
   * Get all changed properties since load
   *
   * @return array[property] => ['from' => '','to' => '']
   */
  public function getDaoChanges();

  /**
   * Get the current properties on the dao
   *
   * @return array[property] = value
   */
  public function getDaoPropertyData();

  /**
   * Get an array of the properties maintained within this DAO
   *
   * @return array properties
   */
  public function getDaoProperties();

  /**
   * Hydrate the DAO with raw data
   *
   * @param array $data
   *
   * @return self
   */
  public function hydrateDao(array $data);

  /**
   * Set the value of a property
   *
   * @param $key
   * @param $value
   *
   * @return self
   */
  public function setDaoProperty($key, $value);

  /**
   * Retrieve the value of a property
   *
   * @param $key
   *
   * @return mixed
   */
  public function getDaoProperty($key);

  /**
   * Retrieve an array of ID fields used for the primary key
   *
   * @return array
   */
  public function getDaoIDProperties();

  /**
   * Set the current dataset to be that of the data store values
   *
   * @return self
   */
  public function markDaoDatasetAsSaved();

  /**
   * Set the DAO as loaded
   *
   * @param bool $isLoaded
   *
   * @returns self
   */
  public function markDaoAsLoaded($isLoaded = true);

  /**
   * Retrieve the ID for a DAO, if multiple properties make up the ID,
   * they will be returned in an array
   *
   * @param bool $forceArray Force an array return, even with single property
   *
   * @return array|mixed
   */
  public function getId($forceArray = false);
}
