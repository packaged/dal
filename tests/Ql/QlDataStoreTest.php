<?php
namespace Ql;

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
    $fs  = new MockQlDataStore();
    $fs->load($dao);
  }

  public function testLoad()
  {
    $datastore  = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);

    $dao     = new MockQlDao();
    $dao->id = 3;
    $datastore->load($dao);
    $this->assertEquals(
      'SELECT * FROM `mock_ql_daos` WHERE `id` = "3" LIMIT 2',
      $connection->getExecutedQuery()
    );

    $dao           = new MockMultiKeyQlDao();
    $dao->id       = 2;
    $dao->username = 'test@example.com';
    $datastore->load($dao);
    $this->assertEquals('x', $dao->username);
    $this->assertEquals('y', $dao->display);
    $this->assertEquals(
      'SELECT * FROM `mock_multi_key_ql_daos` '
      . 'WHERE `id` = "2" AND `username` = "test@example.com" LIMIT 2',
      $connection->getExecutedQuery()
    );
  }

  public function testLoadNone()
  {
    $datastore  = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $connection->setFetchResult([]);
    $datastore->setConnection($connection);

    $dao     = new MockQlDao();
    $dao->id = 3;

    $this->setExpectedException(
      'Packaged\Dal\Exceptions\DataStore\DaoNotFoundException',
      'Unable to locate Dao'
    );
    $datastore->load($dao);
  }

  public function testLoadTooMany()
  {
    $datastore  = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $connection->setFetchResult([['username' => '1'], ['username' => '2']]);
    $datastore->setConnection($connection);

    $dao     = new MockQlDao();
    $dao->id = 3;

    $this->setExpectedException(
      'Packaged\Dal\Exceptions\DataStore\DataStoreException',
      'Too many results located'
    );
    $datastore->load($dao);
  }

  public function testDelete()
  {
    $datastore  = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);

    $dao     = new MockQlDao();
    $dao->id = 3;
    $datastore->delete($dao);
    $this->assertEquals(
      'DELETE FROM `mock_ql_daos` WHERE `id` = "3"',
      $connection->getExecutedQuery()
    );

    $dao           = new MockMultiKeyQlDao();
    $dao->id       = 2;
    $dao->username = 'test@example.com';
    $datastore->delete($dao);
    $this->assertEquals(
      'DELETE FROM `mock_multi_key_ql_daos` '
      . 'WHERE `id` = "2" AND `username` = "test@example.com"',
      $connection->getExecutedQuery()
    );
  }

  public function testDeleteNone()
  {
    $datastore  = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);
    $connection->setRunResult(0);

    $this->setExpectedException(
      'Packaged\Dal\Exceptions\DataStore\DataStoreException',
      'The delete query executed affected 0 rows'
    );

    $dao     = new MockQlDao();
    $dao->id = 3;
    $datastore->delete($dao);
    $this->assertEquals(
      'DELETE FROM `mock_ql_daos` WHERE `id` = "3"',
      $connection->getExecutedQuery()
    );
  }

  public function testDeleteTooMany()
  {
    $datastore  = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);
    $connection->setRunResult(2);

    $this->setExpectedException(
      'Packaged\Dal\Exceptions\DataStore\DataStoreException',
      "Looks like we deleted multiple rows :("
    );

    $dao     = new MockQlDao();
    $dao->id = 3;
    $datastore->delete($dao);
    $this->assertEquals(
      'DELETE FROM `mock_ql_daos` WHERE `id` = "3"',
      $connection->getExecutedQuery()
    );
  }

  public function testSave()
  {
    $datastore  = new MockQlDataStore();
    $connection = new MockAbstractQlDataConnection();
    $datastore->setConnection($connection);

    //Insert
    $dao           = new MockQlDao();
    $dao->username = 'username';
    $dao->display  = 'John Smith';
    $datastore->save($dao);
    $this->assertEquals(
      'INSERT INTO `mock_ql_daos` (`id`, `username`, `display`) '
      . 'VALUES(NULL, "username", "John Smith")',
      $connection->getExecutedQuery()
    );

    //Update
    $dao     = new MockQlDao();
    $dao->id = 3;
    $datastore->load($dao);
    $dao->username = 'usernamde';
    $datastore->save($dao);
    $this->assertEquals(
      'UPDATE `mock_ql_daos` SET `username` = "usernamde" WHERE `id` = "3"',
      $connection->getExecutedQuery()
    );

    //Insert Update
    $dao          = new MockQlDao();
    $dao->id      = 3;
    $dao->display = 'John Smith';
    $datastore->save($dao);
    $this->assertEquals(
      'INSERT INTO `mock_ql_daos` (`id`, `username`, `display`) '
      . 'VALUES("3", NULL, "John Smith") '
      . 'ON DUPLICATE KEY UPDATE `display` = "John Smith"',
      $connection->getExecutedQuery()
    );
  }
}
