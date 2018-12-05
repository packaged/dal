<?php
namespace Tests\Ql;

use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\PdoConnection;
use Packaged\QueryBuilder\Assembler\QueryAssembler;
use Tests\Ql\Mocks\MockNonUniqueKeyDao;
use Tests\Ql\Mocks\MockQlDao;
use Tests\Ql\Mocks\MockQlDataStore;
use Tests\Ql\Mocks\PDO\MockPdoConnection;

class QlDaoTest extends \PHPUnit_Framework_TestCase
{
  protected function setUp()
  {
    $resolver = new DalResolver();
    $resolver->boot();

    $connection = new MockPdoConnection();
    $connection->config();
    $connection->setResolver($resolver);
    $connection->runQuery('TRUNCATE TABLE `mock_ql_daos`');
  }

  public function testStatics()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockPdoConnection();
    $connection->config();
    $datastore->setConnection($connection);

    $collection = MockQlDao::collection();
    $this->assertInstanceOf(MockQlDao::class, $collection->createNewDao());

    $collection = MockQlDao::collection(['name' => 'Test']);
    $this->assertInstanceOf(MockQlDao::class, $collection->createNewDao());
    $this->assertEquals(
      'SELECT mock_ql_daos.* FROM mock_ql_daos WHERE name = "Test"',
      QueryAssembler::stringify($collection->getQuery())
    );

    $resolver = new DalResolver();
    $resolver->boot();
    $resolver->addDataStore('mockql', $datastore);

    $connection->setResolver($resolver);

    $username = uniqid('TEST');
    $u = new MockQlDao();
    $u->username = $username;
    $u->display = 'Test One';
    $u->boolTest = true;
    $u->save();
    // save again to ensure no query is made
    $u->save();
    $mocks = MockQlDao::collection(['username' => $username]);
    $this->assertCount(1, $mocks);

    foreach(MockQlDao::each(['username' => $username]) as $usr)
    {
      $this->assertInstanceOf(MockQlDao::class, $usr);
    }

    $preloaded = MockQlDao::collection(['username' => $username])->load();
    $this->assertCount(1, $preloaded);

    $u2 = new MockQlDao();
    $u2->username = 'Tester';
    $u2->display = 'Test One';
    $u2->save();

    try
    {
      $msg = null;
      MockQlDao::loadOneWhere(['display' => 'Test One']);
    }
    catch(\Exception $e)
    {
      $msg = $e->getMessage();
    }
    $this->assertEquals(
      "Multiple Objects were located when trying to load one",
      $msg
    );

    $u2Test = MockQlDao::loadOneWhere(['username' => 'Tester']);
    $this->assertEquals($u2->username, $u2Test->username);

    $this->assertNull(MockQlDao::loadOneWhere(['username' => 'Missing']));

    $u->delete();
    $u2->delete();

    $resolver->shutdown();
  }

  /**
   * @expectedException \Packaged\Dal\Exceptions\DataStore\TooManyResultsException
   * @expectedExceptionMessage Too many results located
   */
  public function testMultipleExistsFailure()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockPdoConnection();
    $connection->config();
    $datastore->setConnection($connection);

    $resolver = new DalResolver();
    $resolver->boot();
    $resolver->addDataStore('mockql', $datastore);

    $connection->setResolver($resolver);

    $u1 = new MockQlDao();
    $u1->username = 'TestMultiple';
    $u1->display = 'Test One';
    $u1->save();

    $u2 = new MockQlDao();
    $u2->username = 'TestMultiple';
    $u2->display = 'Test Two';
    $u2->save();

    $test = new MockNonUniqueKeyDao();
    $test->username = 'TestMultiple';
    $test->exists();

    MockNonUniqueKeyDao::loadById('TestMultiple');
  }

  public function testDatastoreAutoConstruct()
  {
    $connection = new PdoConnection();

    $resolver = new DalResolver();
    $resolver->boot();
    $resolver->addConnection('mockql', $connection);

    $mock = new MockQlDao();
    $this->assertSame($connection, $mock->getDataStore()->getConnection());

    $resolver->shutdown();
  }

  public function testNoDataStore()
  {
    $resolver = new DalResolver();
    $resolver->boot();

    $mock = new MockQlDao();
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DalResolver\DataStoreNotFoundException'
    );
    $mock->getDataStore();
  }

  public function testChangeKey()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockPdoConnection();
    $connection->config();
    $datastore->setConnection($connection);

    $resolver = new DalResolver();
    $resolver->boot();
    $resolver->addDataStore('mockql', $datastore);

    $connection->setResolver($resolver);

    $dao = new MockQlDao();
    $dao->username = 'Test One';
    $dao->display = 'Test One';
    $dao->save();

    $oldId = $dao->id;

    $dao->id = 999;
    $dao->save();

    $this->assertEquals(999, $dao->id);

    $loadDao = MockQlDao::loadOneWhere(['id' => 999]);
    $this->assertInstanceOf(MockQlDao::class, $loadDao);
    $this->assertEquals(999, $loadDao->id);

    // check old id gone
    $missingDao = MockQlDao::loadOneWhere(['id' => $oldId]);
    $this->assertNull($missingDao);
  }
}
