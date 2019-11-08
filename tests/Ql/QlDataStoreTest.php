<?php
namespace Packaged\Dal\Tests\Ql;

use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DaoNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\Exceptions\DataStore\TooManyResultsException;
use Packaged\Dal\Foundation\AbstractDao;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\PdoConnection;
use Packaged\Dal\Ql\QlDataStore;
use Packaged\QueryBuilder\Builder\QueryBuilder;
use Packaged\QueryBuilder\Expression\NumericExpression;
use Packaged\QueryBuilder\Predicate\EqualPredicate;
use Packaged\Dal\Tests\Ql\Mocks\MockAbstractQlDataConnection;
use Packaged\Dal\Tests\Ql\Mocks\MockMultiKeyQlDao;
use Packaged\Dal\Tests\Ql\Mocks\MockQlDao;
use Packaged\Dal\Tests\Ql\Mocks\MockQlDataStore;

class QlDataStoreTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @throws ConnectionException
   * @throws ConnectionNotFoundException
   * @throws DaoNotFoundException
   * @throws DataStoreException
   * @throws TooManyResultsException
   */
  public function testInvalidDao()
  {
    $this->setExpectedException(DataStoreException::class);
    $dao = $this->getMockForAbstractClass(AbstractDao::class);
    $datastore = new MockQlDataStore();
    $datastore->load($dao);
  }

  /**
   * @throws ConnectionNotFoundException
   */
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
    $this->setExpectedException(ConnectionNotFoundException::class);
    $datastore->getConnection();

    Dao::unsetDalResolver();
  }

  /**
   * @throws ConnectionNotFoundException
   */
  public function testUnconfiguredGetConnection()
  {
    $datastore = new QlDataStore();
    $this->setExpectedException(ConnectionNotFoundException::class);
    $datastore->getConnection();
  }

  /**
   * @throws ConnectionException
   * @throws ConnectionNotFoundException
   * @throws DaoNotFoundException
   * @throws DataStoreException
   * @throws TooManyResultsException
   */
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

  /**
   * @throws ConnectionException
   * @throws ConnectionNotFoundException
   * @throws DaoNotFoundException
   * @throws DataStoreException
   * @throws TooManyResultsException
   */
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
      DaoNotFoundException::class,
      'Unable to locate Dao'
    );
    $datastore->load($dao);
  }

  /**
   * @throws ConnectionException
   * @throws ConnectionNotFoundException
   * @throws DaoNotFoundException
   * @throws DataStoreException
   * @throws TooManyResultsException
   */
  public function testLoadTooMany()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $connection->setFetchResult([['username' => '1'], ['username' => '2']]);
    $datastore->setConnection($connection);

    $dao = new MockQlDao();
    $dao->id = 3;

    $this->setExpectedException(
      DataStoreException::class,
      'Too many results located'
    );
    $datastore->load($dao);
  }

  /**
   * @throws DataStoreException
   * @throws ConnectionNotFoundException
   * @throws ConnectionException
   */
  public function testDeleteUnsavedFailure()
  {
    $this->setExpectedException(DataStoreException::class, 'Cannot delete object.  ID property has changed.');

    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);

    $dao = new MockQlDao();
    $dao->id = 3;
    $datastore->save($dao);

    $dao->id = 123;
    $datastore->delete($dao);
  }

  /**
   * @throws DataStoreException
   * @throws ConnectionNotFoundException
   * @throws ConnectionException
   */
  public function testDeleteNewSuccess()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);

    $dao = new MockQlDao();
    $dao->display = 'test';
    $datastore->save($dao);

    $delDao = new MockQlDao();
    $delDao->id = $dao->id;
    $datastore->delete($delDao);
  }

  /**
   * @throws ConnectionException
   * @throws ConnectionNotFoundException
   * @throws DataStoreException
   */
  public function testDelete()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);

    $dao = new MockQlDao();
    $dao->id = 3;
    $datastore->save($dao);
    $datastore->delete($dao);
    $this->assertEquals(
      'DELETE FROM `mock_ql_daos` WHERE `id` = ?',
      $connection->getExecutedQuery()
    );
    $this->assertEquals([3], $connection->getExecutedQueryValues());

    $dao = new MockMultiKeyQlDao();
    $dao->id = 2;
    $dao->username = 'test@example.com';
    $datastore->save($dao);
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

  /**
   * @throws ConnectionException
   * @throws ConnectionNotFoundException
   * @throws DataStoreException
   */
  public function testDeleteNone()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);

    $dao = new MockQlDao();
    $dao->id = 3;
    $datastore->save($dao);

    $this->setExpectedException(DataStoreException::class, 'The delete query executed affected 0 rows');

    $connection->setRunResult(0);
    $datastore->delete($dao);

    $this->assertEquals(
      'DELETE FROM `mock_ql_daos` WHERE `id` = ?',
      $connection->getExecutedQuery()
    );
    $this->assertEquals([3], $connection->getExecutedQueryValues());
  }

  /**
   * @throws ConnectionException
   * @throws ConnectionNotFoundException
   * @throws DataStoreException
   */
  public function testDeleteTooMany()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);

    $dao = new MockQlDao();
    $dao->id = 3;
    $datastore->save($dao);

    $this->setExpectedException(DataStoreException::class, 'Looks like we deleted multiple rows :(');

    $connection->setRunResult(2);
    $datastore->delete($dao);
    $this->assertEquals(
      'DELETE FROM `mock_ql_daos` WHERE `id` = "3"',
      $connection->getExecutedQuery()
    );
  }

  /**
   * @throws ConnectionException
   * @throws ConnectionNotFoundException
   * @throws DaoNotFoundException
   * @throws DataStoreException
   * @throws TooManyResultsException
   */
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

  /**
   * @throws ConnectionException
   * @throws ConnectionNotFoundException
   */
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

  /**
   * @throws ConnectionException
   * @throws ConnectionNotFoundException
   * @throws DataStoreException
   */
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
