<?php
namespace Ql;

use Foundation\MockAbstractDao;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\PdoConnection;
use Packaged\Dal\Ql\QlDaoCollection;
use Packaged\Helpers\ValueAs;

class QlDaoCollectionTest extends \PHPUnit_Framework_TestCase
{
  public function testHydrated()
  {
    $collection = new MockQlDaoCollection();
    $collection->setDummyData();
    $this->assertEquals(1, $collection->min('id'));
    $this->assertEquals(8, $collection->max('id'));
    $this->assertEquals(4, $collection->avg('id'));
    $this->assertEquals(16, $collection->sum('id'));
    $this->assertEquals(
      ["Test", "User", "Testing"],
      array_values($collection->distinct('name'))
    );
  }

  public function testQueried()
  {
    Dao::setDalResolver(new DalResolver());
    $datastore  = new MockQlDataStore();
    $connection = new PdoConnection();
    $datastore->setConnection($connection);
    MockQlDao::getDalResolver()->addDataStore('mockql', $datastore);

    $u = new MockQlDao();
    $datastore->getConnection()
      ->connect()
      ->runQuery("TRUNCATE " . $u->getTableName());

    $collection = MockQlDao::collection();
    $this->assertNull($collection->min('id'));
    $this->assertNull($collection->max('id'));
    $this->assertNull($collection->avg('id'));
    $this->assertNull($collection->sum('id'));
    $this->assertEmpty($collection->distinct('username'));

    $u->username = 'Test';
    $u->id       = 1;
    $u->save();
    $u->username = 'Test';
    $u->id       = 2;
    $u->save();
    $u->username = 'User';
    $u->id       = 5;
    $u->save();
    $u->username = 'Testing';
    $u->id       = 8;
    $u->save();

    $this->assertEquals(1, $collection->min('id'));
    $this->assertEquals(8, $collection->max('id'));
    $this->assertEquals(4, $collection->avg('id'));
    $this->assertEquals(16, $collection->sum('id'));
    $this->assertEquals(
      ["Test", "User", "Testing"],
      array_values($collection->distinct('username'))
    );

    $datastore->getConnection()->runQuery("TRUNCATE " . $u->getTableName());

    Dao::unsetDalResolver();
  }
}


class MockQlDaoCollection extends QlDaoCollection
{
  public function setDaos(array $daos)
  {
    $this->_daos = $daos;
    return $this;
  }

  public function getDaos()
  {
    return $this->_daos;
  }

  public function setDummyData()
  {
    $this->_daos    = [];
    $this->_daos[1] = ValueAs::obj(['name' => 'Test', 'id' => 1]);
    $this->_daos[2] = ValueAs::obj(['name' => 'Test', 'id' => 2]);
    $user           = new \stdClass();
    $user->name     = 'User';
    $user->id       = 5;
    $this->_daos[5] = $user;
    $mock           = new MockAbstractDao();
    $mock->name     = 'Testing';
    $mock->id       = 8;
    $this->_daos[8] = $mock;
  }
}
