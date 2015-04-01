<?php
namespace Packaged\Dal\Ql\Cql;

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
