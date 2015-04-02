<?php
namespace Packaged\Dal\Ql\Cql\DataType;

class DecimalType extends CassandraType
{
  public static function pack($value)
  {
    if($value === null)
    {
      return null;
    }
    $valueStr = strtolower((string)$value);
    $expOffset = 0;
    if(strpos($valueStr, 'e') !== false)
    {
      $parts = explode("e", $valueStr);
      $valueStr = $parts[0];
      $expOffset = (int)$parts[1];
    }

    $parts = explode(".", $valueStr);
    $hasFraction = count($parts) > 1;
    $digits = (int)($hasFraction ? $parts[0] . $parts[1] : $parts[0]);
    $exp = $hasFraction ? strlen($parts[1]) : 0;
    $exp -= $expOffset;

    $expBytes = pack('N', $exp);
    // TODO: This always encodes as 64 bit. Find a way to use less bytes
    $digitBytes = LongType::pack($digits);
    return $expBytes . $digitBytes;
  }

  public static function unpack($data)
  {
    if($data === null || $data === '')
    {
      return null;
    }
    // Decimals are stored as (-exponent).(value as int)
    // First 4 bytes are exponent, both are big-endian
    $expBin = substr($data, 0, 4);
    $valueBin = substr($data, 4);
    // exponent is an ordinary 32-bit BE int
    $exp = current(unpack('l', self::_reverseIfLE($expBin)));

    // value is a BE int of arbitrary length
    $value = 0;
    $isNeg = current(unpack('c', $valueBin[0])) < 0;

    $str = strrev($valueBin);
    for($i = 0; $i < strlen($str); $i++)
    {
      if($isNeg)
      {
        $n = ord(chr(~ord($str[$i])));
      }
      else
      {
        $n = ord($str[$i]);
      }
      $value += $n << ($i * 8);
    }

    if($isNeg)
    {
      $value = -($value + 1);
    }
    return round($value * pow(10, -$exp), $exp);
  }
}
