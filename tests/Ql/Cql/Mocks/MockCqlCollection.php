<?php
namespace Packaged\Dal\Tests\Ql\Cql\Mocks;

use Packaged\Dal\Ql\Cql\CqlDao;
use Packaged\Dal\Ql\Cql\CqlDaoCollection;

class MockCqlCollection extends CqlDaoCollection
{
  public static function createFromDao(CqlDao $dao)
  {
    $collection = parent::create(get_class($dao));
    $collection->_dao = $dao;
    return $collection;
  }
}
