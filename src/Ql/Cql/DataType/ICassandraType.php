<?php
namespace Packaged\Dal\Ql\Cql\DataType;

interface ICassandraType
{
  /**
   * Pack a value ready to store in cassandra
   *
   * @param $value
   *
   * @return mixed
   */
  public static function pack($value);

  /**
   * Unpack a cassandra native value
   *
   * @param $data
   *
   * @return mixed
   */
  public static function unpack($data);
}
