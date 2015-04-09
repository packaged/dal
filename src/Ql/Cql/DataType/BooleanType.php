<?php
namespace Packaged\Dal\Ql\Cql\DataType;

class BooleanType extends CassandraType
{
  public static function pack($value)
  {
    if($value === null)
    {
      return null;
    }
    return $value ? 1 : 0;
  }

  public static function unpack($data)
  {
    if($data === null || $data === '')
    {
      return null;
    }
    return (bool)$data;
  }
}
