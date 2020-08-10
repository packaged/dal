<?php
namespace Packaged\Dal\Tests\Ql\MySQLi;

use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Tests\Ql\AbstractQlConnectionTest;
use Packaged\Dal\Tests\Ql\Mocks\FailingPrepareRawConnection;
use Packaged\Dal\Tests\Ql\Mocks\MySQLi\MockMySQLiConnection;

class MySQLiConnectionTest extends AbstractQlConnectionTest
{
  protected function _getConnection()
  {
    return new MockMySQLiConnection();
  }

  public function testNativeErrorFormat_runQuery()
  {
    $conn = new FailingPrepareRawConnection('My Exception Message', 1234);
    $connection = $this->_getConnection();
    $connection->setConnection($conn);
    $connection->setResolver(new DalResolver());
    $this->expectException(ConnectionException::class);
    $this->expectExceptionMessage('My Exception Message');
    $this->expectExceptionCode(1234);
    $connection->runQuery("SELECT * FROM `made_up_table_r45i`", []);
  }

  public function testNativeErrorFormat_fetchQueryResults()
  {
    $conn = new FailingPrepareRawConnection('My Exception Message', 1234);
    $connection = $this->_getConnection();
    $connection->setResolver(new DalResolver());
    $connection->setConnection($conn);
    $this->expectException(ConnectionException::class);
    $this->expectExceptionMessage('My Exception Message');
    $this->expectExceptionCode(1234);
    $connection->fetchQueryResults("SELECT * FROM `made_up_table_r46i`", []);
  }
}
