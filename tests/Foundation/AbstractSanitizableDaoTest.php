<?php
namespace Packaged\Dal\Tests\Foundation;

use Exception;
use Packaged\Dal\Exceptions\Dao\DaoException;
use Packaged\Dal\Tests\Foundation\Mocks\MockSanitizableDao;
use Packaged\Helpers\Strings;
use PHPUnit\Framework\TestCase;
use stdClass;

class AbstractSanitizableDaoTest extends TestCase
{
  public function testUnserialize()
  {
    $c = new MockSanitizableDao();
    $c->id = ['idtest', 'test2'];
    $c->json_array = ['jtest'];
    $daoStr = serialize($c);

    /** @var MockSanitizableDao $dao */
    $dao = unserialize($daoStr);
    $this->assertInstanceOf(MockSanitizableDao::class, $dao);
    $this->assertEquals(
      [
        'id',
        'json',
        'json_array',
        'php',
        'filtration',
        'lower',
        'upper',
        'cereal',
        'validator',
      ],
      $dao->getDaoProperties()
    );

    // check reserialize
    $this->assertEquals($daoStr, serialize($dao));

    // check changes
    $changes = $dao->getDaoChanges();
    $this->assertEquals(
      [
        'id' =>
          [
            'from' => null,
            'to'   => ['idtest', 'test2'],
          ],
        'json_array' =>
          [
            'from' => null,
            'to'   => ['jtest'],
          ],
      ],
      $changes
    );
  }

  public function testFailSerialize()
  {
    $this->expectException(DaoException::class);
    $this->expectExceptionMessage(
      'Failed to serialize property "json" in "Packaged\Dal\Tests\Foundation\Mocks\MockSanitizableDao". Malformed UTF-8 characters, possibly incorrectly encoded'
    );

    $mock = new MockSanitizableDao();
    $mock->id = ['idtest', 'test_fail'];

    $value = new stdClass();
    $value->a = 'B';
    $value->c = "\xc3\x28";

    $mock->addSerializer('json');
    $mock->getPropertySerialized('json', $value);
  }

  public function testFailUnserialize()
  {
    $mock = new MockSanitizableDao();
    $mock->id = ['idtest', 'test_fail'];
    $mock->addSerializer('json');

    $errLog = sys_get_temp_dir() . '/dal_test_err';
    $oldLog = ini_set('error_log', $errLog);
    $mock->getPropertyUnserialized('json', '"\xc3\x28"');
    ini_set('error_log', $oldLog);
    $this->assertStringContainsString(
      'Failed to unserialize property "json" in "Packaged\Dal\Tests\Foundation\Mocks\MockSanitizableDao". Syntax error',
      file_get_contents($errLog)
    );
    unlink($errLog);
  }

  public function testSerialize()
  {
    $value = new stdClass();
    $value->a = 'B';
    $value->c = 'd';

    $mock = new MockSanitizableDao();
    $mock->id = ['idtest', 'test2'];

    $mock->addSerializer('json');

    $jsonSerialized = $mock->getPropertySerialized('json', $value);
    $this->assertEquals(
      $value,
      $mock->getPropertyUnserialized('json', $jsonSerialized)
    );

    $mock->addSerializer('json_array', 'json', MockSanitizableDao::SERIALIZATION_JSON_ARRAY);
    $jsonAssocSerialized = $mock->getPropertySerialized('json_array', $value);
    $this->assertEquals(
      json_decode(json_encode($value), true),
      $mock->getPropertyUnserialized('json_array', $jsonAssocSerialized)
    );

    $mock->addSerializer('php', 'json', MockSanitizableDao::SERIALIZATION_PHP);
    $phpSerialized = $mock->getPropertySerialized('php', $value);
    $this->assertEquals(
      $value,
      $mock->getPropertyUnserialized('php', $phpSerialized)
    );

    $mock->hydrateDao(
      ['json' => $jsonSerialized, 'php' => $phpSerialized, 'json_array' => $jsonAssocSerialized],
      true
    );

    $mock->addSerializer('id');
    $this->assertEquals(['idtest', 'test2'], $mock->getId(false, false));
    $this->assertEquals('["idtest","test2"]', $mock->getId(false, true));

    $this->assertEquals($value, $mock->php);
    $this->assertEquals($value, $mock->json);
    $this->assertEquals(json_decode(json_encode($value), true), $mock->json_array);

    $mock->clearSerializers('php');
    $mock->hydrateDao(
      ['json' => $jsonSerialized, 'php' => $phpSerialized, 'json_array' => $jsonAssocSerialized],
      true
    );
    $this->assertEquals($phpSerialized, $mock->php);
  }

