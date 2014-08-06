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
interface ISanitizableDao extends IDao
{
  /**
   * Filter the value for a property
   *
   * @param $key
   * @param $value
   *
   * @return mixed filtered value of the property
   */
  public function filterDaoProperty($key, $value);

  /**
   * Validate a single property
   *
   * @param string $key
   * @param mixed  $value
   * @param bool   $stopFirst Stop on the first error
   * @param bool   $throw
   *
   * @return bool|\Exception[]
   *
   * @throws \Exception
   */
  public function validateDaoProperty(
    $key, $value, $stopFirst = true, $throw = false
  );

  /**
   * Validate the whole DAO, or select properties
   *
   * @param array $properties
   * @param bool  $throw
   *
   * @return array|bool
   * @throws \Exception
   * @throws mixed
   */
  public function isValid(array $properties = null, $throw = false);

  /**
   * Serialize a value based on the rules of a property
   *
   * @param $property
   * @param $value
   *
   * @return string
   */
  public function getPropertySerialized($property, $value);

  /**
   * Unserialize a value based on the rules of a property
   *
   * @param $property
   * @param $value
   *
   * @return mixed
   */
  public function getPropertyUnserialized($property, $value);

  /**
   * Hydrate the DAO with raw data
   *
   * @param array $data
   * @param bool  $raw Is being hydrated with datastore values
   *
   * @return ISanitizableDao
   */
  public function hydrateDao(array $data, $raw = false);

  /**
   * Get the current properties on the dao
   *
   * @param bool $serialized Return the values serialized
   *
   * @return ISanitizableDao
   */
  public function getDaoPropertyData($serialized = true);
}
