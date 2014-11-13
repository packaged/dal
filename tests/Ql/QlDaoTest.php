<?php
namespace Ql;

use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\PdoConnection;
use Packaged\QueryBuilder\Assembler\QueryAssembler;

class QlDaoTest extends \PHPUnit_Framework_TestCase
{
  public function testStatics()
  {
    $datastore  = new MockQlDataStore();
    $connection = new PdoConnection();
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

    $u           = new MockQlDao();
    $u->username = 'Test';
    $u->display  = 'Test One';
    $u->save();
    $mocks = MockQlDao::collection(['username' => 'Test']);
    $this->assertCount(1, $mocks);

    foreach(MockQlDao::each(['username' => 'Test']) as $usr)
    {
      $this->assertInstanceOf(MockQlDao::class, $usr);
    }

    $preloaded = MockQlDao::loadWhere(['username' => 'Test']);
    $this->assertCount(1, $preloaded);

    $u2           = new MockQlDao();
    $u2->username = 'Tester';
    $u2->display  = 'Test One';
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

  protected function tearDown()
  {
    Dao::unsetDalResolver();
    parent::tearDown();
  }
}
