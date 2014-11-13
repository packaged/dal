<?php
namespace Packaged\Dal\Ql\Cql\DataType;

class DoubleType extends CassandraType
{
  public static function pack($value)
  {
    return self::_reverseIfLE(pack('d', $value));
  }

  public static function unpack($data)
  {
    return current(unpack('d', self::_reverseIfLE($data)));
  }
}
