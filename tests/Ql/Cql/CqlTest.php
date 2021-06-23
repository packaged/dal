<?php
namespace Packaged\Dal\Tests\Ql\Cql;

use cassandra\InvalidRequestException;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\Connection\CqlException;
use Packaged\Dal\Exceptions\DalException;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\Cql\CqlConnection;
use Packaged\Dal\Ql\Cql\CqlDao;
use Packaged\Dal\Ql\Cql\CqlDaoCollection;
use Packaged\Dal\Tests\Ql\Cql\Mocks\FailExecuteClient;
use Packaged\Dal\Tests\Ql\Cql\Mocks\FailPrepareClient;
use Packaged\Dal\Tests\Ql\Cql\Mocks\MockCounterCqlDao;
use Packaged\Dal\Tests\Ql\Cql\Mocks\MockCqlCollection;
use Packaged\Dal\Tests\Ql\Cql\Mocks\MockCqlConnection;
use Packaged\Dal\Tests\Ql\Cql\Mocks\MockCqlDao;
use Packaged\Dal\Tests\Ql\Cql\Mocks\MockCqlDataStore;
use Packaged\Dal\Tests\Ql\Cql\Mocks\MockPrefillCqlDao;
use Packaged\Dal\Tests\Ql\Cql\Mocks\UnpreparedExecuteClient;
use Packaged\Dal\Tests\Ql\Cql\Mocks\UnpreparedPrepareClient;
use Packaged\Dal\Tests\Ql\Cql\Mocks\WriteTimeoutClient;
use Packaged\Dal\Tests\Ql\Mocks\MockAbstractQlDataConnection;
use Packaged\QueryBuilder\Builder\QueryBuilder;
use Packaged\QueryBuilder\Expression\ValueExpression;
use Packaged\QueryBuilder\Predicate\EqualPredicate;
use PHPUnit\Framework\TestCase;

class CqlTest extends TestCase
{
  public function testNoKeyspace()
  {
    $datastore = new MockCqlDataStore();
    $connection = new MockCqlConnection();
    $connection->connect();
    $connection->setConfig('keyspace', 'packaged_dal');
    $datastore->setConnection($connection);
    $connection->setResolver(new DalResolver());

    $dao = new MockCqlDao();
    $dao->id = 'test2';
    $dao->id2 = 9876;
    $dao->username = 'daotest';
    $datastore->save($dao);
    $this->assertTrue($datastore->exists($dao));
  }

  public function testBrokenKeyspace()
  {
    $this->expectException(CqlException::class);
    $this->expectExceptionMessage("Keyspace 'broken_keyspace' does not exist");
    $connection = new MockCqlConnection();
    $connection->connect();
    $connection->setConfig('keyspace', 'broken_keyspace');
    $connection->prepare('select * from bad_table');
  }

  public function testInvalidRequestException()
  {
    $connection = new MockCqlConnection();
    $connection->connect();
    $connection->setConfig('keyspace', 'packaged_dal');
    try
    {
      $connection->prepare('SELECT * FROM mock_ql_daos where not_found = 1');
    }
    catch(CqlException $e)
    {
      $this->assertInstanceOf(
        InvalidRequestException::class,
        $e->getPrevious()
      );
    }
  }

  public function testDeletes()
  {
    $connection = new MockCqlConnection();
    $connection->connect();
    $connection->setConfig('keyspace', 'packaged_dal');

    $datastore = new MockCqlDataStore();
    $datastore->setConnection($connection);

    $resolver = new DalResolver();
    $resolver->addDataStore('mockcql', $datastore);
    Dao::setDalResolver($resolver);
    $connection->setResolver($resolver);

    $coll = MockCqlDao::collection();
    $count = $coll->count();
    $first = $coll->first();
    /**
     * @var $first MockCqlDao
     */
    MockCqlDao::collection(['id' => $first->id])->delete();
    $this->assertNotEquals($count, $coll->count());

    $this->expectException(DalException::class);
    $this->expectExceptionMessage('Truncate is not supported');
    $coll->delete();
  }

  protected function _configureConnection(CqlConnection $conn)
  {
    $conn->setReceiveTimeout(5000);
    $conn->setSendTimeout(5000);
    $conn->setConfig('connect_timeout', 1000);
    $conn->setConfig('keyspace', 'packaged_dal');
  }

  public function testConnection()
  {
    $connection = new CqlConnection();
    $connection->setResolver(new DalResolver());
    $this->_configureConnection($connection);
    $this->assertFalse($connection->isConnected());
    $connection->connect();
    $this->assertTrue($connection->isConnected());
    $connection->disconnect();
    $this->assertFalse($connection->isConnected());
  }

