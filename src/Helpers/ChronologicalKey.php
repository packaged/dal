<?php
namespace Packaged\Dal\Helpers;

use Packaged\Helpers\Strings;

class ChronologicalKey
{
  public static function generate($time = null)
  {
    return
      (nonempty($time, time()) << 32)
      + head(unpack('L', Strings::randomString(4)));
  }
}
