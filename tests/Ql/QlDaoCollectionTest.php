<?php
namespace Ql;

use Foundation\MockAbstractDao;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\QlDaoCollection;
use Packaged\Helpers\ValueAs;
use Packaged\QueryBuilder\Assembler\QueryAssembler;
use Packaged\QueryBuilder\Clause\LimitClause;

class QlDaoCollectionTest extends \PHPUnit_Framework_TestCase
{
  public function testHydrated()
  {
    $collection = new MockQlDaoCollection();
    $collection->setDummyData();
    $this->assertEquals(4, $collection->count());
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
    $datastore = new MockQlDataStore();
    $connection = new MockPdoConnection();
    $connection->config();
    $datastore->setConnection($connection);
    MockQlDao::getDalResolver()->addDataStore('mockql', $datastore);

    $connection->setResolver(MockQlDao::getDalResolver());

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

    $u->display = 'queried';
    $u->username = 'Test';
    $u->id = 1;
    $u->save();
    $u->markDaoAsLoaded(false);
    $u->username = 'Test';
    $u->id = 2;
    $u->save();
    $u->markDaoAsLoaded(false);
    $u->username = 'User';
    $u->id = 5;
    $u->save();
    $u->markDaoAsLoaded(false);
    $u->username = 'Testing';
    $u->id = 8;
    $u->save();

    $this->assertEquals(4, $collection->count());
    $this->assertEquals(1, $collection->min('id'));
    $this->assertEquals(8, $collection->max('id'));
    $this->assertEquals(4, $collection->avg('id'));
    $this->assertEquals(16, $collection->sum('id'));
    $this->assertEquals(
      ["Test", "User", "Testing"],
      array_values($collection->distinct('username'))
    );

    $collection->loadWhere(['username' => 'Test']);
    $this->assertCount(2, $collection);

    $collection = MockQlDao::collection(['username' => 'Test']);
    $this->assertCount(2, $collection->getRawArray());

    $first = MockQlDao::collection(['username' => 'Test'])->first();
    $this->assertInstanceOf(MockQlDao::class, $first);

    $first = MockQlDao::collection(['username' => 'NotExisting'])->first('abc');
    $this->assertEquals('abc', $first);

    $col = MockQlDao::collection(['username' => 'Test'])->limit(10);
    $first = $col->first();
    $this->assertTrue($col->hasClause('LIMIT'));
    $limit = $col->getClause('LIMIT');
    if($limit instanceof LimitClause)
    {
      $this->assertEquals(10, $limit->getLimit());
    }
    $this->assertInstanceOf(MockQlDao::class, $first);

    $col = MockQlDao::collection(['username' => 'Test'])->load();
    $this->assertCount(2, $collection);
    $first = $col->first();
    $this->assertInstanceOf(MockQlDao::class, $first);

    $datastore->getConnection()->runQuery("TRUNCATE " . $u->getTableName());

    Dao::unsetDalResolver();
  }

  public function testDynamicTable()
  {
    $dao = new MockQlDao();
    $dao->setTableName('random3');

    $collection = $dao->getCollection();
    $this->assertEquals(
      "SELECT random3.* FROM random3",
      QueryAssembler::stringify($collection)
    );

    $collection = $dao->getCollection(['X' => 'y']);
    $this->assertEquals(
      'SELECT random3.* FROM random3 WHERE X = "y"',
      QueryAssembler::stringify($collection)
    );
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
    $this->_daos = [];
    $this->_daos[1] = ValueAs::obj(['name' => 'Test', 'id' => 1]);
    $this->_daos[2] = ValueAs::obj(['name' => 'Test', 'id' => 2]);
    $user = new \stdClass();
    $user->name = 'User';
    $user->id = 5;
    $this->_daos[5] = $user;
    $mock = new MockAbstractDao();
    $mock->name = 'Testing';
    $mock->id = 8;
    $this->_daos[8] = $mock;
    $this->_isLoaded = true;
  }
}
