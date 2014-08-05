<?php
namespace Foundation;

use Packaged\Dal\Foundation\AbstractSanitizableDao;

class AbstractSanitizableDaoTest extends \PHPUnit_Framework_TestCase
{
  public function testSerialize()
  {
    $value    = new \stdClass();
    $value->a = 'B';
    $value->c = 'd';

    $mock = new MockSanitizableDao();
    $mock->addSerializer('json');

    $jsonSerialized = $mock->getPropertySerialized('json', $value);
    $this->assertEquals(
      $value,
      $mock->getPropertyUnserialized('json', $jsonSerialized)
    );

    $mock->addSerializer('php', 'json', MockSanitizableDao::SERIALIZATION_PHP);
    $phpSerialized = $mock->getPropertySerialized('php', $value);
    $this->assertEquals(
      $value,
      $mock->getPropertyUnserialized('php', $phpSerialized)
    );

    $mock->hydrateDao(
      ['json' => $jsonSerialized, 'php' => $phpSerialized],
      true
    );

    $this->assertEquals($value, $mock->php);
    $this->assertEquals($value, $mock->json);

    $mock->clearSerializers('php');
    $mock->hydrateDao(
      ['json' => $jsonSerialized, 'php' => $phpSerialized],
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

    $value            = 'forward';
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

    $value       = new \stdClass();
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
    $this->assertInternalType('array', $result);
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
      function ($value)
      {
        if(strtolower($value) === $value)
        {
          return true;
        }
        throw new \Exception("Not a lower case string");
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
      function ($value)
      {
        if(starts_with($value, 'lower'))
        {
          return true;
        }
        throw new \Exception("String must start with text 'lower'");
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

    $this->setExpectedException('\Exception', 'Not a lower case string');
    $mock->validateDaoProperty('lower', $mock->lower, true, true);
  }

  public function testIsValid()
  {
    $mock        = new MockSanitizableDao();
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

    $this->setExpectedException('\Exception', 'Not a lower case string');
    $mock->isValid(null, true);
  }
}

/**
 * @method addSerializer
 * @method addCustomSerializer
 * @method removeSerializer
 * @method clearSerializers
 * @method addFilter
 * @method removeFilter
 * @method clearFilters
 * @method addValidator
 * @method removeValidator
 * @method clearValidators
 */
class MockSanitizableDao extends AbstractSanitizableDao
{
  public $json;
  public $php;
  public $filtration;
  public $lower;
  public $upper;
  public $cereal;
  public $validator;

  protected function _configure()
  {
    $this->_sanetizers['validators']['lower'] = [
      function ($value)
      {
        if(strtolower($value) === $value)
        {
          return true;
        }
        throw new \Exception("Not a lower case string");
      },
      function ($value)
      {
        if(starts_with($value, 'lower'))
        {
          return true;
        }
        throw new \Exception("String must start with text 'lower'");
      },
    ];

    $this->_sanetizers['validators']['upper'] = [
      function ($value)
      {
        if(starts_with($value, 'UPPER'))
        {
          return true;
        }
        throw new \Exception("String must start with text 'UPPER'");
      },
    ];
  }

  public function __call($method, $args)
  {
    $method = '_' . $method;
    return call_user_func_array([$this, $method], $args);
  }
}
