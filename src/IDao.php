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
interface IDao
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
   * @return mixed
   */
  public function hydrateDao(array $data);

  /**
   * Set the value of a property
   *
   * @param $key
   * @param $value
   *
   * @return static
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
}
