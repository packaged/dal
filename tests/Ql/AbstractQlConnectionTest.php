<?php
namespace Packaged\Dal\Tests\Ql;

use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\Connection\DuplicateKeyException;
use Packaged\Dal\Exceptions\DalException;
use Packaged\Dal\Ql\AbstractQlConnection;
use Packaged\Dal\Tests\Ql\Mocks\FailingPrepareRawConnection;
use Packaged\Dal\Tests\Ql\Mocks\MockQlDao;
use Packaged\Dal\Tests\Ql\Mocks\MockQlDataStore;

abstract class AbstractQlConnectionTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @return AbstractQlConnection
   */
  abstract protected function _getConnection();

  public function testConnection()
  {
    $connection = $this->_getConnection();
    $connection->config();
    $this->assertFalse($connection->isConnected());
    $connection->connect();
    $this->assertTrue($connection->isConnected());
    $connection->disconnect();
    $this->assertFalse($connection->isConnected());
  }

  public function testConnectionException()
  {
    $connection = $this->_getConnection();
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
    $connection = $this->_getConnection();
    $config = new ConfigSection();
    $connection->configure($config);
    $connection->connect();
    $this->assertTrue($connection->isConnected());
    $connection->disconnect();
    $this->assertFalse($connection->isConnected());
  }

  public function testAutoConnect()
  {
    $datastore = new MockQlDataStore();
    $connection = $this->_getConnection();
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
    $connection = $this->_getConnection();
    $connection->config();
    $connection->connect();
    $connection->setResolver(new DalResolver());
    $this->setExpectedException(ConnectionException::class);
    $connection->runQuery("SELECT * FROM `made_up_table_r43i`", []);
  }

  public function testReconnect()
  {
    $connection = $this->_getConnection();
    $connection->config();
    $connection->connect();
    $connection->setResolver(new DalResolver());
    $conRes = $connection->fetchQueryResults("SELECT CONNECTION_ID() AS CID");
    $connID = $conRes[0]['CID'];
    for($i = 0; $i < 100; $i++)
    {
      $connection->runQuery("SELECT * FROM `mock_ql_daos`", []);
      if($i == 10)
      {
        $connection->addConfig("retries", 0);
        try
        {
          $connection->runQuery("KILL " . $connID);
        }
        catch(\Exception $e)
        {
        }
        $connection->addConfig("retries", 3);
      }
      usleep(20000);
    }
    $conRes = $connection->fetchQueryResults("SELECT CONNECTION_ID() AS CID");
    $this->assertNotEquals($conRes[0]['CID'], $connID);
    $this->assertEquals(100, $i);
  }

  public function testWaitTimeout()
  {
    $connection = $this->_getConnection();
    $connection->config();
    $connection->connect();
    $connection->setResolver(new DalResolver());
    $connection->runQuery("SET SESSION wait_timeout = 1", []);
    sleep(3);
    $this->assertEquals(
      [[1 => 1]],
      $connection->fetchQueryResults("SELECT 1", [])
    );
  }

  public function testfetchQueryResultsExceptions()
  {
    $connection = $this->_getConnection();
    $connection->config();
    $connection->connect();
    $connection->setResolver(new DalResolver());
    $this->setExpectedException(ConnectionException::class);
    $connection->fetchQueryResults("SELECT * FROM `made_up_table_r44i`", []);
  }

  public function testLsd()
  {
    $datastore = new MockQlDataStore();
    $connection = $this->_getConnection();
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
    $connection = $this->_getConnection();
    $connection->fetchQueryResults("SELECT", []);
  }

  public function testRetries()
  {
    $resolver = new DalResolver();
    $connection = $this->_getConnection();
    $connection->setResolver($resolver);
    $connection->setConnection(
      new FailingPrepareRawConnection('Failed To Prepare')
    );
    try
    {
      $connection->runQuery('my query');
    }
    catch(ConnectionException $e)
    {
      $this->assertEquals('Failed To Prepare', $e->getMessage());
    }
    $this->assertEquals(1, $connection->getRunCount());
  }

  public function testInvalidSyntax()
  {
    $resolver = new DalResolver();
    $connection = $this->_getConnection();
    $connection->setResolver($resolver);
    $connection->connect();
    try
    {
      $connection->runQuery('my query');
    }
    catch(ConnectionException $e)
    {
      // invalid query
      $this->assertEquals(1064, $e->getCode());
    }
    $this->assertEquals(1, $connection->getRunCount());
  }

  public function testTransactions()
  {
    $connection = $this->_getConnection();
    $connection->setResolver(new DalResolver());
    $connection->connect();
    $connection->switchDatabase('packaged_dal');

    $connection2 = $this->_getConnection();
    $connection2->setResolver(new DalResolver());
    $connection2->switchDatabase('packaged_dal');

    $connection->runQuery("DROP TABLE IF EXISTS transactions_test");
    $connection->runQuery(
      "CREATE TABLE transactions_test ("
      . "id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,"
      . "name VARCHAR(255),"
      . "value VARCHAR(255),"
      . "testname VARCHAR(10)"
      . ")"
    );

    $insertFn = function ($testName, $count) use ($connection) {
      for($i = 0; $i < $count; $i++)
      {
        $connection->runQuery(
          sprintf(
            "INSERT INTO transactions_test (name, value, testname)"
            . " VALUES ('name %d', 'value %d', '%s')",
            $i,
            $i,
            $testName
          )
        );
      }
    };

    $countFn = function ($testName, $conn = null) use ($connection) {
      $conn = $conn ?: $connection;
      return $conn->fetchQueryResults(
        sprintf(
          "SELECT count(*) as `count` FROM transactions_test WHERE testname='%s'",
          $testName
        )
      )[0]['count'];
    };

    // Test a commit
    $connection->startTransaction();
    $insertFn('commit', 10);
    $this->assertEquals(10, $countFn('commit'));
    $this->assertEquals(0, $countFn('commit', $connection2));
    $connection->commit();
    $this->assertEquals(10, $countFn('commit'));
    $this->assertEquals(10, $countFn('commit', $connection2));

    // Test a rollback
    $connection->startTransaction();
    $insertFn('rollback', 10);
    $this->assertEquals(10, $countFn('rollback'));
    $this->assertEquals(0, $countFn('rollback', $connection2));
    $connection->rollback();
    $this->assertEquals(0, $countFn('rollback'));
    $this->assertEquals(0, $countFn('rollback', $connection2));
  }

  public function testCommitNotInTransaction()
  {
    $connection = $this->_getConnection();
    $connection->setResolver(new DalResolver());
    $connection->connect();

    $this->setExpectedException(
      ConnectionException::class,
      'Not currently in a transaction'
    );
    $connection->commit();
  }

  public function testRollbackNotInTransaction()
  {
    $connection = $this->_getConnection();
    $connection->setResolver(new DalResolver());
    $connection->connect();

    $this->setExpectedException(
      ConnectionException::class,
      'Not currently in a transaction'
    );
    $connection->rollback();
  }

  public function testDuplicateKey()
  {
    $connection = $this->_getConnection();
    $connection->setResolver(new DalResolver());
    $connection->connect();
    $connection->switchDatabase('packaged_dal');

    $connection->runQuery("DROP TABLE IF EXISTS duplicate_key_test");
    $connection->runQuery(
      "CREATE TABLE duplicate_key_test ("
      . "id INT NOT NULL PRIMARY KEY"
      . ")"
    );

    $query = "INSERT INTO duplicate_key_test (id) VALUES (1)";
    $connection->runQuery($query);

    // Run it again, should cause a DuplicateKeyException
    $this->setExpectedException(DuplicateKeyException::class, "Duplicate entry '1' for key 'PRIMARY'");
    try
    {
      $connection->runQuery($query);
    }
    catch(\Exception $e)
    {
      $this->assertEquals(1, $connection->getRunCount());
      throw $e;
    }
  }
}
