<?php
namespace Packaged\Dal\Ql\Cql\DataType;

class DoubleType extends CassandraType
{
  public static function pack($value)
  {
    if($value === null)
    {
      return null;
    }
    return self::_reverseIfLE(pack('d', $value));
  }

  public static function unpack($data)
  {
    if($data === null || $data === '')
    {
      return null;
    }
    return current(unpack('d', self::_reverseIfLE($data)));
  }
}
