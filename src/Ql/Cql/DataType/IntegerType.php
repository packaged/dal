<?php
namespace Packaged\Dal\Ql\Cql\DataType;

class IntegerType extends CassandraType
{
  public static function pack($value)
  {
    if($value === null)
    {
      return null;
    }
    return pack('N', $value);
  }

  public static function unpack($data)
  {
    if($data === null)
    {
      return null;
    }
    return current(unpack('l', self::_reverseIfLE($data)));
  }
}
