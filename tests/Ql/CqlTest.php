<?php
namespace Tests\Ql;

use cassandra\CassandraClient;
use cassandra\Compression;
use cassandra\ConsistencyLevel;
use cassandra\CqlPreparedResult;
use cassandra\InvalidRequestException;
use cassandra\TimedOutException;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\DataTypes\Counter;
use Packaged\Dal\Exceptions\Connection\CqlException;
use Packaged\Dal\Exceptions\DalException;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\Cql\CqlConnection;
use Packaged\Dal\Ql\Cql\CqlDao;
use Packaged\Dal\Ql\Cql\CqlDaoCollection;
use Packaged\Dal\Ql\Cql\CqlDataStore;
use Packaged\Dal\Ql\Cql\CqlStatement;
use Packaged\Dal\Ql\IQLDataConnection;
use Packaged\QueryBuilder\Builder\QueryBuilder;
use Packaged\QueryBuilder\Expression\ValueExpression;
use Packaged\QueryBuilder\Predicate\EqualPredicate;
use Tests\Ql\Mocks\MockAbstractQlDataConnection;
use Thrift\Exception\TTransportException;

class CqlTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @var CqlConnection
   */
  private static $_connection;

  public static function setUpBeforeClass()
  {
    self::$_connection = new CqlConnection();
    self::$_connection->setConfig('connect_timeout', 10000);
    self::$_connection->setConfig('receive_timeout', 10000);
    self::$_connection->setConfig('send_timeout', 10000);

    self::$_connection->setResolver(new DalResolver());
    self::$_connection->connect();
    self::$_connection->runQuery(
      "CREATE KEYSPACE IF NOT EXISTS packaged_dal WITH REPLICATION = "
      . "{'class' : 'SimpleStrategy','replication_factor' : 1};"
    );
    self::$_connection->runQuery(
      'DROP TABLE IF EXISTS packaged_dal.mock_ql_daos'
    );
    self::$_connection->runQuery(
      'CREATE TABLE packaged_dal.mock_ql_daos ('
      . '"id" varchar,'
      . '"id2" int,'
      . '"username" varchar,'
      . '"display" varchar,'
      . '"intVal" int,'
      . '"bigintVal" bigint,'
      . '"doubleVal" double,'
      . '"floatVal" float,'
      . '"decimalVal" decimal,'
      . '"negDecimalVal" decimal,'
      . '"timestampVal" timestamp,'
      . '"boolVal" boolean,'
      . ' PRIMARY KEY ((id), id2));'
    );
    self::$_connection->runQuery(
      'DROP TABLE IF EXISTS packaged_dal.mock_counter_daos'
    );
    self::$_connection->runQuery(
      'CREATE TABLE packaged_dal.mock_counter_daos ('
      . '"id" varchar PRIMARY KEY,'
      . '"c1" counter,'
      . '"c2" counter,'
      . ');'
    );
  }

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

  /**
   * @expectedException \Packaged\Dal\Exceptions\Connection\CqlException
   * @expectedExceptionMessage Keyspace 'broken_keyspace' does not exist
   */
  public function testBrokenKeyspace()
  {
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

    $this->setExpectedException(
      DalException::class,
      'Truncate is not supported'
    );
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

    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\Connection\ConnectionException'
    );
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
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\Connection\CqlException'
    );
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
    $dao->setTtl(100);
    $datastore->save($dao);
    $this->assertEquals(
      'INSERT INTO "mock_ql_daos" ("id", "id2", "username", "display", "intVal", "bigintVal", "doubleVal", "floatVal", "negDecimalVal", "decimalVal", "timestampVal", "boolVal") '
      . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) USING TTL ?',
      $connection->getExecutedQuery()
    );
    $this->assertEquals(
      [
        '3',
        1234,
        'testuser',
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
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
      'INSERT INTO "mock_ql_daos" ("id", "id2", "username", "display", "intVal", "bigintVal", "doubleVal", "floatVal", "negDecimalVal", "decimalVal", "timestampVal", "boolVal") '
      . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      $connection->getExecutedQuery()
    );
    $this->assertEquals(
      [
        'test4',
        4321,
        'testuser',
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
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
    $connection = new CqlConnection();
    $this->_configureConnection($connection);
    $datastore->setConnection($connection);
    $connection->connect();
    $resolver = new DalResolver();
    $resolver->boot();
    $connection->setResolver($resolver);
    Dao::getDalResolver()->addDataStore('mockcql', $datastore);

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
    self::$_connection->runQuery(
      "CREATE KEYSPACE IF NOT EXISTS dal_keyspace1 WITH REPLICATION = "
      . "{'class' : 'SimpleStrategy','replication_factor' : 1};"
    );
    self::$_connection->runQuery(
      "CREATE KEYSPACE IF NOT EXISTS dal_keyspace2 WITH REPLICATION = "
      . "{'class' : 'SimpleStrategy','replication_factor' : 1};"
    );
    $conn1 = new CqlConnection();
    $conn1->setResolver(new DalResolver());
    $conn1->setConfig('persist', true);
    $conn1->setConfig('keyspace', 'dal_keyspace1');
    $conn1->connect();
    $conn1->runQuery(
      'CREATE TABLE IF NOT EXISTS "test1" ("test_field" text, PRIMARY KEY ("test_field"))'
    );

    $conn2 = new CqlConnection();
    $conn2->setResolver(new DalResolver());
    $conn2->setConfig('persist', true);
    $conn2->setConfig('keyspace', 'dal_keyspace2');
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
}

class MockCqlCollection extends CqlDaoCollection
{
  public static function createFromDao(CqlDao $dao)
  {
    $collection = parent::create(get_class($dao));
    $collection->_dao = $dao;
    return $collection;
  }
}

class MockCqlStatement extends CqlStatement
{
  public function setStatement($stmt)
  {
    $this->_isPrepared = true;
    $this->_rawStatement = $stmt;
  }
}

class MockCqlConnection extends CqlConnection
{
  protected $_executeCount = 0;
  protected $_prepareCount = 0;

  protected $_newClient = null;

  public function getConfig($item)
  {
    return $this->_config()->getItem($item);
  }

  public function connect()
  {
    if($this->_newClient)
    {
      $this->_client = $this->_newClient;
    }
    return parent::connect();
  }

  public function setClient($client)
  {
    $this->_newClient = $client;
    $this->disconnect()->connect();
  }

  public function execute(
    CqlStatement $statement, array $parameters = [],
    $consistency = ConsistencyLevel::QUORUM, $retries = null
  )
  {
    $this->_executeCount++;
    return parent::execute($statement, $parameters, $consistency, $retries);
  }

  public function getExecuteCount()
  {
    return $this->_executeCount;
  }

  public function prepare(
    $query, $compression = Compression::NONE, $retries = null
  )
  {
    $this->_prepareCount++;
    return parent::prepare($query, $compression, $retries);
  }

  public function getPrepareCount()
  {
    return $this->_prepareCount;
  }

  public function resetCounts()
  {
    $this->_prepareCount = 0;
    $this->_executeCount = 0;
  }
}

class MockCqlDataStore extends CqlDataStore
{
  public function setConnection(IQLDataConnection $connection)
  {
    $this->_connection = $connection;
    return $this;
  }
}

class MockCqlDao extends CqlDao
{
  protected $_dataStoreName = 'mockcql';
  protected $_ttl;
  protected $_timestamp;

  public $id;
  public $id2;
  public $username;
  public $display;
  public $intVal;
  public $bigintVal;
  public $doubleVal;
  public $floatVal;
  public $negDecimalVal;
  public $decimalVal;
  public $timestampVal;
  public $boolVal;

  protected $_dataStore;

  public function getDaoIDProperties()
  {
    return ['id', 'id2'];
  }

  public function getTableName()
  {
    return "mock_ql_daos";
  }

  public function getTtl()
  {
    return $this->_ttl;
  }

  public function setTtl($ttl)
  {
    $this->_ttl = $ttl;
    return $this;
  }

  public function getTimestamp()
  {
    return $this->_timestamp;
  }

  public function setTimestamp($timestamp)
  {
    $this->_timestamp = $timestamp;
    return $this;
  }

  public function setDataStore(CqlDataStore $store)
  {
    $this->_dataStore = $store;
    return $this;
  }

  public function getDataStore()
  {
    if($this->_dataStore === null)
    {
      return parent::getDataStore();
    }
    return $this->_dataStore;
  }
}

class MockCounterCqlDao extends CqlDao
{
  protected $_dataStoreName = 'mockcql';
  protected $_ttl;

  public $id;
  /**
   * @counter
   * @var Counter
   */
  public $c1;
  /**
   * @counter
   * @var Counter
   */
  public $c2;

  protected $_dataStore;

  protected $_tableName = 'mock_counter_daos';

  public function getTableName()
  {
    return $this->_tableName;
  }

  public function setTableName($table)
  {
    $this->_tableName = $table;
    return $this;
  }

  public function getTtl()
  {
    return $this->_ttl;
  }

  public function setTtl($ttl)
  {
    $this->_ttl = $ttl;
    return $this;
  }

  public function setDataStore(CqlDataStore $store)
  {
    $this->_dataStore = $store;
    return $this;
  }

  public function getDataStore()
  {
    if($this->_dataStore === null)
    {
      return parent::getDataStore();
    }
    return $this->_dataStore;
  }
}

class FailPrepareClient extends CassandraClient
{
  public function prepare_cql3_query($query, $compression)
  {
    throw new TimedOutException();
  }
}

class FailExecuteClient extends CassandraClient
{
  public function prepare_cql3_query($query, $compression)
  {
    return new CqlPreparedResult();
  }

  public function execute_prepared_cql3_query(
    $itemId, array $values, $consistency
  )
  {
    throw new TTransportException('Class: timed out reading 123 bytes');
  }
}

class WriteTimeoutClient extends CassandraClient
{
  public function prepare_cql3_query($query, $compression)
  {
    return new CqlPreparedResult();
  }

  public function execute_prepared_cql3_query(
    $itemId, array $values, $consistency
  )
  {
    throw new TimedOutException();
  }
}

class UnpreparedExecuteClient extends CassandraClient
{
  public function prepare_cql3_query($query, $compression)
  {
    return new CqlPreparedResult();
  }

  public function execute_prepared_cql3_query(
    $itemId, array $values, $consistency
  )
  {
    throw new \Exception(
      'Prepared query with ID 1 not found (either the query was not prepared on this host (maybe the host has been restarted?) or you have prepared too many queries and it has been evicted from the internal cache)'
    );
  }
}

class UnpreparedPrepareClient extends CassandraClient
{
  public function prepare_cql3_query($query, $compression)
  {
    throw new \Exception(
      'Prepared query with ID 1 not found (either the query was not prepared on this host (maybe the host has been restarted?) or you have prepared too many queries and it has been evicted from the internal cache)'
    );
  }
}
