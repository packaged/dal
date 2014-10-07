<?php
namespace Ql;

use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\PdoConnection;

class QlDaoTest extends \PHPUnit_Framework_TestCase
{
  public function testStatics()
  {
    $datastore  = new MockQlDataStore();
    $connection = new PdoConnection();
    $datastore->setConnection($connection);

    $collection = MockQlDao::collection();
    $this->assertInstanceOf(MockQlDao::class, $collection->createNewDao());

    Dao::setDalResolver(new DalResolver());

    MockQlDao::getDalResolver()->addDataStore('mockql', $datastore);

    $u           = new MockQlDao();
    $u->username = 'Test';
    $u->save();
    $mocks = MockQlDao::loadWhere(['username' => 'Test']);
    $this->assertTrue(count($mocks) === 1);
    $u->delete();

    Dao::unsetDalResolver();
  }
}