  public function testCustomSerialize()
  {
    $mock = new MockSanitizableDao();
    $mock->addCustomSerializer(
      'cereal',
      'rev',
      function ($value) { return strrev($value); },
      function ($value) { return strrev($value); }
    );

    $value = 'forward';
    $customSerialized = $mock->getPropertySerialized('cereal', $value);
    $this->assertEquals('drawrof', $customSerialized);
    $this->assertEquals(
      $value,
      $mock->getPropertyUnserialized('cereal', $customSerialized)
    );

    $mock->removeSerializer('cereal', 'rev');
    $this->assertEquals($value, $mock->getPropertySerialized('cereal', $value));

    //Check double serialize
    $mock->addSerializer(
      'cereal',
      'json',
      MockSanitizableDao::SERIALIZATION_JSON
    );
    $mock->addSerializer(
      'cereal',
      'php',
      MockSanitizableDao::SERIALIZATION_PHP
    );

    $value = new stdClass();
    $value->mock = 'hat';
    $value->prop = 'stand';

    $serialized = $mock->getPropertySerialized('cereal', $value);
    $this->assertEquals('s:29:"{"mock":"hat","prop":"stand"}";', $serialized);
    $this->assertEquals(
      $value,
      $mock->getPropertyUnserialized('cereal', $serialized)
    );
  }

  public function testFilter()
  {
    $mock = new MockSanitizableDao();
    $mock->addFilter(
      'filtration',
      'strtolower',
      function ($value) { return strtolower($value); }
    );
    $mock->setDaoProperty('filtration', 'this Is SOME Crazy CAsE');
    $this->assertEquals('this is some crazy case', $mock->filtration);
    $mock->addFilter(
      'filtration',
      'ucwords',
      function ($value) { return ucwords($value); }
    );
    $mock->setDaoProperty('filtration', 'this Is SOME Crazy CAsE');
    $this->assertEquals('This Is Some Crazy Case', $mock->filtration);

    $mock->removeFilter('filtration', 'ucwords');
    $mock->setDaoProperty('filtration', 'this Is SOME Crazy CAsE');
    $this->assertEquals('this is some crazy case', $mock->filtration);

    $mock->clearFilters('filtration');
    $mock->setDaoProperty('filtration', 'this Is SOME Crazy CAsE');
    $this->assertEquals('this Is SOME Crazy CAsE', $mock->filtration);
  }

  public function testValidate()
  {
    $mock = new MockSanitizableDao();

    $mock->addValidator(
      'validator',
      'false',
      function () { return false; }
    );
    $result = $mock->validateDaoProperty('validator', '');
    $this->assertIsArray($result);
    $this->assertEquals(
      "An unknown error occurred when validating validator",
      $result[0]->getMessage()
    );

    $mock->clearValidators('lower');
    $mock->lower = 'UPPER string';
    $this->assertTrue($mock->validateDaoProperty('lower', $mock->lower));

    $mock->addValidator(
      'lower',
      'lower',
      function ($value) {
        if(strtolower($value) === $value)
        {
          return true;
        }
        throw new Exception("Not a lower case string");
      }
    );
    $this->assertNotEquals(
      true,
      $mock->validateDaoProperty('lower', $mock->lower)
    );

    $mock->lower = 'upper string that isnt';
    $this->assertTrue($mock->validateDaoProperty('lower', $mock->lower));

    $mock->addValidator(
      'lower',
      'starts_lower',
      function ($value) {
        if(Strings::startsWith($value, 'lower'))
        {
          return true;
        }
        throw new Exception("String must start with text 'lower'");
      }
    );
    $this->assertNotEquals(
      true,
      $mock->validateDaoProperty('lower', $mock->lower)
    );
    $mock->lower = 'lower string';
    $this->assertTrue($mock->validateDaoProperty('lower', $mock->lower));

    $mock->lower = 'This Is Not Lower';
    $this->assertNotEquals(
      true,
      $mock->validateDaoProperty('lower', $mock->lower)
    );

    $exceptions = $mock->validateDaoProperty('lower', $mock->lower, false);
    $this->assertCount(2, $exceptions);
    $this->assertContainsOnlyInstancesOf('\Exception', $exceptions);

    $mock->removeValidator('lower', 'starts_lower');
    $exceptions = $mock->validateDaoProperty('lower', $mock->lower, false);
    $this->assertCount(1, $exceptions);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Not a lower case string');
    $mock->validateDaoProperty('lower', $mock->lower, true, true);
  }

  public function testIsValid()
  {
    $mock = new MockSanitizableDao();
    $mock->lower = 'lower string';
    $mock->upper = 'UPPER TEXT';
    $this->assertTrue($mock->isValid());

    $mock->lower = 'Upper Lower';
    $this->assertArrayHasKey('lower', $mock->isValid());

    $mock->upper = 'Not upper';
    $this->assertArrayHasKey('lower', $mock->isValid());
    $this->assertArrayHasKey('upper', $mock->isValid());

    $this->assertArrayHasKey('upper', $mock->isValid(['upper']));
    $this->assertArrayNotHasKey('lower', $mock->isValid(['upper']));

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Not a lower case string');
    $mock->isValid(null, true);
  }

  public function testGetPropertyData()
  {
    $mock = new MockSanitizableDao();
    $mock->addSerializer('json');
    $mock->json = new stdClass();
    $mock->json->name = 'test';

    $this->assertEquals($mock->json, $mock->getDaoPropertyData(false)['json']);
    $this->assertEquals(
      '{"name":"test"}',
      $mock->getDaoPropertyData(true)['json']
    );
  }
}
