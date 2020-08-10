<?php
namespace Packaged\Dal\Tests\Ql\PDO\Mocks;

use Packaged\Dal\Ql\PdoConnection;
use Packaged\Dal\Tests\Ql\Mocks\MockConnectionInterface;
use Packaged\Dal\Tests\Ql\Mocks\MockCounterDao;
use Packaged\Dal\Tests\Ql\Mocks\MockQlDao;

class MockPdoConnection extends PdoConnection implements MockConnectionInterface
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

  public function getMockDao()
  {
    return new MockQlDao();
  }

  public function getMockCounterDao()
  {
    return new MockCounterDao();
  }

  public function truncate()
  {
    $this->runQuery('TRUNCATE TABLE `packaged_dal`.`mock_ql_daos`');
    $this->runQuery('TRUNCATE TABLE `packaged_dal`.`mock_counter_daos`');
  }
}
