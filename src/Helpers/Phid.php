<?php
namespace Packaged\Dal\Helpers;

use Packaged\Helpers\Objects;
use Packaged\Helpers\Path;

class Phid
{
  public static function generate(
    $object, $append = null, $prefix = null, $moreEntropy = false
  )
  {
    if($prefix === null)
    {
      $class = Objects::classShortname($object);
      $short = self::getUppers($class);
      $prefix = strlen($short) > 1 ? $short : substr(strtoupper($class), 0, 3);
    }

    if($append !== null)
    {
      $append = self::getUppers($append);
    }

    return uniqid(
      Path::buildCustom(':', [$prefix, 'PHID', $append]) . ':',
      $moreEntropy
    );
  }

  public static function getUppers($string)
  {
    return preg_replace('/[^A-Z]/', '', ucwords($string));
  }
}
