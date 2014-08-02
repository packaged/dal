<?php
namespace Packaged\Dal\Helpers;

class Phid
{
  public static function generate(
    $object, $append = null, $prefix = null, $moreEntropy = false
  )
  {
    if($prefix === null)
    {
      $class  = class_shortname($object);
      $short  = self::getUppers($class);
      $prefix = strlen($short) > 1 ? $short : substr(strtoupper($class), 0, 3);
    }

    if($append !== null)
    {
      $append = self::getUppers($append);
    }

    return uniqid(
      build_path_custom(':', [$prefix, 'PHID', $append]) . ':',
      $moreEntropy
    );
  }

  public static function getUppers($string)
  {
    return preg_replace('/[^A-Z]/', '', ucwords($string));
  }
}
