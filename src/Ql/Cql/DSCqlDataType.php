<?php
namespace Packaged\Dal\Ql\Cql;

use Cassandra\Bigint;
use Cassandra\Blob;
use Cassandra\Date;
use Cassandra\Decimal;
use Cassandra\Float as CassFloat;
use Cassandra\Inet;
use Cassandra\Smallint;
use Cassandra\Time;
use Cassandra\Timestamp;
use Cassandra\Timeuuid;
use Cassandra\Tinyint;
use Cassandra\Uuid;
use Cassandra\Varint;
use Packaged\Dal\Exceptions\Connection\CqlException;

class DSCqlDataType
{
  /*
   * From cassandra.h:
   *
    XX(CASS_VALUE_TYPE_CUSTOM,  0x0000, "", "") \
    XX(CASS_VALUE_TYPE_ASCII,  0x0001, "ascii", "org.apache.cassandra.db.marshal.AsciiType") \
    XX(CASS_VALUE_TYPE_BIGINT,  0x0002, "bigint", "org.apache.cassandra.db.marshal.LongType") \
    XX(CASS_VALUE_TYPE_BLOB,  0x0003, "blob", "org.apache.cassandra.db.marshal.BytesType") \
    XX(CASS_VALUE_TYPE_BOOLEAN,  0x0004, "boolean", "org.apache.cassandra.db.marshal.BooleanType") \
    XX(CASS_VALUE_TYPE_COUNTER,  0x0005, "counter", "org.apache.cassandra.db.marshal.CounterColumnType") \
    XX(CASS_VALUE_TYPE_DECIMAL,  0x0006, "decimal", "org.apache.cassandra.db.marshal.DecimalType") \
    XX(CASS_VALUE_TYPE_DOUBLE,  0x0007, "double", "org.apache.cassandra.db.marshal.DoubleType") \
    XX(CASS_VALUE_TYPE_FLOAT,  0x0008, "float", "org.apache.cassandra.db.marshal.FloatType") \
    XX(CASS_VALUE_TYPE_INT,  0x0009, "int", "org.apache.cassandra.db.marshal.Int32Type") \
    XX(CASS_VALUE_TYPE_TEXT,  0x000A, "text", "org.apache.cassandra.db.marshal.UTF8Type") \
    XX(CASS_VALUE_TYPE_TIMESTAMP,  0x000B, "timestamp", "org.apache.cassandra.db.marshal.TimestampType") \
    XX(CASS_VALUE_TYPE_UUID,  0x000C, "uuid", "org.apache.cassandra.db.marshal.UUIDType") \
    XX(CASS_VALUE_TYPE_VARCHAR,  0x000D, "varchar", "") \
    XX(CASS_VALUE_TYPE_VARINT,  0x000E, "varint", "org.apache.cassandra.db.marshal.IntegerType") \
    XX(CASS_VALUE_TYPE_TIMEUUID,  0x000F, "timeuuid", "org.apache.cassandra.db.marshal.TimeUUIDType") \
    XX(CASS_VALUE_TYPE_INET,  0x0010, "inet", "org.apache.cassandra.db.marshal.InetAddressType") \
    XX(CASS_VALUE_TYPE_DATE,  0x0011, "date", "org.apache.cassandra.db.marshal.SimpleDateType") \
    XX(CASS_VALUE_TYPE_TIME,  0x0012, "time", "org.apache.cassandra.db.marshal.TimeType") \
    XX(CASS_VALUE_TYPE_SMALL_INT,  0x0013, "smallint", "org.apache.cassandra.db.marshal.ShortType") \
    XX(CASS_VALUE_TYPE_TINY_INT,  0x0014, "tinyint", "org.apache.cassandra.db.marshal.ByteType") \
    XX(CASS_VALUE_TYPE_DURATION,  0x0015, "duration", "org.apache.cassandra.db.marshal.DurationType") \
    XX(CASS_VALUE_TYPE_LIST,  0x0020, "list", "org.apache.cassandra.db.marshal.ListType") \
    XX(CASS_VALUE_TYPE_MAP,  0x0021, "map", "org.apache.cassandra.db.marshal.MapType") \
    XX(CASS_VALUE_TYPE_SET,  0x0022, "set", "org.apache.cassandra.db.marshal.SetType") \
    XX(CASS_VALUE_TYPE_UDT,  0x0030, "", "") \
    XX(CASS_VALUE_TYPE_TUPLE,  0x0031, "tuple", "org.apache.cassandra.db.marshal.TupleType")

    CASS_VALUE_TYPE_UNKNOWN = 0xFFFF,
   */

  public static function packValue($value, $typeCode)
  {
    if($value === null)
    {
      return null;
    }

    switch($typeCode)
    {
      case 0x00:
        throw new CqlException("Custom types are not supported");
      case 0x01: // ascii
      case 0x0a: // text
      case 0x0d: // varchar
        return $value;
      case 0x02: // bigint
        return new Bigint($value);
      case 0x03: // blob
        return new Blob($value);
      case 0x04: // bool
        return new Tinyint($value ? 1 : 0);
      case 0x05: // counter
        return new Bigint($value);
      case 0x06: // decimal
        return new Decimal($value);
      case 0x07: // double
      case 0x08: // float
        return new CassFloat($value);
      case 0x09: // int
        return new Bigint($value);
      case 0x0b: // timestamp
        return new Timestamp($value);
      case 0x0c: // UUID
        return new Uuid($value);
      case 0x0e: // varint
        return new Varint($value);
      case 0x0f: // timeuuid
        return new Timeuuid($value);
      case 0x10: // inet
        return new Inet($value);
      case 0x11: // date
        return new Date($value);
      case 0x12: // time
        return new Time($value);
      case 0x13: // smallint
        return new Smallint($value);
      case 0x14: // tinyint
        return new Tinyint($value);

      case 0x20: // list
        throw new CqlException('Lists are not supported');
      case 0x21: // map
        throw new CqlException('Maps are not supported');
      case 0x22: // set
        throw new CqlException('Sets are not supported');
      case 0x15: // duration
        throw new CqlException('Durations are not supported');
      case 0x30: // udt
        throw new CqlException('User-defined types are not supported');
      case 0x31: // tuple
        throw new CqlException('Tuples are not supported');
      default:
        throw new CqlException('Unknown type code: ' . $typeCode);
    }
  }
}
