<?php
namespace Packaged\Dal\Ql\Cql\DataType;

class FloatType extends CassandraType
{
  public static function pack($value)
  {
    if($value === null)
    {
      return null;
    }
    return self::_reverseIfLE(pack('f', $value));
  }

  public static function unpack($data)
  {
    if($data === null)
    {
      return null;
    }
    return current(unpack('f', self::_reverseIfLE($data)));
  }
}