  public function testConnectionException()
  {
    $connection = new CqlConnection();
    $config = new ConfigSection();
    $config->addItem('hosts', '255.255.255.255');
    $connection->configure($config);

    $this->expectException(ConnectionException::class);
    $connection->connect();
  }

  public function testLsd()
  {
    $datastore = new MockCqlDataStore();
    $connection = new CqlConnection();
    $this->_configureConnection($connection);
    $datastore->setConnection($connection);
    $connection->connect();
    $connection->setResolver(new DalResolver());

    $dao = new MockCqlDao();
    $dao->id = uniqid('daotest');
    $dao->id2 = 12345;
    $dao->username = time();
    $dao->display = 'User ' . date("Y-m-d");
    $dao->intVal = 123456;
    $dao->bigintVal = -123456;
    $dao->doubleVal = 123456;
    $dao->floatVal = 12.3456;
    $dao->decimalVal = '1.2e3';
    $dao->negDecimalVal = -54.321;
    $dao->timestampVal = strtotime('2015-04-02');
    $dao->boolVal = true;
    $datastore->save($dao);
    $dao->username = 'test 1';
    $dao->display = 'Brooke';
    $datastore->save($dao);
    $dao->username = 'test 2';
    $datastore->load($dao);
    $this->assertEquals('test 1', $dao->username);
    $this->assertEquals(123456, $dao->intVal);
    $this->assertEquals(-123456, $dao->bigintVal);
    $this->assertEquals(123456, $dao->doubleVal);
    $this->assertEquals(12.3456, $dao->floatVal, '', 0.00001);
    $this->assertEquals(1200, $dao->decimalVal);
    $this->assertEquals(-54.321, $dao->negDecimalVal);
    $this->assertEquals(strtotime('2015-04-02'), $dao->timestampVal);
    $this->assertTrue($dao->boolVal);
    $dao->display = 'Save 2';
    $datastore->save($dao);
    $datastore->delete($dao);

    $this->assertEquals(
      ['id' => $dao->id, 'id2' => $dao->getPropertySerialized('id2', 12345)],
      $dao->getId()
    );
  }

  public function testConnectionConfig()
  {
    $connection = new MockCqlConnection();
    $this->_configureConnection($connection);
    $connection->connect();
    $connection->setReceiveTimeout(123);
    $this->assertEquals(123, $connection->getConfig('receive_timeout'));
    $connection->setSendTimeout(123);
    $this->assertEquals(123, $connection->getConfig('send_timeout'));
    $connection->setConfig('connect_timeout', 123);
    $this->assertEquals(123, $connection->getConfig('connect_timeout'));
    $connection->disconnect();
  }

  public function testPrepareException()
  {
    $connection = new MockCqlConnection();
    $this->_configureConnection($connection);
    $connection->connect();
    $this->expectException(CqlException::class);
    $connection->prepare("INVALID");
  }

  public function testGetData()
  {
    $datastore = new MockCqlDataStore();
    $connection = new MockCqlConnection();
    $connection->setConfig('keyspace', 'packaged_dal');
    $datastore->setConnection($connection);
    $connection->setResolver(new DalResolver());

    $dao = new MockCqlDao();
    $dao->id = 'test1';
    $dao->id2 = 5555;
    $dao->username = 'testuser';
    $datastore->save($dao);

    $eq = new EqualPredicate();
    $eq->setField('id');
    $eq->setExpression(ValueExpression::create('test1'));
    $d = $datastore->getData(
      QueryBuilder::select()->from($dao->getTableName())->where($eq)
    );

    $testDao = new MockCqlDao();
    $testDao->hydrateDao($d[0], true);
    $testDao->markDaoAsLoaded();
    $testDao->markDaoDatasetAsSaved();

    $this->assertEquals($dao, $testDao);
  }

