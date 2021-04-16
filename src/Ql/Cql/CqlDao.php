<?php
namespace Packaged\Dal\Ql\Cql;

use Packaged\Dal\DataTypes\UniqueList;
use Packaged\Dal\Ql\QlDao;

abstract class CqlDao extends QlDao
{
  public function getTtl()
  {
    return null;
  }

  public function getTimestamp()
  {
    return null;
  }

  protected function _serializeUniqueList($value)
  {
    if($value instanceof UniqueList)
    {
      $value = $value->calculated();
    }
    return $value;
  }

  /**
   * @param string|object|null $class
   *
   * @return CqlDaoCollection
   */
  protected static function _createCollection($class = null)
  {
    if($class === null)
    {
      $class = get_called_class();
    }

    return CqlDaoCollection::create($class);
  }
}
