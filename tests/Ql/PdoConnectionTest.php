<?php
namespace Ql;

use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\PdoConnection;

require_once 'supporting.php';

class PdoConnectionTest extends \PHPUnit_Framework_TestCase
{
  public function testConnection()
  {
    $connection = new PdoConnection();
    $this->assertFalse($connection->isConnected());
    $connection->connect();
    $this->assertTrue($connection->isConnected());
    $connection->disconnect();
    $this->assertFalse($connection->isConnected());
  }

  public function testConnectionException()
  {
    $connection = new PdoConnection();
    $config     = new ConfigSection();
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
    $config     = new ConfigSection();
    $connection->configure($config);
    $connection->connect();
    $this->assertTrue($connection->isConnected());
    $connection->causeGoneAway();
    $this->assertFalse($connection->isConnected());
  }

  public function testAutoConnect()
  {
    $datastore  = new MockQlDataStore();
    $connection = new PdoConnection();
    $connection->configure(new ConfigSection());
    $datastore->setConnection($connection);

    $dao           = new MockQlDao();
    $dao->username = time() . 'user';
    $dao->display  = 'User ' . date("Y-m-d");
    $datastore->save($dao);
    $datastore->getConnection()->disconnect();
    $new     = new MockQlDao();
    $new->id = $dao->id;
    $datastore->load($new);
    $this->assertEquals($dao->username, $new->username);

    $datastore->delete($new);
  }

  public function testExceptions()
  {
    $connection = new PdoConnection();
    $connection->configure(new ConfigSection());
    $connection->connect();
    $this->setExpectedException(ConnectionException::class);
    $connection->runQuery("SELECT * FROM `made_up_table_r43i`", []);
  }

  public function testLsd()
  {
    $datastore  = new MockQlDataStore();
    $connection = new PdoConnection();
    $connection->configure(new ConfigSection());
    $datastore->setConnection($connection);
    $connection->connect();

    $resolver = new DalResolver();
    $resolver->addDataStore('mockql', $datastore);
    $resolver->boot();

    $dao           = new MockQlDao();
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

    $staticLoad = MockQlDao::loadById($dao->id);
    $this->assertEquals($dao->username, $staticLoad->username);

    $datastore->delete($dao);

    $resolver->shutdown();
  }
}

class CorruptablePdoConnection extends PdoConnection
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