  public function testTtl()
  {
    $connection = new MockAbstractQlDataConnection();
    $datastore = new MockCqlDataStore();
    $datastore->setConnection($connection);
    $dao = new MockCqlDao();
    $dao->id = 3;
    $dao->id2 = 1234;
    $dao->username = 'testuser';
    $dao->display = 'latest';
    $dao->setTtl(100);
    $datastore->save($dao);
    $this->assertEquals(
      'INSERT INTO "mock_ql_daos" ("id", "id2", "username", "display") VALUES (?, ?, ?, ?) USING TTL ?',
      $connection->getExecutedQuery()
    );
    $this->assertEquals(
      [
        '3',
        1234,
        'testuser',
        'latest',
        100,
      ],
      $connection->getExecutedQueryValues()
    );

    $dao = new MockCqlDao();
    $dao->id = 'test4';
    $dao->id2 = 4321;
    $dao->username = 'testuser';
    $dao->setTtl(null);
    $datastore->save($dao);
    $this->assertEquals(
      'INSERT INTO "mock_ql_daos" ("id", "id2", "username") VALUES (?, ?, ?)',
      $connection->getExecutedQuery()
    );
    $this->assertEquals(
      [
        'test4',
        4321,
        'testuser',
      ],
      $connection->getExecutedQueryValues()
    );

    $dao->setTtl(101);
    $dao->setTimestamp(123456);
    $dao->username = "test";
    $datastore->save($dao);
    $this->assertEquals(
      'UPDATE "mock_ql_daos" USING TTL ? AND TIMESTAMP ? SET "username" = ? WHERE "id" = ? AND "id2" = ?',
      $connection->getExecutedQuery()
    );
    $this->assertEquals(
      [101, 123456, 'test', 'test4', 4321],
      $connection->getExecutedQueryValues()
    );

    $cqlDao = $this->getMockForAbstractClass(CqlDao::class);
    $this->assertInstanceOf(CqlDao::class, $cqlDao);
    /**
     * @var $cqlDao CqlDao
     */
    $this->assertNull($cqlDao->getTtl());
  }

  public function testCollection()
  {
    $this->assertInstanceOf(CqlDaoCollection::class, MockCqlDao::collection());

    $dataStore = new MockCqlDataStore();
    $connection = new CqlConnection();
    $connection->setConfig('keyspace', 'packaged_dal');
    $dataStore->setConnection($connection);
    $connection->setResolver(new DalResolver());
    $mockDao = new MockCqlDao();
    $mockDao->setDataStore($dataStore);
    $collection = MockCqlCollection::createFromDao($mockDao);
    $data = $collection->getRawArray();

    $collection->clear();
    $this->assertEquals(count($data), $collection->count());

    $this->assertNotEmpty($data);
    $this->assertInstanceOf(MockCqlDao::class, $data[0]);
  }

  public function testCounters()
  {
    $datastore = new MockCqlDataStore();
    $connection = new MockCqlConnection();
    $this->_configureConnection($connection);
    $datastore->setConnection($connection);
    $connection->connect();
    $resolver = new DalResolver();
    $resolver->boot();
    $connection->setResolver($resolver);
    Dao::getDalResolver()->addDataStore('mockcql', $datastore);

    $connection->truncate();

    $dao = new MockCounterCqlDao();
    $dao->id = 'test1';
    $dao->c1->increment(10);
    $dao->c1->decrement(5);
    $datastore->save($dao);
    $dao->c2->increment(1);
    $dao->c2->decrement(3);
    $datastore->save($dao);

    $loaded = MockCounterCqlDao::loadById('test1');
    $this->assertEquals(5, $loaded->c1->calculated());
    $this->assertEquals(-2, $loaded->c2->calculated());

    $dao = new MockCounterCqlDao();
    $dao->id = 'test1';
    $dao->c1->increment(5);
    $dao->c1->decrement(5);
    $datastore->save($dao);
    $dao->c2->increment(0);
    $datastore->save($dao);
  }

  public function testRetries()
  {
    $connection = new MockCqlConnection();
    $connection->setClient(new FailPrepareClient(null));

    try
    {
      $connection->resetCounts();
      $connection->prepare('SOME QUERY');
    }
    catch(CqlException $e)
    {
      $this->assertEquals(3, $connection->getPrepareCount());
      $this->assertEquals(0, $connection->getExecuteCount());
    }

    $connection->setClient(new FailExecuteClient(null));
    try
    {
      $connection->resetCounts();
      $stmt = $connection->prepare('test');
      $connection->execute($stmt);
    }
    catch(CqlException $e)
    {
      $this->assertEquals(1, $connection->getPrepareCount());
      $this->assertEquals(3, $connection->getExecuteCount());
    }

    $connection->setClient(new WriteTimeoutClient(null));
    try
    {
      $connection->resetCounts();
      $stmt = $connection->prepare('test');
      $connection->execute($stmt);
    }
    catch(CqlException $e)
    {
      $this->assertEquals(1, $connection->getPrepareCount());
      $this->assertEquals(3, $connection->getExecuteCount());
    }

    $connection->setClient(new UnpreparedPrepareClient(null));
    try
    {
      $connection->resetCounts();
      $connection->prepare('test');
    }
    catch(CqlException $e)
    {
      $this->assertEquals(3, $connection->getPrepareCount());
      $this->assertEquals(0, $connection->getExecuteCount());
    }

    $connection->setClient(new UnpreparedExecuteClient(null));
    try
    {
      $connection->resetCounts();
      $stmt = $connection->prepare('test');
      $connection->execute($stmt);
    }
    catch(CqlException $e)
    {
      $this->assertEquals(3, $connection->getPrepareCount());
      $this->assertEquals(3, $connection->getExecuteCount());
    }
  }

