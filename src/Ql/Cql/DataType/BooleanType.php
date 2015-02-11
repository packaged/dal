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
    return pack('C', (bool)$value);
  }

  public static function unpack($data)
  {
    if($data === null || $data === '')
    {
      return null;
    }
    return current(unpack('C', $data)) === 1;
  }
}
