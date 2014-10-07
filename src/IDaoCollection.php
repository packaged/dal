<?php
namespace Packaged\Dal;

interface IDaoCollection
  extends \Countable, \JsonSerializable, \IteratorAggregate,
          \ArrayAccess
{
  /**
   * Execute a callback over each dao in the collection
   *
   * @param \Closure $callback
   *
   * @return $this
   */
  public function each(\Closure $callback);

  /**
   * True if not DAOs exist within the collection
   *
   * @return bool
   */
  public function isEmpty();

  /**
   * Find all distinct values of a property in the collection
   *
   * @param $property
   *
   * @return array
   */
  public function distinct($property);

  /**
   * Pull all properties from the collection
   * optionally keyed by another property
   *
   * @param string      $property
   * @param null|string $keyProperty
   *
   * @return mixed
   */
  public function ppull($property, $keyProperty = null);

  /**
   * Pull an array of properties from the collection
   * optionally keyed by another property
   *
   * @param string[]    $properties
   * @param null|string $keyProperty
   *
   * @return mixed
   */
  public function apull(array $properties, $keyProperty = null);
}
