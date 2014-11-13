<?php
namespace Packaged\Dal\Ql\Cql\DataType;

abstract class CassandraType
{
  public static function pack($value)
  {
    return $value;
  }

  public static function unpack($raw)
  {
    return $raw;
  }

  protected static function _reverseIfLE($bin)
  {
    static $isLittleEndian = null;
    if($isLittleEndian === null)
    {
      $isLittleEndian = current(unpack('v', pack('S', 256))) == 256;
    }
    return $isLittleEndian ? strrev($bin) : $bin;
  }
}
