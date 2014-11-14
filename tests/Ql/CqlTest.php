<?php
namespace Ql;

use cassandra\CqlPreparedResult;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Ql\Cql\CqlConnection;
use Packaged\Dal\Ql\Cql\CqlDao;
use Packaged\Dal\Ql\Cql\CqlDataStore;
use Packaged\Dal\Ql\IQLDataConnection;

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

  public static function tearDownAfterClass()
  {
  }

  public function testDataTypes()
  {
    $datastore  = new MockCqlDataStore();
    $connection = new CqlConnection();
    $this->_configureConnection($connection);
    $datastore->setConnection($connection);
    $connection->connect();

    $dao            = new MockCQlDao();
    $dao->id        = 'cqlid';
    $dao->intVal    = 123456;
    $dao->bigintVal = 123456;
    $dao->doubleVal = 123456;
    $dao->floatVal  = 12.3456;
    $dao->boolVal   = true;
    $datastore->save($dao);

    $daoLoad     = new MockCQlDao();
    $daoLoad->id = 'cqlid';
    $datastore->load($daoLoad);
    $this->assertEquals('cqlid', $daoLoad->id);
    $this->assertEquals(123456, $daoLoad->intVal);
    $this->assertEquals(123456, $daoLoad->bigintVal);
    $this->assertEquals(123456, $daoLoad->doubleVal);
    $this->assertEquals(12.3456, $daoLoad->floatVal, '', 0.00001);
    $this->assertTrue($daoLoad->boolVal);
  }

  protected function _configureConnection(CqlConnection $conn)
  {
    $conn->setReceiveTimeout(5000);
    $conn->setSendTimeout(5000);
    $conn->setConfig('connect_timeout', 1000);
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

    $dao           = new MockCQlDao();
    $dao->username = time() . 'user';
    $dao->display  = 'User ' . date("Y-m-d");
    $datastore->save($dao);
    $dao->username = 'test 1';
    $dao->display  = 'Brooke';
    $datastore->save($dao);
    $dao->username = 'test 2';
    $datastore->load($dao);
    $this->assertEquals('test 1', $dao->username);
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

class MockCQlDao extends CqlDao
{
  protected $_dataStoreName = 'mockql';

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

  public function getTableName()
  {
    return "mock_ql_daos";
  }
}