  public function testSwitchingKeyspace()
  {
    $conn = new CqlConnection();
    $conn->setResolver(new DalResolver());
    $conn->setConfig('persist', true);
    $conn->setConfig('keyspace', 'packaged_dal_switch');
    $conn->connect();
    $conn->runQuery(
      'CREATE TABLE IF NOT EXISTS "test1" ("test_field" text, PRIMARY KEY ("test_field"))'
    );

    $conn1 = new CqlConnection();
    $conn1->setResolver(new DalResolver());
    $conn1->setConfig('persist', true);
    $conn1->setConfig('keyspace', 'packaged_dal');
    $conn1->connect();
    $conn1->runQuery(
      'CREATE TABLE IF NOT EXISTS "test1" ("test_field" text, PRIMARY KEY ("test_field"))'
    );

    $conn2 = new CqlConnection();
    $conn2->setResolver(new DalResolver());
    $conn2->setConfig('persist', true);
    $conn2->setConfig('keyspace', 'packaged_dal_switch');
    $conn2->connect();
    $conn2->runQuery(
      'CREATE TABLE IF NOT EXISTS "test2" ("test_field" text, PRIMARY KEY ("test_field"))'
    );

    $conn1->runQuery('INSERT INTO "test1" ("test_field") VALUES (\'value1\')');
    $conn2->runQuery('INSERT INTO "test2" ("test_field") VALUES (\'value2\')');

    $row1 = $conn1->fetchQueryResults('SELECT * FROM "test1"');
    $this->assertEquals('value1', $row1[0]['test_field']);

    $row2 = $conn2->fetchQueryResults('SELECT * FROM "test2"');
    $this->assertEquals('value2', $row2[0]['test_field']);
  }

  public function testPartialInsert()
  {
    $datastore = new MockCqlDataStore();
    $connection = new CqlQueryObserverConnection();
    $this->_configureConnection($connection);
    $datastore->setConnection($connection);
    $connection->connect();
    $connection->setResolver(new DalResolver());

    $dao = new MockCqlDao();
    $dao->id = uniqid('daotest');
    $dao->id2 = 12345;
    $dao->display = 'test 1';
    $datastore->save($dao);
    self::assertEquals(
      [
        [
          'runQuery',
          'INSERT INTO "mock_ql_daos" ("id", "id2", "display") VALUES (?, ?, ?)',
          [$dao->id, 12345, 'test 1'],
        ],
      ],
      $connection->getQueries()
    );
  }

  public function testAlwaysInsertID()
  {
    $datastore = new MockCqlDataStore();
    $connection = new CqlQueryObserverConnection();
    $this->_configureConnection($connection);
    $datastore->setConnection($connection);
    $connection->connect();
    $connection->setResolver(new DalResolver());

    $dao = new MockCqlDao();
    $dao->id = uniqid('daotest');
    $dao->markDaoDatasetAsSaved();
    $dao->id2 = 12345;
    $dao->display = 'test 2';
    $datastore->save($dao);
    self::assertEquals(
      [
        [
          'runQuery',
          'INSERT INTO "mock_ql_daos" ("id2", "display", "id") VALUES (?, ?, ?)',
          [12345, 'test 2', $dao->id],
        ],
      ],
      $connection->getQueries()
    );
  }

  public function testAlwaysInsertIDWithPrefil()
  {
    $datastore = new MockCqlDataStore();
    $connection = new CqlQueryObserverConnection();
    $this->_configureConnection($connection);
    $datastore->setConnection($connection);
    $connection->connect();
    $connection->setResolver(new DalResolver());

    $dao = new MockPrefillCqlDao();
    $dao->id = uniqid('daotest');
    $dao->id2 = 12345;
    $dao->username = 'abc';
    $datastore->save($dao);
    self::assertEquals(
      [
        [
          'runQuery',
          'INSERT INTO "mock_ql_daos" ("id", "id2", "username", "intVal", "boolVal") VALUES (?, ?, ?, ?, ?)',
          [$dao->id, 12345, 'abc', 1, false],
        ],
      ],
      $connection->getQueries()
    );
  }
}
