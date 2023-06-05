<?php
namespace Packaged\Dal\Tests\Foundation;

use ArrayIterator;
use Packaged\Dal\Foundation\DaoCollection;
use Packaged\Dal\Tests\Foundation\Mocks\MockAbstractDao;
use Packaged\Dal\Tests\Foundation\Mocks\MockDaoCollection;
use Packaged\Helpers\ValueAs;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class DaoCollectionTest extends TestCase
{
  public function testCreateClass()
  {
    $collection = DaoCollection::create(MockAbstractDao::class);
    $this->assertInstanceOf(
      MockAbstractDao::class,
      $collection->createNewDao()
    );

    $collection = DaoCollection::create('stdClass');
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage("'stdClass' is not a valid DAO Class");
    $collection->createNewDao();
  }

  public function testCreateDao()
  {
    $dao = new MockAbstractDao();
    $dao->email = 'email';
    $collection = DaoCollection::create($dao);
    $this->assertSame($dao, $collection->createNewDao(false));
  }

  public function testEach()
  {
    $collection = MockDaoCollection::create();
    $collection->setDummyData();
    $collection->each(
      function ($data) {
        $this->assertObjectHasProperty('name', $data);
      }
    );
  }

  public function testFirst()
  {
    $collection = MockDaoCollection::create();
    $this->assertEquals('test', $collection->first('test'));
    $collection->setDummyData();
    $this->assertEquals(
      ValueAs::obj(['name' => 'Test', 'id' => 1]),
      $collection->first()
    );
  }

  public function testEmpty()
  {
    $collection = MockDaoCollection::create();
    $this->assertTrue($collection->isEmpty());
    $collection->setDummyData();
    $this->assertFalse($collection->isEmpty());
  }

  public function testDistinct()
  {
    $collection = MockDaoCollection::create();
    $this->assertEquals([], $collection->distinct('name'));
    $collection->setDummyData();
    $this->assertEquals(
      ["Test", "User", "Testing"],
      array_values($collection->distinct('name'))
    );
  }

  public function testPPull()
  {
    $collection = MockDaoCollection::create();
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
    $collection = MockDaoCollection::create();
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

  public function testAggregates()
  {
    $collection = MockDaoCollection::create();
    $this->assertNull($collection->min('id'));
    $this->assertNull($collection->max('id'));
    $this->assertNull($collection->avg('id'));
    $this->assertNull($collection->sum('id'));
  }

  public function testCount()
  {
    $collection = MockDaoCollection::create();
    $this->assertEquals(0, $collection->count());
    $collection->setDummyData();
    $this->assertEquals(4, $collection->count());
  }

  public function testJsonSerialize()
  {
    $collection = MockDaoCollection::create();
    $this->assertEquals([], $collection->jsonSerialize());
    $collection->setDummyData();
    $this->assertEquals(
      [
        ['name' => 'Test', 'id' => 1],
        ['name' => 'Test', 'id' => 2],
        ['name' => 'User', 'id' => 5],
        ['name' => 'Testing', 'id' => 8, 'email' => 'nobody@example.com', 'age' => 0],
      ],
      $collection->jsonSerialize()
    );
  }

  public function testToString()
  {
    $collection = MockDaoCollection::create();
    $this->assertEquals("[]", (string)$collection);
    $collection->setDummyData();
    $this->assertEquals(
      '[{"name":"Test","id":1},'
      . '{"name":"Test","id":2},'
      . '{"name":"User","id":5},'
      . '{"name":"Testing","email":"nobody@example.com","age":0,"id":8}]',
      (string)$collection
    );
  }

  public function testArrayUsage()
  {
    $collection = MockDaoCollection::create();
    $collection->setDummyData();
    $this->assertTrue(isset($collection[8]));
    $removal = $collection[8];
    unset($collection[8]);
    $this->assertFalse(isset($collection[8]));
    $collection[8] = $removal;
    $this->assertTrue(isset($collection[8]));
    $new = new stdClass();
    $new->name = 'Brooke';
    $collection[] = $new;
    /**
     * @var $collection DaoCollection
     */
    $this->assertTrue(in_array('Brooke', $collection->distinct('name')));
  }

  public function testIterator()
  {
    $collection = MockDaoCollection::create();
    $collection->setDummyData();
    $this->assertEquals(
      new ArrayIterator($collection->getDaos()),
      $collection->getIterator()
    );
  }

  public function testGetRaw()
  {
    $collection = MockDaoCollection::create();
    $collection->setDummyData();
    $this->assertEquals($collection->getDaos(), $collection->getRawArray());
  }
}
