<?php
namespace Ql;

use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\Connection\PdoException;
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
    $connection->disconnect();
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
    $connection->fetchQueryResults("SELECT * FROM `made_up_table_r44i`", []);
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
    $connection->runQuery("SELECT * FROM `made_up_table_r45i`", []);
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
    $connection->fetchQueryResults("SELECT * FROM `made_up_table_r46i`", []);
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

  public function testRetries()
  {
    $resolver = new DalResolver();
    $connection = new MockPdoConnection();
    $connection->setResolver($resolver);
    $connection->setConnection(new FailingRawConnection());
    try
    {
      $connection->runQuery('my query');
    }
    catch(PdoException $e)
    {
    }
    $this->assertEquals(1, $connection->getRunCount());
  }

  public function testInvalidSyntax()
  {
    $resolver = new DalResolver();
    $connection = new MockPdoConnection();
    $connection->setResolver($resolver);
    $connection->connect();
    try
    {
      $connection->runQuery('my query');
    }
    catch(PdoException $e)
    {
      $this->assertEquals(42000, $e->getPrevious()->getCode());
    }
    $this->assertEquals(1, $connection->getRunCount());
  }

  /**
   * @dataProvider delayedPreparesProvider
   *
   * @param int|string $setting
   * @param int        $expectedDelayCount
   *
   * @throws ConnectionException
   */
  public function testDelayedPrepares($setting, $expectedDelayCount)
  {
    $connection = new DelayedPreparesPdoConnection();
    $cfg = $connection->config();
    $cfg->addItem('delayed_prepares', $setting);
    $connection->connect();
    $connection->setResolver(new DalResolver());

    // Run the same query 3 times
    for($i = 0; $i < 3; $i++)
    {
      $res = $connection->runQuery('SELECT ?', [$i]);
      // result is the number of affected rows
      $this->assertEquals(1, $res);
    }

    $this->assertEquals(
      $expectedDelayCount,
      $connection->getLastQueryDelayCount()
    );
  }

  public function delayedPreparesProvider()
  {
    return [[0, 0], [1, 1], [2, 2], ['true', 1], ['false', 0]];
  }

  public function testTransactions()
  {
    $connection = new MockPdoConnection();
    $connection->setResolver(new DalResolver());
    $connection->addConfig('database', 'packaged_dal');

    $connection2 = new MockPdoConnection();
    $connection2->setResolver(new DalResolver());
    $connection2->addConfig('database', 'packaged_dal');

    $connection->runQuery("DROP TABLE IF EXISTS transactions_test");
    $connection->runQuery(
      "CREATE TABLE transactions_test ("
      . "id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,"
      . "name VARCHAR(255),"
      . "value VARCHAR(255),"
      . "testname VARCHAR(10)"
      . ")"
    );

    $insertFn = function ($testName, $count) use ($connection)
    {
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

    $countFn = function ($testName, $conn = null) use ($connection)
    {
      $conn = $conn ?: $connection;
      return $conn->runQuery(
        sprintf(
          "SELECT * FROM transactions_test WHERE testname='%s'",
          $testName
        )
      );
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
    $connection = new MockPdoConnection();
    $connection->setResolver(new DalResolver());
    $connection->connect();

    $this->setExpectedException(
      PdoException::class,
      'Not currently in a transaction'
    );
    $connection->commit();
  }

  public function testRollbackNotInTransaction()
  {
    $connection = new MockPdoConnection();
    $connection->setResolver(new DalResolver());
    $connection->connect();

    $this->setExpectedException(
      PdoException::class,
      'Not currently in a transaction'
    );
    $connection->rollback();
  }

  public function testSwitchDBDuringTransaction()
  {
    $connection = new MockPdoConnection();
    $connection->config();
    $connection->setResolver(new DalResolver());
    $connection->connect();

    $connection->startTransaction();
    $connection->addConfig('database', 'packaged_dal_2');
    $this->setExpectedException(
      PdoException::class,
      'Cannot switch database while in a transaction'
    );
    $connection->runQuery('SELECT 1');
  }

  public function switchDBProvider()
  {
    return [[true], [false]];
  }

  /**
   * @param bool $persistent
   *
   * @dataProvider switchDBProvider
   * @throws ConnectionException
   */
  public function testSharedConnection($persistent)
  {
    $tmpConn = new MockPdoConnection();
    $tmpConn->setResolver(new DalResolver());
    $tmpConn->addConfig('options', [\PDO::ATTR_PERSISTENT => $persistent]);

    $numProcs = count($tmpConn->fetchQueryResults("SHOW FULL PROCESSLIST"));

    $tmpConn->runQuery("DROP DATABASE IF EXISTS packaged_dal_a");
    $tmpConn->runQuery("DROP DATABASE IF EXISTS packaged_dal_b");
    $tmpConn->runQuery("CREATE DATABASE packaged_dal_a");
    $tmpConn->runQuery("CREATE DATABASE packaged_dal_b");
    $tmpConn->runQuery(
      "CREATE TABLE packaged_dal_a.table_a (id int, value varchar(200))"
    );
    $tmpConn->runQuery(
      "CREATE TABLE packaged_dal_b.table_b (id int, value varchar(200))"
    );
    $tmpConn->runQuery(
      "INSERT INTO packaged_dal_a.table_a VALUES (1, 'test_a')"
    );
    $tmpConn->runQuery(
      "INSERT INTO packaged_dal_b.table_b VALUES (1, 'test_b')"
    );

    $conn1 = new MockPdoConnection();
    $conn1->setResolver(new DalResolver());
    $conn1->addConfig('options', [\PDO::ATTR_PERSISTENT => $persistent]);
    $conn1->addConfig('database', 'packaged_dal_a');

    $conn2 = new MockPdoConnection();
    $conn2->setResolver(new DalResolver());
    $conn2->addConfig('options', [\PDO::ATTR_PERSISTENT => $persistent]);
    $conn2->addConfig('database', 'packaged_dal_b');

    $conn1->connect();
    $conn2->connect();

    $testSelect = function (MockPdoConnection $conn, $isA)
    use ($tmpConn, $persistent)
    {
      $tbl = 'table_' . ($isA ? 'a' : 'b');
      $db = 'packaged_dal_' . ($isA ? 'a' : 'b');

      $this->assertEquals(
        [['id' => 1, 'value' => $isA ? 'test_a' : 'test_b']],
        $conn->fetchQueryResults("SELECT * FROM " . $tbl)
      );

      if($persistent)
      {
        $this->assertEquals(
          [['db' => $db]],
          $tmpConn->fetchQueryResults('SELECT DATABASE() AS db')
        );
      }
    };

    $testSelect($conn1, true);
    $testSelect($conn2, false);
    $testSelect($conn1, true);
    $testSelect($conn2, false);

    $this->assertEquals(
      $persistent ? $numProcs : $numProcs + 2,
      count($tmpConn->fetchQueryResults("SHOW FULL PROCESSLIST"))
    );

    $tmpConn->runQuery("DROP DATABASE IF EXISTS packaged_dal_a");
    $tmpConn->runQuery("DROP DATABASE IF EXISTS packaged_dal_b");
  }

  public function stmtCacheLimitProvider()
  {
    return [[0], [1], [20]];
  }

  /**
   * @dataProvider stmtCacheLimitProvider
   *
   * @param int $limit
   *
   * @throws ConnectionException
   */
  public function testStatementCacheLimit($limit)
  {
    $connection = new StmtCacheConnection();
    $connection->setCacheLimit($limit);
    $connection->setResolver(new DalResolver());
    $connection->connect();
    $connection->clearStmtCache();

    $this->assertEquals(0, $connection->getCachedStatementCount());

    // run (1.5x limit) statements three times each, make sure the
    // correct amount are cached
    $stmts = [];
    $numQueries = max(ceil($limit * 1.5), 5);
    for($i = 0; $i < $numQueries; $i++)
    {
      $sql = 'SELECT ' . $i;
      $stmts[$i] = $sql;
      for($n = 0; $n < 3; $n++)
      {
        $connection->runQuery($sql);
      }

      $this->assertEquals(
        min($i + 1, $limit),
        $connection->getCachedStatementCount()
      );

      // make sure the correct statements are still in the cache
      $cache = $connection->getCachedStatements();
      for($j = 0; $j <= $i; $j++)
      {
        $cacheKey = $connection->getCacheKey($stmts[$j]);
        if(($i >= $limit) && ($j <= $i - $limit))
        {
          $this->assertFalse(isset($cache[$cacheKey]));
        }
        else
        {
          $this->assertTrue(isset($cache[$cacheKey]));
        }
      }
    }
  }
}

class FailingRawConnection
{
  public function prepare($query)
  {
    throw new \PDOException($query);
  }

  public function setAttribute($attribute, $value)
  {
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

class StmtCacheConnection extends MockPdoConnection
{
  public function clearStmtCache()
  {
    $this->_clearStmtCache();
  }

  public function getCachedStatementCount()
  {
    return count(self::$_stmtCache[$this->_getConnectionId()]);
  }

  public function getCachedStatements()
  {
    return self::$_stmtCache[$this->_getConnectionId()];
  }

  public function getCacheKey($sql)
  {
    return $this->_stmtCacheKey($sql);
  }

  public function setCacheLimit($limit)
  {
    $this->_maxPreparedStatements = $limit;
  }
}
