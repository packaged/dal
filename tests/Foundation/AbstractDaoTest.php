<?php
namespace Foundation;

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
}

class MockAbstractDao extends AbstractDao
{
  public $name;
  public $email = 'nobody@example.com';
}
