<?php
namespace Ql;

use Cassandra\Exception\InvalidQueryException;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\DataTypes\Counter;
use Packaged\Dal\Exceptions\DalException;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\Cql\CqlDao;
use Packaged\Dal\Ql\Cql\CqlDataStore;
use Packaged\Dal\Ql\Cql\DSCqlConnection;
use Packaged\Dal\Ql\IQLDataConnection;
use Packaged\QueryBuilder\Builder\QueryBuilder;
use Packaged\QueryBuilder\Expression\ValueExpression;
use Packaged\QueryBuilder\Predicate\EqualPredicate;

require_once 'supporting.php';

class DSCqlTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @var DSCqlConnection
   */
  private static $_connection;

  public static function setUpBeforeClass()
  {
    self::$_connection = new DSCqlConnection();
    self::$_connection->setResolver(new DalResolver());
    self::$_connection->setRequestTimeout(10000);
    self::$_connection->setConnectTimeout(10000);
    self::$_connection->connect();
    self::$_connection->runQuery(
      "CREATE KEYSPACE IF NOT EXISTS packaged_dal_ds WITH REPLICATION = "
      . "{'class' : 'SimpleStrategy','replication_factor' : 1};"
    );
    self::$_connection->runQuery(
      'DROP TABLE IF EXISTS packaged_dal_ds.mock_ds_ql_daos'
    );
    self::$_connection->runQuery(
      'CREATE TABLE packaged_dal_ds.mock_ds_ql_daos ('
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
      'DROP TABLE IF EXISTS packaged_dal_ds.mock_ds_counter_daos'
    );
    self::$_connection->runQuery(
      'CREATE TABLE packaged_dal_ds.mock_ds_counter_daos ('
      . '"id" varchar PRIMARY KEY,'
      . '"c1" counter,'
      . '"c2" counter,'
      . ');'
    );
  }

  /*public function testStuff()
  {
    $connection = new DSCqlConnection();
    $connection->connect();
    $connection->runQuery(
      'SELECT * FROM system_schema.keyspaces WHERE keyspace_name=?',
      ['orgtest1234abcde']
    );
  }*/

  public function testNoKeyspace()
  {
    $datastore = new MockDSCqlDataStore();
    $connection = new DSCqlConnection();
    $connection->connect();
    $connection->setConfig('keyspace', 'packaged_dal_ds');
    $datastore->setConnection($connection);
    $connection->setResolver(new DalResolver());

    $dao = new MockDSCqlDao();
    $dao->id = 'test2';
    $dao->id2 = 9876;
    $dao->username = 'daotest';
    $datastore->save($dao);
    $this->assertTrue($datastore->exists($dao));
  }

  /**
   * @expectedException \Cassandra\Exception\RuntimeException
   * @expectedExceptionMessage Keyspace 'broken_keyspace' does not exist
   */
  public function testBrokenKeyspace()
  {
    $connection = new DSCqlConnection();
    $connection->connect();
    $connection->setConfig('keyspace', 'broken_keyspace');
    $connection->fetchQueryResults('select * from bad_table');
  }

  public function testInvalidRequestException()
  {
    $connection = new DSCqlConnection();
    $connection->connect();
    $connection->setConfig('keyspace', 'packaged_dal_ds');

    $this->setExpectedException(InvalidQueryException::class);
    $connection->fetchQueryResults(
      'SELECT * FROM mock_ds_ql_daos where not_found = 1'
    );
  }

  public function testDeletes()
  {
    $connection = new DSCqlConnection();
    $connection->connect();
    $connection->setConfig('keyspace', 'packaged_dal_ds');

    $datastore = new MockDSCqlDataStore();
    $datastore->setConnection($connection);

    $resolver = new DalResolver();
    $resolver->addDataStore('mockdscql', $datastore);
    Dao::setDalResolver($resolver);
    $connection->setResolver($resolver);

    $coll = MockDSCqlDao::collection();
    $count = $coll->count();
    $first = $coll->first();
    /**
     * @var $first MockDSCqlDao
     */
    MockDSCqlDao::collection(['id' => $first->id])->delete();
    $this->assertNotEquals($count, $coll->count());

    $this->setExpectedException(
      DalException::class,
      'Truncate is not supported'
    );
    $coll->delete();
  }

  protected function _configureConnection(DSCqlConnection $conn)
  {
    $conn->setRequestTimeout(5000);
    $conn->setConnectTimeout(1000);
    $conn->setConfig('keyspace', 'packaged_dal_ds');
  }

  public function testConnection()
  {
    $connection = new DSCqlConnection();
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
    $connection = new DSCqlConnection();
    $config = new ConfigSection();
    $config->addItem('hosts', '255.255.255.255');
    $connection->configure($config);

    $this->setExpectedException(
      '\Cassandra\Exception\RuntimeException',
      'No hosts available for the control connection'
    );
    $connection->connect();
  }

  public function testLsd()
  {
    $datastore = new MockDSCqlDataStore();
    $connection = new DSCqlConnection();
    $this->_configureConnection($connection);
    $datastore->setConnection($connection);
    $connection->connect();
    $connection->setResolver(new DalResolver());

    $dao = new MockDSCqlDao();
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

  /*
  public function testPrepareException()
  {
    $connection = new DSCqlConnection();
    $this->_configureConnection($connection);
    $connection->connect();
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\Connection\CqlException'
    );
    $connection->prepare("INVALID");
  }
  */

  public function testGetData()
  {
    $datastore = new MockDSCqlDataStore();
    $connection = new DSCqlConnection();
    $connection->setConfig('keyspace', 'packaged_dal_ds');
    $datastore->setConnection($connection);
    $connection->setResolver(new DalResolver());

    $dao = new MockDSCqlDao();
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

    $testDao = new MockDSCqlDao();
    $testDao->hydrateDao($d[0], true);
    $testDao->markDaoAsLoaded();
    $testDao->markDaoDatasetAsSaved();

    $this->assertEquals($dao, $testDao);
  }

  public function testTtl()
  {
    $connection = new MockAbstractQlDataConnection();
    $datastore = new MockDSCqlDataStore();
    $datastore->setConnection($connection);
    $dao = new MockDSCqlDao();
    $dao->id = 3;
    $dao->id2 = 1234;
    $dao->username = 'testuser';
    $dao->setTtl(100);
    $datastore->save($dao);
    $this->assertEquals(
      'INSERT INTO "mock_ds_ql_daos" ("id", "id2", "username", "display", "intVal", "bigintVal", "doubleVal", "floatVal", "negDecimalVal", "decimalVal", "timestampVal", "boolVal") '
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

    $dao = new MockDSCqlDao();
    $dao->id = 'test4';
    $dao->id2 = 4321;
    $dao->username = 'testuser';
    $dao->setTtl(null);
    $datastore->save($dao);
    $this->assertEquals(
      'INSERT INTO "mock_ds_ql_daos" ("id", "id2", "username", "display", "intVal", "bigintVal", "doubleVal", "floatVal", "negDecimalVal", "decimalVal", "timestampVal", "boolVal") '
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
      'UPDATE "mock_ds_ql_daos" USING TTL ? AND TIMESTAMP ? SET "username" = ? WHERE "id" = ? AND "id2" = ?',
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

  public function testCounters()
  {
    $datastore = new MockDSCqlDataStore();
    $connection = new DSCqlConnection();
    $this->_configureConnection($connection);
    $datastore->setConnection($connection);
    $connection->connect();
    $resolver = new DalResolver();
    $resolver->boot();
    $connection->setResolver($resolver);
    Dao::getDalResolver()->addDataStore('mockdscql', $datastore);

    $dao = new MockDSCounterCqlDao();
    $dao->id = 'test1';
    $dao->c1->increment(10);
    $dao->c1->decrement(5);
    $datastore->save($dao);
    $dao->c2->increment(1);
    $dao->c2->decrement(3);
    $datastore->save($dao);

    $loaded = MockDSCounterCqlDao::loadById('test1');
    $this->assertEquals(5, $loaded->c1->calculated());
    $this->assertEquals(-2, $loaded->c2->calculated());

    $dao = new MockDSCounterCqlDao();
    $dao->id = 'test1';
    $dao->c1->increment(5);
    $dao->c1->decrement(5);
    $datastore->save($dao);
    $dao->c2->increment(0);
    $datastore->save($dao);
  }

  public function testSwitchingKeyspace()
  {
    self::$_connection->runQuery(
      "CREATE KEYSPACE IF NOT EXISTS dal_ds_keyspace1 WITH REPLICATION = "
      . "{'class' : 'SimpleStrategy','replication_factor' : 1};"
    );
    self::$_connection->runQuery(
      "CREATE KEYSPACE IF NOT EXISTS dal_ds_keyspace2 WITH REPLICATION = "
      . "{'class' : 'SimpleStrategy','replication_factor' : 1};"
    );
    $conn1 = new DSCqlConnection();
    $conn1->setResolver(new DalResolver());
    $conn1->setConfig('persist', true);
    $conn1->setConfig('keyspace', 'dal_ds_keyspace1');
    $conn1->connect();
    $conn1->runQuery(
      'CREATE TABLE IF NOT EXISTS "test1" ("test_field" text, PRIMARY KEY ("test_field"))'
    );

    $conn2 = new DSCqlConnection();
    $conn2->setResolver(new DalResolver());
    $conn2->setConfig('persist', true);
    $conn2->setConfig('keyspace', 'dal_ds_keyspace2');
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

class MockDSCqlDataStore extends CqlDataStore
{
  public function setConnection(IQLDataConnection $connection)
  {
    $this->_connection = $connection;
    return $this;
  }
}

class MockDSCqlDao extends CqlDao
{
  protected $_dataStoreName = 'mockdscql';
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
    return "mock_ds_ql_daos";
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

class MockDSCounterCqlDao extends CqlDao
{
  protected $_dataStoreName = 'mockdscql';
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

  protected $_tableName = 'mock_ds_counter_daos';

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
