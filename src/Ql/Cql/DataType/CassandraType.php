<?php
namespace Packaged\Dal\Ql\Cql\DataType;

abstract class CassandraType implements ICassandraType
{
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
