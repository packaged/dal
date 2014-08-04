<?php
namespace Foundation;

use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\AbstractDao;

class AbstractDaoTest extends \PHPUnit_Framework_TestCase
{
  public function testGetProperties()
  {
    $dao = new MockAbstractDao();
    $this->assertEquals(['name', 'email'], $dao->getDaoProperties());
  }

  public function testGetPropertyData()
  {
    $dao = new MockAbstractDao();

    $dao->name = 'John Smith';
    $this->assertEquals(
      ['name' => 'John Smith', 'email' => 'nobody@example.com'],
      $dao->getDaoPropertyData()
    );

    $dao->email = 'john@example.com';
    $this->assertEquals(
      ['name' => 'John Smith', 'email' => 'john@example.com'],
      $dao->getDaoPropertyData()
    );
  }

  public function testGetChanges()
  {
    $dao = new MockAbstractDao();
    $this->assertEmpty($dao->getDaoChanges());
    $dao->markDaoDatasetAsSaved();
    $dao->email = 'john@example.com';
    $this->assertEquals(
      ['email' => ['from' => 'nobody@example.com', 'to' => $dao->email]],
      $dao->getDaoChanges()
    );
  }

  public function testPropertyGetSet()
  {
    $dao = new MockAbstractDao();
    $this->assertEquals('nobody@example.com', $dao->getDaoProperty('email'));
    $dao->setDaoProperty('email', 'john@example.com');
    $this->assertEquals('john@example.com', $dao->getDaoProperty('email'));
  }

  public function testHydrate()
  {
    $dao = new MockAbstractDao();
    $dao->hydrateDao(['name' => 'John Smith']);
    $this->assertEquals(
      ['name' => 'John Smith', 'email' => 'nobody@example.com'],
      $dao->getDaoPropertyData()
    );
  }

  public function testOverHydrate()
  {
    $dao = new MockAbstractDao();
    $dao->hydrateDao(['name' => 'John Smith', 'nondao' => 'miss']);
    $this->assertEquals(
      ['name' => 'John Smith', 'email' => 'nobody@example.com'],
      $dao->getDaoPropertyData()
    );
    $this->assertFalse(isset($dao->nodao));
  }

  public function testGetIDProperties()
  {
    $this->assertEquals(['id'], (new MockAbstractDao())->getDaoIDProperties());
  }

  public function testDalResolver()
  {
    $resolver = new DalResolver();
    $resolver->addDataStore('test', $this->getMock('\Packaged\Dal\IDataStore'));
    AbstractDao::setDalResolver($resolver);
    $mock = new MockAbstractDao();
    $mock->init();
    $this->assertInstanceOf('\Packaged\Dal\IDataStore', $mock->getDataStore());
    $this->assertSame($resolver, $mock->getDalResolver());
    AbstractDao::unsetDalResolver();
    $this->assertNull($mock->getDalResolver());
  }
}

class MockAbstractDao extends AbstractDao
{
  public $name;
  public $email = 'nobody@example.com';

  public function init()
  {
    $this->_setDataStoreName('test');
  }
}
