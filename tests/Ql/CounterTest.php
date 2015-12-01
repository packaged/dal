<?php
namespace Ql;

use Packaged\Dal\DalResolver;
use Packaged\Dal\DataTypes\Counter;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\QlDao;
use Packaged\Dal\Ql\QlDataStore;

class CounterTest extends \PHPUnit_Framework_TestCase
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

    $datastore->getConnection()->runQuery('TRUNCATE TABLE mock_counter_daos');

    $dao = MockCounterDao::loadOrNew('test1');
    $dao->c1->increment(10);
    $dao->c1->decrement(5);
    $datastore->save($dao);
    $dao->c2->increment(1);
    $dao->c2->decrement(3);
    $datastore->save($dao);

    $dao = new MockCounterDao();
    $dao->id = 'test1';
    $dao->c2->increment(1);
    $dao->c2->decrement(3);
    $datastore->save($dao);

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(5, $dao->c1->calculated());
    $this->assertEquals(-4, $dao->c2->calculated());

    $dao = MockCounterDao::loadById('test1');
    $dao->c1->increment(0);
    $datastore->save($dao);
    $dao->c1->increment(10);
    $datastore->save($dao);
    $dao->c1->increment(15);
    $datastore->save($dao);
    $dao->c3->increment(8);
    $datastore->save($dao);
    $dao->c3->increment(9.7);
    $datastore->save($dao);
    $dao->c3->increment(99);
    $datastore->save($dao);
    $dao->c3->increment(1.3);
    $datastore->save($dao);
    $dao->c3->increment(0.0);
    $datastore->save($dao);
    $dao->c3->increment(null);
    $datastore->save($dao);
    $dao->c3->increment('goat');
    $datastore->save($dao);
    $dao->c3->increment(
      '99
 a'
    );
    $datastore->save($dao);
    $dao->c2->increment(0);
    $datastore->save($dao);

    $dao1 = MockCounterDao::loadById('test1');
    $dao1->c1->increment(7);
    $dao2 = MockCounterDao::loadById('test1');
    $dao2->c1->increment(3);
    $dao1->save();
    $dao2->save();

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(40,$dao->c1->calculated());

    $this->assertEquals(217, $dao->c3->calculated());
    $dao = new MockCounterDao();
    $dao->id = 'test1';
    $dao->markDaoAsLoaded();
    $dao->markDaoDatasetAsSaved();
    $dao->c3->increment('0.00');
    $datastore->save($dao);

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(217, $dao->c3->calculated());

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(40, $dao->c1->calculated());
    $this->assertEquals(-4, $dao->c2->calculated());
    $this->assertEquals(217, $dao->c3->calculated());

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(40, $dao->c1->calculated());

    $dao->c1 = 100;
    $dao->save();

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(100, $dao->c1->calculated());

    $dao->c1->setValue(500);
    $dao->save();

    $dao = MockCounterDao::loadById('test1');
    $this->assertEquals(500, $dao->c1->calculated());

    $json = json_encode($dao);
    $this->assertEquals(
      '{"id":"test1","c1":"500","c2":"-4","c3":"217.00"}',
      $json
    );

    $dao = new MockCounterDao();
    $dao->id = 'test1';
    $dao->c1->setValue(6);
    $dao->c2->setValue(-8);
    $datastore->save($dao);

    $json = json_encode($dao);
    $this->assertEquals('{"id":"test1","c1":"6","c2":"-8","c3":"0"}', $json);
  }
}

class MockCounterDao extends QlDao
{
  protected $_dataStoreName = 'mockql';
  protected $_ttl;

  public $id;
  /**
   * @counter
   * @var Counter
   */
  public $c1;
  /**
   * @counter
   * @var Counter
   */
  public $c2;
  /**
   * @counter
   * @var Counter
   */
  public $c3;

  protected $_dataStore;

  protected $_tableName = 'mock_counter_daos';

  public function getTableName()
  {
    return $this->_tableName;
  }

  public function setTableName($table)
  {
    $this->_tableName = $table;
    return $this;
  }

  public function getTtl()
  {
    return $this->_ttl;
  }

  public function setTtl($ttl)
  {
    $this->_ttl = $ttl;
    return $this;
  }

  public function setDataStore(QlDataStore $store)
  {
    $this->_dataStore = $store;
    return $this;
  }

  public function getDataStore()
  {
    if($this->_dataStore === null)
    {
      return parent::getDataStore();
    }
    return $this->_dataStore;
  }
}
