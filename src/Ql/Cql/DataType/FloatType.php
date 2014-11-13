<?php
namespace Packaged\Dal\Ql\Cql\DataType;

class FloatType extends CassandraType
{
  public static function pack($value)
  {
    return self::_reverseIfLE(pack('f', $value));
  }

  public static function unpack($data)
  {
    return current(unpack('f', self::_reverseIfLE($data)));
  }
}
