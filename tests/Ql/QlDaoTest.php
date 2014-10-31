<?php
namespace Ql;

use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\PdoConnection;

class QlDaoTest extends \PHPUnit_Framework_TestCase
{
  public function testStatics()
  {
    $datastore  = new MockQlDataStore();
    $connection = new PdoConnection();
    $datastore->setConnection($connection);

    $collection = MockQlDao::collection();
    $this->assertInstanceOf(MockQlDao::class, $collection->createNewDao());

    $resolver = new DalResolver();
    $resolver->boot();
    $resolver->addDataStore('mockql', $datastore);

    $u           = new MockQlDao();
    $u->username = 'Test';
    $u->save();
    $mocks = MockQlDao::loadWhere(['username' => 'Test']);
    $this->assertTrue(count($mocks) === 1);
    $u->delete();

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