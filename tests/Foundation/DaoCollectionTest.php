<?php
namespace Foundation;

use Packaged\Dal\Foundation\DaoCollection;
use Packaged\Helpers\ValueAs;

class DaoCollectionTest extends \PHPUnit_Framework_TestCase
{
  public function testCreateClass()
  {
    $collection = DaoCollection::create(MockAbstractDao::class);
    $this->assertInstanceOf(
      MockAbstractDao::class,
      $collection->createNewDao()
    );

    $collection = DaoCollection::create('stdClass');
    $this->setExpectedException(
      "RuntimeException",
      "'stdClass' is not a valid DAO Class"
    );
    $collection->createNewDao();
  }

  public function testEach()
  {
    $collection = new MockDaoCollection();
    $collection->setDummyData();
    $collection->each(
      function ($data)
      {
        $this->assertObjectHasAttribute('name', $data);
      }
    );
  }

  public function testEmpty()
  {
    $collection = new MockDaoCollection();
    $this->assertTrue($collection->isEmpty());
    $collection->setDummyData();
    $this->assertFalse($collection->isEmpty());
  }

  public function testDistinct()
  {
    $collection = new MockDaoCollection();
    $this->assertEquals([], $collection->distinct('name'));
    $collection->setDummyData();
    $this->assertEquals(
      ["Test", "User", "Testing"],
      array_values($collection->distinct('name'))
    );
  }

  public function testPPull()
  {
    $collection = new MockDaoCollection();
    $this->assertEquals([], $collection->ppull('name'));
    $collection->setDummyData();
    $this->assertEquals(
      ["Test", "Test", "User", "Testing"],
      array_values($collection->ppull('name'))
    );
    $this->assertEquals(
      [1 => "Test", 2 => "Test", 5 => "User", 8 => "Testing"],
      $collection->ppull('name', 'id')
    );
  }

  public function testApull()
  {
    $collection = new MockDaoCollection();
    $this->assertEquals([], $collection->apull(['name', 'id']));
    $collection->setDummyData();
    $this->assertEquals(
      [
        ['name' => 'Test', 'id' => 1],
        ['name' => 'Test', 'id' => 2],
        ['name' => 'User', 'id' => 5],
        ['name' => 'Testing', 'id' => 8],
      ],
      array_values($collection->apull(['name', 'id']))
    );
    $this->assertEquals(
      [
        1 => ['name' => 'Test', 'id' => 1],
        2 => ['name' => 'Test', 'id' => 2],
        5 => ['name' => 'User', 'id' => 5],
        8 => ['name' => 'Testing', 'id' => 8],
      ],
      $collection->apull(['name', 'id'], 'id')
    );
  }

  public function testCount()
  {
    $collection = new MockDaoCollection();
    $this->assertEquals(0, $collection->count());
    $collection->setDummyData();
    $this->assertEquals(4, $collection->count());
  }

  public function testJsonSerialize()
  {
    $collection = new MockDaoCollection();
    $this->assertEquals([], $collection->jsonSerialize());
    $collection->setDummyData();
    $this->assertEquals(
      [
        ['name' => 'Test', 'id' => 1],
        ['name' => 'Test', 'id' => 2],
        ['name' => 'User', 'id' => 5],
        ['name' => 'Testing', 'id' => 8, 'email' => 'nobody@example.com'],
      ],
      $collection->jsonSerialize()
    );
  }

  public function testToString()
  {
    $collection = new MockDaoCollection();
    $this->assertEquals("[]", (string)$collection);
    $collection->setDummyData();
    $this->assertEquals(
      '[{"name":"Test","id":1},'
      . '{"name":"Test","id":2},'
      . '{"name":"User","id":5},'
      . '{"name":"Testing","email":"nobody@example.com","id":8}]',
      (string)$collection
    );
  }

  public function testArrayUsage()
  {
    $collection = new MockDaoCollection();
    $collection->setDummyData();
    $this->assertTrue(isset($collection[8]));
    $removal = $collection[8];
    unset($collection[8]);
    $this->assertFalse(isset($collection[8]));
    $collection[8] = $removal;
    $this->assertTrue(isset($collection[8]));
    $new          = new \stdClass();
    $new->name    = 'Brooke';
    $collection[] = $new;
    /**
     * @var $collection DaoCollection
     */
    $this->assertTrue(in_array('Brooke', $collection->distinct('name')));
  }

  public function testIterator()
  {
    $collection = new MockDaoCollection();
    $collection->setDummyData();
    $this->assertEquals(
      new \ArrayIterator($collection->getDaos()),
      $collection->getIterator()
    );
  }

  public function testGetRaw()
  {
    $collection = new MockDaoCollection();
    $collection->setDummyData();
    $this->assertEquals($collection->getDaos(), $collection->getRawArray());
  }
}

class MockDaoCollection extends DaoCollection
{
  public function setDaos(array $daos)
  {
    $this->_daos = $daos;
    return $this;
  }

  public function getDaos()
  {
    return $this->_daos;
  }

  public function setDummyData()
  {
    $this->_daos    = [];
    $this->_daos[1] = ValueAs::obj(['name' => 'Test', 'id' => 1]);
    $this->_daos[2] = ValueAs::obj(['name' => 'Test', 'id' => 2]);
    $user           = new MockUsr();
    $user->name     = 'User';
    $user->id       = 5;
    $this->_daos[5] = $user;
    $mock           = new MockAbstractDao();
    $mock->name     = 'Testing';
    $mock->id       = 8;
    $this->_daos[8] = $mock;
  }
}

class MockUsr
{
  public $name;
  public $id;
}
