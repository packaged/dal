<?php
namespace Ql;

use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Ql\MySql\MySQLiConnection;

require_once 'supporting.php';

class MySQLiConnectionTest extends \PHPUnit_Framework_TestCase
{
  public function testConnection()
  {
    $connection = new MySQLiConnection();
    $this->assertFalse($connection->isConnected());
    $connection->connect();
    $this->assertTrue($connection->isConnected());
    $connection->disconnect();
    $this->assertFalse($connection->isConnected());
  }

  public function testConnectionException()
  {
    $connection = new MySQLiConnection();
    $config     = new ConfigSection();
    $config->addItem('hostname', '255.255.255.255');
    $connection->configure($config);

    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\Connection\ConnectionException'
    );
    $connection->connect();
  }

  public function testDisconnectException()
  {
    $connection = new CorruptableMySQLiConnection();
    $config     = new ConfigSection();
    $connection->configure($config);

    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\Connection\ConnectionException'
    );
    $connection->connect();
    $connection->close();
    $connection->disconnect();
  }

  public function testIsConnected()
  {
    $connection = new CorruptableMySQLiConnection();
    $config     = new ConfigSection();
    $connection->configure($config);
    $connection->connect();
    $connection->close();
    $connection->setUseParentConnected(true);
    $this->assertFalse($connection->isConnected());
  }

  public function testLsd()
  {
    $datastore  = new MockQlDataStore();
    $connection = new MySQLiConnection();
    $connection->configure(new ConfigSection());
    $datastore->setConnection($connection);
    $connection->connect();

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
    $datastore->delete($dao);
  }
}

class CorruptableMySQLiConnection extends MySQLiConnection
{
  public function close()
  {
    $this->_connection->close();
  }

  protected $_useParentConnected = false;

  public function isConnected()
  {
    if($this->_useParentConnected)
    {
      return parent::isConnected();
    }
    return true;
  }

  public function setUseParentConnected($connected)
  {
    $this->_useParentConnected = $connected;
  }
}
