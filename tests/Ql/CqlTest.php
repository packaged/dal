<?php
namespace Ql;

use cassandra\CqlPreparedResult;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Ql\Cql\CqlConnection;
use Packaged\Dal\Ql\Cql\CqlDao;
use Packaged\Dal\Ql\Cql\CqlDaoCollection;
use Packaged\Dal\Ql\Cql\CqlDataStore;
use Packaged\Dal\Ql\IQLDataConnection;
use Packaged\QueryBuilder\Builder\QueryBuilder;
use Packaged\QueryBuilder\Expression\ValueExpression;
use Packaged\QueryBuilder\Predicate\EqualPredicate;

require_once 'supporting.php';

class CqlTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @var CqlConnection
   */
  private static $_connection;

  public static function setUpBeforeClass()
  {
    self::$_connection = new CqlConnection();
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
      . '"id" varchar PRIMARY KEY,'
      . '"username" varchar,'
      . '"display" varchar,'
      . '"intVal" int,'
      . '"bigintVal" bigint,'
      . '"doubleVal" double,'
      . '"floatVal" float,'
      . '"boolVal" boolean'
      . ');'
    );
  }

  public function testNoKeyspace()
  {
    $datastore  = new MockCqlDataStore();
    $connection = new MockCqlConnection();
    $connection->connect();
    $connection->setConfig('keyspace', 'packaged_dal');
    $datastore->setConnection($connection);

    $dao     = new MockCqlDao();
    $dao->id = 'testdao';
    $datastore->save($dao);
    $this->assertTrue($datastore->exists($dao));
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
    $config     = new ConfigSection();
    $config->addItem('hosts', '255.255.255.255');
    $connection->configure($config);

    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\Connection\ConnectionException'
    );
    $connection->connect();
  }

  public function testLsd()
  {
    $datastore  = new MockCqlDataStore();
    $connection = new CqlConnection();
    $this->_configureConnection($connection);
    $datastore->setConnection($connection);
    $connection->connect();

    $dao            = new MockCqlDao();
    $dao->username  = time() . 'user';
    $dao->display   = 'User ' . date("Y-m-d");
    $dao->intVal    = 123456;
    $dao->bigintVal = -123456;
    $dao->doubleVal = 123456;
    $dao->floatVal  = 12.3456;
    $dao->boolVal   = true;
    $datastore->save($dao);
    $dao->username = 'test 1';
    $dao->display  = 'Brooke';
    $datastore->save($dao);
    $dao->username = 'test 2';
    $datastore->load($dao);
    $this->assertEquals('test 1', $dao->username);
    $this->assertEquals(123456, $dao->intVal);
    $this->assertEquals(-123456, $dao->bigintVal);
    $this->assertEquals(123456, $dao->doubleVal);
    $this->assertEquals(12.3456, $dao->floatVal, '', 0.00001);
    $this->assertTrue($dao->boolVal);
    $dao->display = 'Save 2';
    $datastore->save($dao);
    $datastore->delete($dao);
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

  public function testExecuteException()
  {
    $connection = new MockCqlConnection();
    $this->_configureConnection($connection);
    $connection->connect();
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\Connection\CqlException'
    );
    $connection->execute(new CqlPreparedResult());
  }

  public function testGetData()
  {
    $datastore  = new MockCqlDataStore();
    $connection = new MockCqlConnection();
    $datastore->setConnection($connection);

    $dao           = new MockCqlDao();
    $dao->id       = 'testdao';
    $dao->username = 'testuser';
    $datastore->save($dao);

    $eq = new EqualPredicate();
    $eq->setField('id');
    $eq->setExpression(ValueExpression::create('testdao'));
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
    $datastore  = new MockCqlDataStore();
    $datastore->setConnection($connection);
    $dao           = new MockCqlDao();
    $dao->id       = 'testdao';
    $dao->username = 'testuser';
    $dao->setTtl(100);
    $datastore->save($dao);
    $this->assertEquals(
      'INSERT INTO "mock_ql_daos" ("id", "username", "display", "intVal",'
      . ' "bigintVal", "doubleVal", "floatVal", "boolVal") '
      . 'VALUES("testdao", "testuser", NULL, "    ", "        ", " '
      . '       ", "    ", " ") USING TTL 100',
      $connection->getExecutedQuery()
    );

    $dao           = new MockCqlDao();
    $dao->id       = 'testdao';
    $dao->username = 'testuser';
    $dao->setTtl(null);
    $datastore->save($dao);
    $this->assertEquals(
      'INSERT INTO "mock_ql_daos" ("id", "username", "display", "intVal",'
      . ' "bigintVal", "doubleVal", "floatVal", "boolVal") '
      . 'VALUES("testdao", "testuser", NULL, "    ", "        ", " '
      . '       ", "    ", " ")',
      $connection->getExecutedQuery()
    );

    $dao->setTtl(101);
    $dao->username = "test";
    $datastore->save($dao);
    $this->assertEquals(
      'UPDATE "mock_ql_daos" SET "username" = "test" WHERE "id" = "testdao" USING TTL 101',
      $connection->getExecutedQuery()
    );

    $cqlDao = $this->getMockForAbstractClass(CqlDao::class);
    $this->assertNull($cqlDao->getTtl());
  }

  public function testCollection()
  {
    $this->assertInstanceOf(CqlDaoCollection::class, MockCqlDao::collection());

    $dataStore = new MockCqlDataStore();
    $dataStore->setConnection(new MockAbstractQlDataConnection());
    $mockDao = new MockCqlDao();
    $mockDao->setDataStore($dataStore);
    $collection = MockCqlCollection::createFromDao($mockDao);
    $data       = $collection->loadWhere()->getRawArray();
    $this->assertNotEmpty($data);
    $this->assertInstanceOf(MockCqlDao::class, $data[0]);
  }
}

class MockCqlCollection extends CqlDaoCollection
{
  public static function createFromDao(CqlDao $dao)
  {
    $collection       = parent::create(get_class($dao));
    $collection->_dao = $dao;
    return $collection;
  }
}

class MockCqlConnection extends CqlConnection
{
  public function getConfig($item)
  {
    return $this->_config()->getItem($item);
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
  protected $_dataStoreName = 'mockql';
  protected $_ttl;

  public $id;
  public $username;
  public $display;
  /**
   * @int
   */
  public $intVal;
  /**
   * @bigint
   */
  public $bigintVal;
  /**
   * @double
   */
  public $doubleVal;
  /**
   * @float
   */
  public $floatVal;
  /**
   * @bool
   */
  public $boolVal;

  protected $_dataStore;

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
