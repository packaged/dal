<?php
namespace Packaged\Dal\Ql\Cql\DataType;

class BooleanType extends CassandraType
{
  public static function pack($value)
  {
    return pack('C', (bool)$value);
  }

  public static function unpack($data)
  {
    return current(unpack('C', $data)) === 1;
  }
}
