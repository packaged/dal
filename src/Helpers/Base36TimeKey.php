<?php
namespace Packaged\Dal\Helpers;

class Base36TimeKey
{
  public static function generate($time = null)
  {
    if($time === null)
    {
      $time = microtime(true) * 1000;
    }
    else if($time < 9999999999)
    {
      $time += mt_rand(1000, 9999);
    }

    $return = base_convert(mt_rand(1000, 9999) . floor($time), 10, 36);
    for($i = 0; $i < 3; $i++)
    {
      $rand = rand(0, 35);
      if($rand > 9)
      {
        $return .= chr($rand + 55);
      }
      else
      {
        $return .= $rand;
      }
    }
    return strtoupper($return);
  }
}
