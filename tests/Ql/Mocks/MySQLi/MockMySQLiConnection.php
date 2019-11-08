<?php
namespace Packaged\Dal\Tests\Ql\Mocks\MySQLi;

use Packaged\Dal\Ql\MySQLiConnection;

class MockMySQLiConnection extends MySQLiConnection
{
  public function setConnection($connection)
  {
    $this->_connection = $connection;
  }

  public function addConfig($key, $value)
  {
    $this->_config()->addItem($key, $value);
    return $this;
  }

  public function config()
  {
    $this->_config()->addItem('database', 'packaged_dal');
    return $this->_config();
  }

  public function getRunCount()
  {
    return $this->_lastRetryCount;
  }
}
