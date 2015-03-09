<?php
namespace Ql;

use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\DalException;

require_once 'supporting.php';

class PdoConnectionTest extends \PHPUnit_Framework_TestCase
{
  public function testConnection()
  {
    $connection = new MockPdoConnection();
    $connection->config();
    $this->assertFalse($connection->isConnected());
    $connection->connect();
    $this->assertTrue($connection->isConnected());
    $connection->disconnect();
    $this->assertFalse($connection->isConnected());
  }

  public function testConnectionException()
  {
    $connection = new MockPdoConnection();
    $config = $connection->config();
    $config->addItem('hostname', '255.255.255.255');
    $connection->configure($config);

    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\Connection\ConnectionException'
    );
    $connection->connect();
  }

  public function testIsConnected()
  {
    $connection = new CorruptablePdoConnection();
    $config = new ConfigSection();
    $connection->configure($config);
    $connection->connect();
    $this->assertTrue($connection->isConnected());
    $connection->causeGoneAway();
    $this->assertFalse($connection->isConnected());
  }

  public function testAutoConnect()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockPdoConnection();
    $connection->config();
    $connection->setResolver(new DalResolver());
    $datastore->setConnection($connection);

    $dao = new MockQlDao();
    $dao->username = time() . 'user';
    $dao->display = 'User ' . date("Y-m-d");
    $datastore->save($dao);
    $datastore->getConnection()->disconnect();
    $new = new MockQlDao();
    $new->id = $dao->id;
    $datastore->load($new);
    $this->assertEquals($dao->username, $new->username);

    $datastore->delete($new);
  }

  public function testRunQueryExceptions()
  {
    $connection = new MockPdoConnection();
    $connection->config();
    $connection->connect();
    $connection->setResolver(new DalResolver());
    $this->setExpectedException(ConnectionException::class);
    $connection->runQuery("SELECT * FROM `made_up_table_r43i`", []);
  }

  public function testfetchQueryResultsExceptions()
  {
    $connection = new MockPdoConnection();
    $connection->config();
    $connection->connect();
    $connection->setResolver(new DalResolver());
    $this->setExpectedException(ConnectionException::class);
    $connection->fetchQueryResults("SELECT * FROM `made_up_table_r43i`", []);
  }

  public function testNativeErrorFormat_runQuery()
  {
    $pdo = new PrepareErrorPdoConnection('My Exception Message', 1234);
    $connection = new MockPdoConnection();
    $connection->setConnection($pdo);
    $connection->setResolver(new DalResolver());
    $this->setExpectedException(
      ConnectionException::class,
      'My Exception Message',
      1234
    );
    $connection->runQuery("SELECT * FROM `made_up_table_r43i`", []);
  }

  public function testNativeErrorFormat_fetchQueryResults()
  {
    $pdo = new PrepareErrorPdoConnection('My Exception Message', 1234);
    $connection = new MockPdoConnection();
    $connection->setResolver(new DalResolver());
    $connection->setConnection($pdo);
    $this->setExpectedException(
      ConnectionException::class,
      'My Exception Message',
      1234
    );
    $connection->fetchQueryResults("SELECT * FROM `made_up_table_r43i`", []);
  }

  public function testLsd()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockPdoConnection();
    $connection->config();
    $datastore->setConnection($connection);
    $connection->connect();

    $resolver = new DalResolver();
    $resolver->addDataStore('mockql', $datastore);
    $resolver->boot();
    $resolver->enablePerformanceMetrics();

    $connection->setResolver($resolver);

    $dao = new MockQlDao();
    $dao->username = time() . 'user';
    $dao->display = 'User ' . date("Y-m-d");
    $dao->boolTest = true;
    $datastore->save($dao);
    $dao->username = 'test 1';
    $dao->display = 'Brooke';
    $dao->boolTest = false;
    $datastore->save($dao);
    $dao->username = 'test 2';
    $datastore->load($dao);
    $this->assertEquals('test 1', $dao->username);
    $this->assertEquals(0, $dao->boolTest);
    $dao->display = 'Save 2';
    $datastore->save($dao);

    $staticLoad = MockQlDao::loadById($dao->id);
    $this->assertEquals($dao->username, $staticLoad->username);

    $datastore->delete($dao);

    $metrics = $resolver->getPerformanceMetrics();
    $resolver->disablePerformanceMetrics();
    $this->assertCount(6, $metrics);

    $resolver->shutdown();
  }

  public function testNoResolver()
  {
    $this->setExpectedException(
      DalException::class,
      "Connection running without the resolver being defined"
    );
    $connection = new MockPdoConnection();
    $connection->fetchQueryResults("SELECT", []);
  }
}

class CorruptablePdoConnection extends MockPdoConnection
{
  public function causeGoneAway()
  {
    $this->_connection = new GoneAwayConnection();
  }
}

class GoneAwayConnection
{
  public function query()
  {
    throw new \Exception("MySQL Has Gone Away");
  }
}
