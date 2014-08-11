<?php
namespace Ql;

use cassandra\CqlPreparedResult;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Ql\Cql\CqlConnection;
use Packaged\Dal\Ql\Cql\CqlDataStore;
use Packaged\Dal\Ql\IQLDataConnection;

require_once 'supporting.php';

class CqlTest extends \PHPUnit_Framework_TestCase
{
  public function testConnection()
  {
    $connection = new CqlConnection();
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
    $connection->configure(new ConfigSection());
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
    $connection->connect();
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\Connection\CqlException'
    );
    $connection->prepare("INVALD");
  }

  public function testExecuteException()
  {
    $connection = new MockCqlConnection();
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

class MockCQlDao extends MockQlDao
{
  public function getTableName()
  {
    return "mock_ql_daos";
  }
}
