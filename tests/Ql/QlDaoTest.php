<?php
namespace Packaged\Dal\Tests\Ql;

use Exception;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException;
use Packaged\Dal\Exceptions\DalResolver\DataStoreNotFoundException;
use Packaged\Dal\Exceptions\Dao\MultipleDaoException;
use Packaged\Dal\Exceptions\DataStore\DaoNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\Exceptions\DataStore\TooManyResultsException;
use Packaged\Dal\Ql\QlDataStore;
use Packaged\Dal\Tests\Ql\Mocks\MockConnectionInterface;
use Packaged\Dal\Tests\Ql\Mocks\MockCounterDao;
use Packaged\Dal\Tests\Ql\Mocks\MockNonUniqueKeyDao;
use Packaged\Dal\Tests\Ql\Mocks\MockQlDao;
use Packaged\Dal\Tests\Ql\Mocks\MockQlDataStore;
use Packaged\Dal\Tests\Ql\Mocks\MySQLi\MockMySQLiConnection;
use Packaged\Dal\Tests\Ql\PDO\Mocks\MockPdoConnection;
use Packaged\QueryBuilder\Assembler\QueryAssembler;
use PHPUnit\Framework\TestCase;

class QlDaoTest extends TestCase
{
  protected function _getResolver($name, $store, $connection)
  {
    $resolver = (new DalResolver())->addDataStore($name, $store);
    $store->setConnection($connection);
    return $resolver;
  }

  public static function connectionProvider()
  {
    $pdo = new MockPdoConnection();
    $store = new MockQlDataStore();
    $pdo->config();
    $resolver = $this->_getResolver('mockql', $store, $pdo);
    $pdo->setResolver($resolver);
    yield 'pdo' => [$pdo, $store, $resolver];

    $store = new MockQlDataStore();
    $mysqli = new MockMySQLiConnection();
    $mysqli->config();
    $resolver = $this->_getResolver('mockql', $store, $mysqli);
    $mysqli->setResolver($resolver);
    yield 'mysqli' => [$mysqli, $store->setConnection($mysqli), $resolver];
  }

  /**
   * @dataProvider connectionProvider
   *
   * @param MockConnectionInterface $connection
   *
   * @param QlDataStore             $dataStore
   * @param DalResolver             $resolver
   *
   * @throws MultipleDaoException
   */
  public function testStatics(MockConnectionInterface $connection, QlDataStore $dataStore, DalResolver $resolver)
  {
    $connection->truncate();
    $resolver->boot();

    $collection = MockQlDao::collection();
    $this->assertInstanceOf(MockQlDao::class, $collection->createNewDao());

    $collection = MockQlDao::collection(['name' => 'Test']);
    $this->assertInstanceOf(MockQlDao::class, $collection->createNewDao());
    $this->assertEquals(
      'SELECT mock_ql_daos.* FROM mock_ql_daos WHERE name = "Test"',
      QueryAssembler::stringify($collection->getQuery())
    );

    $username = uniqid('TEST');
    $u = new MockQlDao();
    $u->username = $username;
    $u->display = 'Test One';
    $u->boolTest = true;
    $u->save();
    // save again to ensure no query is made
    $u->save();
    $mocks = MockQlDao::collection(['username' => $username]);
    $this->assertCount(1, $mocks);

    foreach(MockQlDao::each(['username' => $username]) as $usr)
    {
      $this->assertInstanceOf(MockQlDao::class, $usr);
    }

    $preloaded = MockQlDao::collection(['username' => $username])->load();
    $this->assertCount(1, $preloaded);

    $u2 = new MockQlDao();
    $u2->username = 'Tester';
    $u2->display = 'Test One';
    $u2->save();

    try
    {
      $msg = null;
      MockQlDao::loadOneWhere(['display' => 'Test One']);
    }
    catch(Exception $e)
    {
      $msg = $e->getMessage();
    }
    $this->assertEquals(
      "Multiple Objects were located when trying to load one",
      $msg
    );

    $u2Test = MockQlDao::loadOneWhere(['username' => 'Tester']);
    $this->assertEquals($u2->username, $u2Test->username);

    $this->assertNull(MockQlDao::loadOneWhere(['username' => 'Missing']));

    $u->delete();
    $u2->delete();

    $resolver->shutdown();
  }

  /**
   * @dataProvider connectionProvider
   *
   * @param MockConnectionInterface $connection
   *
   * @param QlDataStore             $datastore
   * @param DalResolver             $resolver
   *
   * @throws DaoNotFoundException
   */
  public function testMultipleExistsFailure(
    MockConnectionInterface $connection, QlDataStore $datastore, DalResolver $resolver
  )
  {
    $connection->truncate();
    $resolver->boot();
    $this->expectExceptionMessage("Too many results located");
    $this->expectException(TooManyResultsException::class);

    $u1 = $connection->getMockDao();
    $u1->username = 'TestMultiple';
    $u1->display = 'Test One';
    $u1->save();

    $u2 = new MockQlDao();
    $u2->username = 'TestMultiple';
    $u2->display = 'Test Two';
    $u2->save();

    $test = new MockNonUniqueKeyDao();
    $test->username = 'TestMultiple';
    $test->exists();

    MockNonUniqueKeyDao::loadById('TestMultiple');
    $resolver->shutdown();
  }

  /**
   * @dataProvider connectionProvider
   *
   * @param MockConnectionInterface $connection
   * @param QlDataStore             $datastore
   *
   * @param DalResolver             $resolver
   *
   * @throws ConnectionNotFoundException
   * @throws DataStoreNotFoundException
   */
  public function testDatastoreAutoConstruct(
    MockConnectionInterface $connection, QlDataStore $datastore, DalResolver $resolver
  )
  {
    $connection->truncate();
    $resolver->boot();
    $resolver->addConnection('mockql', $connection);

    $mock = $connection->getMockDao();
    $this->assertSame($connection, $mock->getDataStore()->getConnection());

    $resolver->shutdown();
  }

