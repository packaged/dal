<?php
namespace Packaged\Dal\Ql\Cql;

use Packaged\Dal\Ql\Cql\DataType\BooleanType;
use Packaged\Dal\Ql\Cql\DataType\CompositeType;
use Packaged\Dal\Ql\Cql\DataType\DecimalType;
use Packaged\Dal\Ql\Cql\DataType\DoubleType;
use Packaged\Dal\Ql\Cql\DataType\FloatType;
use Packaged\Dal\Ql\Cql\DataType\ICassandraType;
use Packaged\Dal\Ql\Cql\DataType\IntegerType;
use Packaged\Dal\Ql\Cql\DataType\LongType;

class CqlDataType
{
  private static $_types = [
    // Boolean
    'org.apache.cassandra.db.marshal.BooleanType'       => BooleanType::class,
    // Integer
    'org.apache.cassandra.db.marshal.Int32Type'         => IntegerType::class,
    'org.apache.cassandra.db.marshal.IntegerType'       => IntegerType::class,
    // Float
    'org.apache.cassandra.db.marshal.FloatType'         => FloatType::class,
    // Decimal
    'org.apache.cassandra.db.marshal.DecimalType'       => DecimalType::class,
    // Double
    'org.apache.cassandra.db.marshal.DoubleType'        => DoubleType::class,
    // Long
    'org.apache.cassandra.db.marshal.LongType'          => LongType::class,
    'org.apache.cassandra.db.marshal.CounterColumnType' => LongType::class,
    'org.apache.cassandra.db.marshal.TimestampType'     => LongType::class,
    'org.apache.cassandra.db.marshal.SetType'           => CompositeType::class,
  ];

  /**
   * @param $type
   *
   * @return ICassandraType
   */
  public static function getType($type)
  {
    // Remove ReversedType modifier for cluster keys sorted in descending order
    //  eg: org.apache.cassandra.db.marshal.ReversedType(org.apache.cassandra.db.marshal.Int32Type)
    $type = preg_replace(
      '/^org\.apache\.cassandra\.db\.marshal\.ReversedType\((.*)\)$/',
      '\1',
      $type
    );
    $type = preg_replace('/(^.+)\(.+/', '\1', $type);
    return isset(self::$_types[$type]) ? self::$_types[$type] : false;
  }

  public static function pack($type, $value)
  {
    $priType = self::getType($type);
    if($priType)
    {
      if($priType === CompositeType::class)
      {
        $subType = preg_replace('/^.+\((.+)\)/', '\1', $type);
        return $priType::pack($value, $subType);
      }
      return $priType::pack($value);
    }
    return $value;
  }

  public static function unpack($type, $data)
  {
    $type = self::getType($type);
    if($type)
    {
      return $type::unpack($data);
    }
    return $data;
  }
}
