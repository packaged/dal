<?php
namespace Tests\Ql;

use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Ql\PdoConnection;
use Tests\Ql\Mocks\PDO\DelayedPreparesPdoConnection;
use Tests\Ql\Mocks\PDO\MockPdoConnection;
use Tests\Ql\Mocks\PDO\PrepareErrorPdoConnection;
use Tests\Ql\Mocks\PDO\StmtCachePdoConnection;

class PdoConnectionTest extends AbstractQlConnectionTest
{
  protected function _getConnection()
  {
    return new MockPdoConnection();
  }

  public function testDsn()
  {
    $connection = new PdoConnection();
    $connection->setResolver(new DalResolver());
    $connection->configure(
      new ConfigSection('pdo', ['hostname' => '127.0.0.1', 'port' => 3306])
    );
    $connection->connect();

    $result = $connection->fetchQueryResults('SELECT 1');
    $this->assertEquals([[1 => 1]], $result);
  }

  public function testNativeErrorFormat_runQuery()
  {
    $pdo = new PrepareErrorPdoConnection('My Exception Message', 1234);
    $connection = $this->_getConnection();
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
    $connection = $this->_getConnection();
    $connection->setResolver(new DalResolver());
    $connection->setConnection($pdo);
    $this->setExpectedException(
      ConnectionException::class,
      'My Exception Message',
      1234
    );
    $connection->fetchQueryResults("SELECT * FROM `made_up_table_r46i`", []);
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

  public function testStatementCacheLimit()
  {
    $this->_doStmtCacheLimitTest(0);
    $this->_doStmtCacheLimitTest(1);
    $this->_doStmtCacheLimitTest(20);
  }

  private function _doStmtCacheLimitTest($limit)
  {
    $connection = new StmtCachePdoConnection();
    $connection->setCacheLimit($limit);
    $connection->setResolver(new DalResolver());
    $connection->connect();

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
