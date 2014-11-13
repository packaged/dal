<?php
namespace Packaged\Dal\Ql\Cql\DataType;

class IntegerType extends CassandraType
{
  public static function pack($value)
  {
    return pack('N', $value);
  }

  public static function unpack($data)
  {
    return current(unpack('l', self::_reverseIfLE($data)));
  }
}
