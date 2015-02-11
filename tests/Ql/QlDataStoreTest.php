<?php
namespace Ql;

use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\PdoConnection;
use Packaged\Dal\Ql\QlDataStore;
use Packaged\QueryBuilder\Builder\QueryBuilder;
use Packaged\QueryBuilder\Expression\NumericExpression;
use Packaged\QueryBuilder\Predicate\EqualPredicate;

require_once 'supporting.php';

class QlDataStoreTest extends \PHPUnit_Framework_TestCase
{
  public function testInvalidDao()
  {
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DataStore\DataStoreException'
    );
    $dao = $this->getMockForAbstractClass(
      '\Packaged\Dal\Foundation\AbstractDao'
    );
    $fs = new MockQlDataStore();
    $fs->load($dao);
  }

  public function testGetConnection()
  {
    $resolver = new DalResolver();
    $conn = new PdoConnection();
    $resolver->addConnection('pdo', $conn);
    $resolver->boot();
    $datastore = new QlDataStore();
    $datastore->configure(
      new ConfigSection('qldatastore', ['connection' => 'pdo'])
    );
    $this->assertSame($conn, $datastore->getConnection());

    $datastore = new QlDataStore();
    $datastore->configure(
      new ConfigSection('qldatastore', ['connection' => 'mizzing'])
    );
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException'
    );
    $datastore->getConnection();

    Dao::unsetDalResolver();
  }

  public function testUnconfiguredGetConnection()
  {
    $datastore = new QlDataStore();
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException'
    );
    $datastore->getConnection();
  }

  public function testLoad()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);

    $dao = new MockQlDao();
    $dao->id = 3;
    $this->assertTrue($datastore->exists($dao));
    $datastore->load($dao);
    $this->assertEquals(
      'SELECT * FROM `mock_ql_daos` WHERE `id` = ? LIMIT ?',
      $connection->getExecutedQuery()
    );
    $this->assertEquals([3, 2], $connection->getExecutedQueryValues());

    $dao = new MockMultiKeyQlDao();
    $dao->id = 2;
    $dao->username = 'test@example.com';
    $datastore->load($dao);
    $this->assertEquals('x', $dao->username);
    $this->assertEquals('y', $dao->display);
    $this->assertEquals(
      'SELECT * FROM `mock_multi_key_ql_daos` '
      . 'WHERE `id` = ? AND `username` = ? LIMIT ?',
      $connection->getExecutedQuery()
    );
    $this->assertEquals(
      [2, "test@example.com", 2],
      $connection->getExecutedQueryValues()
    );
  }

  public function testLoadNone()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $connection->setFetchResult([]);
    $datastore->setConnection($connection);

    $dao = new MockQlDao();
    $dao->id = 3;

    $this->assertFalse($datastore->exists($dao));

    $this->setExpectedException(
      'Packaged\Dal\Exceptions\DataStore\DaoNotFoundException',
      'Unable to locate Dao'
    );
    $datastore->load($dao);
  }

  public function testLoadTooMany()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $connection->setFetchResult([['username' => '1'], ['username' => '2']]);
    $datastore->setConnection($connection);

    $dao = new MockQlDao();
    $dao->id = 3;

    $this->setExpectedException(
      'Packaged\Dal\Exceptions\DataStore\DataStoreException',
      'Too many results located'
    );
    $datastore->load($dao);
  }

  public function testDelete()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);

    $dao = new MockQlDao();
    $dao->id = 3;
    $datastore->delete($dao);
    $this->assertEquals(
      'DELETE FROM `mock_ql_daos` WHERE `id` = ?',
      $connection->getExecutedQuery()
    );
    $this->assertEquals([3], $connection->getExecutedQueryValues());

    $dao = new MockMultiKeyQlDao();
    $dao->id = 2;
    $dao->username = 'test@example.com';
    $datastore->delete($dao);
    $this->assertEquals(
      'DELETE FROM `mock_multi_key_ql_daos` '
      . 'WHERE `id` = ? AND `username` = ?',
      $connection->getExecutedQuery()
    );
    $this->assertEquals(
      [2, "test@example.com"],
      $connection->getExecutedQueryValues()
    );
  }

  public function testDeleteNone()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);
    $connection->setRunResult(0);

    $this->setExpectedException(
      'Packaged\Dal\Exceptions\DataStore\DataStoreException',
      'The delete query executed affected 0 rows'
    );

    $dao = new MockQlDao();
    $dao->id = 3;
    $datastore->delete($dao);
    $this->assertEquals(
      'DELETE FROM `mock_ql_daos` WHERE `id` = ?',
      $connection->getExecutedQuery()
    );
    $this->assertEquals([3], $connection->getExecutedQueryValues());
  }

  public function testDeleteTooMany()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);
    $connection->setRunResult(2);

    $this->setExpectedException(
      'Packaged\Dal\Exceptions\DataStore\DataStoreException',
      "Looks like we deleted multiple rows :("
    );

    $dao = new MockQlDao();
    $dao->id = 3;
    $datastore->delete($dao);
    $this->assertEquals(
      'DELETE FROM `mock_ql_daos` WHERE `id` = "3"',
      $connection->getExecutedQuery()
    );
  }

  public function testSave()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);

    //Insert
    $dao = new MockQlDao();
    $dao->username = 'username';
    $dao->display = 'John Smith';
    $datastore->save($dao);
    $this->assertEquals(
      'INSERT INTO `mock_ql_daos` (`id`, `username`, `display`, `boolTest`) '
      . 'VALUES (?, ?, ?, ?)',
      $connection->getExecutedQuery()
    );
    $this->assertEquals(
      [null, "username", "John Smith", null],
      $connection->getExecutedQueryValues()
    );

    //Update
    $dao = new MockQlDao();
    $dao->id = 3;
    $datastore->load($dao);
    $dao->username = 'usernamde';
    $datastore->save($dao);
    $this->assertEquals(
      'UPDATE `mock_ql_daos` SET `username` = ? WHERE `id` = ?',
      $connection->getExecutedQuery()
    );
    $this->assertEquals(
      ['usernamde', '3'],
      $connection->getExecutedQueryValues()
    );

    //Insert Update
    $dao = new MockQlDao();
    $dao->id = 3;
    $dao->display = 'John Smith';
    $dao->boolTest = false;
    $datastore->save($dao);
    $this->assertEquals(
      'INSERT INTO `mock_ql_daos` (`id`, `username`, `display`, `boolTest`) '
      . 'VALUES (?, ?, ?, ?) '
      . 'ON DUPLICATE KEY UPDATE `display` = ?, `boolTest` = ?',
      $connection->getExecutedQuery()
    );
    $this->assertEquals(
      [3, null, 'John Smith', 0, 'John Smith', 0],
      $connection->getExecutedQueryValues()
    );
  }

  public function testGetData()
  {
    $dao = new MockQlDao();
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);
    $datastore->getData(QueryBuilder::select()->from($dao->getTableName()));
    $this->assertEquals(
      'SELECT * FROM `mock_ql_daos`',
      $connection->getExecutedQuery()
    );
  }

  public function testExecute()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);

    $dao = new MockQlDao();
    $dao->id = 4;
    $dao->display = 'John Smith';
    $dao->boolTest = false;
    $datastore->save($dao);

    $datastore->execute(
      QueryBuilder::deleteFrom(
        $dao->getTableName(),
        (new EqualPredicate())
          ->setField('id')
          ->setExpression(NumericExpression::create(4))
      )
    );
    $this->assertEquals(
      'DELETE FROM `mock_ql_daos` WHERE `id` = ?',
      $connection->getExecutedQuery()
    );
    $this->assertEquals([4], $connection->getExecutedQueryValues());
  }
}
