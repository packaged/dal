<?php

namespace Packaged\Dal\Ql\Cql\DataType;

use Packaged\Dal\Ql\Cql\CqlDataType;

class CompositeType extends CassandraType
{
  /** @internal */
  const NON_SLICE = 0;
  /** @internal */
  const SLICE_START = 1;
  /** @internal */
  const SLICE_FINISH = 2;

  public static function pack($value, $subType = null, $slice_end = self::NON_SLICE)
  {
    $res = "";
    $num_items = count($value);
    for($i = 0; $i < $num_items; $i++)
    {
      $item = $value[$i];
      $eoc = 0x00;
      if($i === ($num_items - 1))
      {
        // if is last item
        if($slice_end == self::SLICE_START)
        {
          $eoc = 0xFF;
        }
        else if($slice_end == self::SLICE_FINISH)
        {
          $eoc = 0x01;
        }
      }

      $packed = CqlDataType::pack($subType, $item);
      $len = strlen($packed);
      $res .= pack("C2", $len & 0xFF00, $len & 0xFF) . $packed . pack("C1", $eoc);
    }

    return $res;
  }

  public static function unpack($data, $subType = null)
  {
    return '';
  }
}
