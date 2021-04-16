<?php
namespace Packaged\Dal\Tests\Ql\Cql;

use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Tests\Ql\Cql\Mocks\MockCqlConnection;
use Packaged\Dal\Tests\Ql\Cql\Mocks\MockCqlDataStore;
use Packaged\Dal\Tests\Ql\Cql\Mocks\MockSetCqlDao;
use PHPUnit\Framework\TestCase;

class UniqueListCqlTest extends TestCase
{
  public function testUniqueList()
  {
    $datastore = new MockCqlDataStore();
    $connection = new MockCqlConnection();
    $connection->setConfig('keyspace', 'packaged_dal');
    $datastore->setConnection($connection);
    $connection->connect();
    $resolver = new DalResolver();
    $resolver->boot();
    $connection->setResolver($resolver);
    Dao::getDalResolver()->addDataStore('mockcql', $datastore);

    $connection->runQuery('TRUNCATE TABLE packaged_dal.mock_set_daos');

    $dao = MockSetCqlDao::loadOrNew('test1');
    $dao->s->add('one', 'two');
    $datastore->save($dao);

    $testDao = MockSetCqlDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['one', 'two'])));

    $dao->s->add('three');
    $datastore->save($dao);

    $testDao = MockSetCqlDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['one', 'two', 'three'])));

    $dao->s->remove('one');
    $datastore->save($dao);

    $testDao = MockSetCqlDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['two', 'three'])));

    $dao->s->add('four')->remove('two');
    $datastore->save($dao);

    $testDao = MockSetCqlDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['three', 'four'])));

    $dao->s->remove('three', 'four')->add('one', 'two', 'five');
    $datastore->save($dao);

    $testDao = MockSetCqlDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['one', 'two', 'five'])));

    $dao->s->remove('five')->add('five');
    $changes = $datastore->save($dao);
    $this->assertEmpty($changes);

    $testDao = MockSetCqlDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['one', 'two', 'five'])));

    $dao->s->setValue(['one'])->remove('one');
    $datastore->save($dao);

    $testDao = MockSetCqlDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['one'])));

    $dao->s->setValue(['one'])->remove('one')->add('two');
    $datastore->save($dao);

    $testDao = MockSetCqlDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['two'])));

    $dao->s->setValue(['one'])->add('two');
    $datastore->save($dao);

    $testDao = MockSetCqlDao::loadById('test1');
    $this->assertEquals(0, count(array_diff($testDao->s->calculated(), ['one', 'two'])));
  }
}