  public function testNoDataStore()
  {
    $this->expectException(DataStoreNotFoundException::class);
    $resolver = new DalResolver();
    $resolver->boot();

    $mock = new MockQlDao();
    $mock->getDataStore();
  }

  /**
   * @dataProvider connectionProvider
   *
   * @param MockConnectionInterface $connection
   * @param QlDataStore             $datastore
   *
   * @param DalResolver             $resolver
   *
   * @throws MultipleDaoException
   */
  public function testChangeKey(MockConnectionInterface $connection, QlDataStore $datastore, DalResolver $resolver)
  {
    $connection->truncate();
    $resolver->boot();

    $dao = $connection->getMockDao();
    $dao->username = 'Test One';
    $dao->display = 'Test One';
    $dao->save();

    $oldId = $dao->id;

    $dao->id = 999;
    $dao->save();

    $this->assertEquals(999, $dao->id);

    $loadDao = MockQlDao::loadOneWhere(['id' => 999]);
    $this->assertInstanceOf(MockQlDao::class, $loadDao);
    $this->assertEquals(999, $loadDao->id);

    // check old id gone
    $missingDao = MockQlDao::loadOneWhere(['id' => $oldId]);
    $this->assertNull($missingDao);
    $resolver->shutdown();
  }

  /**
   * @dataProvider connectionProvider
   *
   * @param MockConnectionInterface $connection
   * @param QlDataStore             $datastore
   * @param DalResolver             $resolver
   *
   * @throws ConnectionNotFoundException
   * @throws DaoNotFoundException
   * @throws ConnectionException
   * @throws DataStoreException
   */
  public function testCounters(MockConnectionInterface $connection, QlDataStore $datastore, DalResolver $resolver)
  {
    $resolver->boot();
    $connection->truncate();

    $dao = MockCounterDao::loadOrNew('test1');
    $dao->c1->increment(10);
    $dao->c1->decrement(5);
    $datastore->save($dao);
    $dao->c2->increment(1);
    $dao->c2->decrement(3);
    $datastore->save($dao);

    $dao = new MockCounterDao();
    $dao->id = 'test1';
    $dao->c2->increment(1);
    $dao->c2->decrement(3);
    $datastore->save($dao);

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(5, $dao->c1->calculated());
    $this->assertEquals(-4, $dao->c2->calculated());

    $dao = MockCounterDao::loadById('test1');
    $dao->c1->increment(0);
    $datastore->save($dao);
    $dao->c1->increment(10);
    $datastore->save($dao);
    $dao->c1->increment(15);
    $datastore->save($dao);
    $dao->c3->increment(8);
    $datastore->save($dao);
    $dao->c3->increment(9.7);
    $datastore->save($dao);
    $dao->c3->increment(99);
    $datastore->save($dao);
    $dao->c3->increment(1.3);
    $datastore->save($dao);
    $dao->c3->increment(0.0);
    $datastore->save($dao);
    $dao->c3->increment(null);
    $datastore->save($dao);
    $dao->c3->increment('goat');
    $datastore->save($dao);
    $dao->c3->increment(
      '99
 a'
    );
    $datastore->save($dao);
    $dao->c2->increment(0);
    $datastore->save($dao);

    $dao1 = MockCounterDao::loadById('test1');
    $dao1->c1->increment(7);
    $dao2 = MockCounterDao::loadById('test1');
    $dao2->c1->increment(3);
    $dao1->save();
    $dao2->save();

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(40, $dao->c1->calculated());

    $this->assertEquals(217, $dao->c3->calculated());
    $dao = new MockCounterDao();
    $dao->id = 'test1';
    $dao->markDaoAsLoaded();
    $dao->markDaoDatasetAsSaved();
    $dao->c3->increment('0.00');
    $datastore->save($dao);

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(217, $dao->c3->calculated());

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(40, $dao->c1->calculated());
    $this->assertEquals(-4, $dao->c2->calculated());
    $this->assertEquals(217, $dao->c3->calculated());

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(40, $dao->c1->calculated());

    $dao->c1 = 100;
    $dao->save();

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(100, $dao->c1->calculated());

    $dao->c1->setValue(500);
    $dao->save();

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(500, $dao->c1->calculated());

    $json = json_encode($dao);
    $this->assertEquals(
      '{"id":"test1","c1":"500","c2":"-4","c3":"217.00"}',
      $json
    );

    $dao = new MockCounterDao();
    $dao->id = 'test1';
    $dao->c1->setValue(6);
    $dao->c2->setValue(-8);
    $datastore->save($dao);

    $json = json_encode($dao);
    $this->assertEquals('{"id":"test1","c1":"6","c2":"-8","c3":"0"}', $json);

    $resolver->shutdown();
  }

  /**
   * @dataProvider connectionProvider
   *
   * @param MockConnectionInterface $connection
   * @param QlDataStore             $datastore
   * @param DalResolver             $resolver
   */
  public function testUnusedCounter(MockConnectionInterface $connection, QlDataStore $datastore, DalResolver $resolver)
  {
    $resolver->boot();
    $connection->truncate();

    $dao = new MockCounterDao();
    $dao->id = "novalue";
    $this->assertArrayNotHasKey('c1', $dao->save());
    $dao->c1->increment(1)->decrement(1);
    $this->assertArrayHasKey('c1', $dao->save());

    $resolver->shutdown();
  }
}
