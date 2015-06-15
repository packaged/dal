<?php
namespace Packaged\Dal\Helpers;

use Packaged\Helpers\Arrays;
use Packaged\Helpers\Strings;
use Packaged\Helpers\ValueAs;

class ChronologicalKey
{
  public static function generate($time = null)
  {
    return
      (ValueAs::nonempty($time, time()) << 32)
      + Arrays::first(unpack('L', Strings::randomString(4)));
  }
}
