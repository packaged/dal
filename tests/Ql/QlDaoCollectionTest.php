<?php
namespace Packaged\Dal\Tests\Ql;

use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Tests\Ql\Mocks\MockQlDao;
use Packaged\Dal\Tests\Ql\Mocks\MockQlDataStore;
use Packaged\Dal\Tests\Ql\PDO\Mocks\MockPdoConnection;
use Packaged\QueryBuilder\Assembler\QueryAssembler;
use Packaged\QueryBuilder\Clause\GroupByClause;
use Packaged\QueryBuilder\Clause\LimitClause;
use PDO;
use PHPUnit\Framework\TestCase;

class QlDaoCollectionTest extends TestCase
{
  public function testHydrated()
  {
    $collection = Mocks\MockQlDaoCollection::create();
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

  public function testEmulatedPrepare()
  {
    $resolver = new DalResolver();
    $resolver->boot();
    $datastore = new MockQlDataStore();
    $connection = new MockPdoConnection();
    $connection->addConfig('options', [PDO::ATTR_EMULATE_PREPARES => true]);
    $connection->config();
    $connection->connect();
    $connection->setResolver($resolver);
    $datastore->setConnection($connection);
    MockQlDao::getDalResolver()->addDataStore('mockql', $datastore);
    $item = MockQlDao::loadOneWhere(['id' => 'y']);
    $this->assertEmpty($item);
    $resolver->shutdown();
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

    $collection->where(['username' => 'Test'])->load();
    $this->assertCount(2, $collection);

    $collection = MockQlDao::collection(['username' => 'Test']);
    $this->assertCount(2, $collection->getRawArray());

    $first = MockQlDao::collection(['username' => 'Test'])->first();
    $this->assertInstanceOf(MockQlDao::class, $first);

    $first = MockQlDao::collection(['username' => 'NotExisting'])->first('abc');
    $this->assertEquals('abc', $first);

    $count = MockQlDao::collection(['username' => 'NotExisting'])
      ->orderBy('username')->count();
    $this->assertEquals(0, $count);

    $col = MockQlDao::collection(['username' => 'Test'])->limit(10);
    $first = $col->first();
    $this->assertTrue($col->hasClause('LIMIT'));
    $limit = $col->getClause('LIMIT');
    if($limit instanceof LimitClause)
    {
      $this->assertEquals(10, $limit->getLimit()->getValue());
    }
    $this->assertInstanceOf(MockQlDao::class, $first);

    $col = MockQlDao::collection(['username' => 'Test'])->load();
    $this->assertCount(2, $collection);
    $first = $col->first();
    $this->assertInstanceOf(MockQlDao::class, $first);

    $countCollection = MockQlDao::collection();
    $countCollection->limit(1);
    $this->assertEquals(1, $countCollection->count());
    $countCollection->limit(0);
    $this->assertEquals(0, $countCollection->count());

    $datastore->getConnection()->runQuery("TRUNCATE " . $u->getTableName());

    Dao::unsetDalResolver();
  }

  public function testGroupedCount()
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

    $u = new MockQlDao();
    $u->boolTest = true;
    $u->save();
    $u = new MockQlDao();
    $u->boolTest = true;
    $u->save();
    $u = new MockQlDao();
    $u->boolTest = false;
    $u->save();

    $group = new GroupByClause();
    $group->addField('boolTest');
    $this->assertEquals(2, MockQlDao::collection()->addClause($group)->count());
  }

  public function testLimitCount()
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

    $u = new MockQlDao();
    $u->boolTest = true;
    $u->save();
    $u = new MockQlDao();
    $u->boolTest = true;
    $u->save();
    $u = new MockQlDao();
    $u->boolTest = false;
    $u->save();

    $connection->setResolver(MockQlDao::getDalResolver());

    $this->assertEquals(2, MockQlDao::collection()->limit(2)->count());
  }

  public function testGroupLimitCount()
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

    $u = new MockQlDao();
    $u->username = 'user1';
    $u->save();
    $u = new MockQlDao();
    $u->username = 'user2';
    $u->save();
    $u = new MockQlDao();
    $u->username = 'user2';
    $u->save();
    $u = new MockQlDao();
    $u->username = 'user3';
    $u->save();

    $connection->setResolver(MockQlDao::getDalResolver());

    $this->assertEquals(3, MockQlDao::collection()->limit(3)->count());

    $group = new GroupByClause();
    $group->addField('username');
    $this->assertEquals(2, MockQlDao::collection()->addClause($group)->limit(2)->count());
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
