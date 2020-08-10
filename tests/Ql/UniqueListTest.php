<?php
namespace Packaged\Dal\Tests\Ql;

use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Tests\Ql\Mocks\MockQlDataStore;
use Packaged\Dal\Tests\Ql\Mocks\MockSetDao;
use Packaged\Dal\Tests\Ql\PDO\Mocks\MockPdoConnection;
use PHPUnit\Framework\TestCase;

class UniqueListTest extends TestCase
{
  public function testUniqueList()
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

    $dao->s->remove('three', 'four')->add('one', 'two', 'five');
    $datastore->save($dao);

    $testDao = MockSetDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['one', 'two', 'five'])));

    $dao->s->remove('five')->add('five');
    $changes = $datastore->save($dao);
    $this->assertEmpty($changes);

    $testDao = MockSetDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['one', 'two', 'five'])));

    $dao->s->setValue(['one'])->remove('one');
    $datastore->save($dao);

    $testDao = MockSetDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['one'])));

    $dao->s->setValue(['one'])->remove('one')->add('two');
    $datastore->save($dao);

    $testDao = MockSetDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['two'])));

    $dao->s->setValue(['one'])->add('two');
    $datastore->save($dao);

    $testDao = MockSetDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['one', 'two'])));
  }
}
