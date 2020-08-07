<?php
namespace Packaged\Dal\Tests\Ql;

use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Tests\Ql\Mocks\MockQlDataStore;
use Packaged\Dal\Tests\Ql\Mocks\MockSetDao;
use Packaged\Dal\Tests\Ql\Mocks\PDO\MockPdoConnection;
use PHPUnit_Framework_TestCase;

class UniqueListTest extends PHPUnit_Framework_TestCase
{
  public function testCounters()
  {
    $datastore = new MockQlDataStore();
    $connection = new MockPdoConnection();
    $connection->config();
    $datastore->setConnection($connection);
    $connection->connect();
    $resolver = new DalResolver();
    $resolver->boot();
    $connection->setResolver($resolver);
    Dao::getDalResolver()->addDataStore('mockql', $datastore);

    $datastore->getConnection()->runQuery('TRUNCATE TABLE mock_set_daos');

    $dao = MockSetDao::loadOrNew('test1');
    $dao->s->add('one', 'two');
    $datastore->save($dao);

    $testDao = MockSetDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['one', 'two'])));

    $dao->s->add('three');
    $datastore->save($dao);

    $testDao = MockSetDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['one', 'two', 'three'])));

    $dao->s->remove('one');
    $datastore->save($dao);

    $testDao = MockSetDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['two', 'three'])));

    $dao->s->add('four')->remove('two');
    $datastore->save($dao);

    $testDao = MockSetDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['three', 'four'])));
  }
}
