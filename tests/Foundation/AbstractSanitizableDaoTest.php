<?php
namespace Tests\Foundation;

use Packaged\Dal\Foundation\AbstractSanitizableDao;
use Packaged\Helpers\Strings;

class AbstractSanitizableDaoTest extends \PHPUnit_Framework_TestCase
{
  public function testUnserialize()
  {
    $daoStr = 'O:35:"Tests\Foundation\MockSanitizableDao":12:{s:2:"id";a:2:{i:0;s:6:"'
      . 'idtest";i:1;s:5:"test2";}s:4:"json";N;s:3:"php";N;s:10:"filtration";'
      . 'N;s:5:"lower";N;s:5:"upper";N;s:6:"cereal";N;s:9:"validator";'
      . 'N;s:17:" * _dataStoreName";N;s:13:" * _savedData";a:8:{s:2:"id'
      . '";N;s:4:"json";N;s:3:"php";N;s:10:"filtration";N;s:5:"lower";N;s:5:'
      . '"upper";N;s:6:"cereal";N;s:9:"validator";N;}s:15:" * _called'
      . 'Class";s:35:"Tests\Foundation\MockSanitizableDao";s:12:" * _isLoaded";N;}';

    $dao = unserialize($daoStr);
    $this->assertInstanceOf(MockSanitizableDao::class, $dao);
    $this->assertEquals(
      [
        'id',
        'json',
        'php',
        'filtration',
        'lower',
        'upper',
        'cereal',
        'validator',
      ],
      $dao->getDaoProperties()
    );

    $serialize = serialize($dao);
    $this->assertEquals($daoStr, $serialize);
  }

  /**
   * @expectedException \Packaged\Dal\Exceptions\Dao\DaoException
   * @expectedExceptionMessage Failed to serialize property "json" in "Tests\Foundation\MockSanitizableDao". Malformed UTF-8
   *                           characters, possibly incorrectly encoded
   */
  public function testFailSerialize()
  {
    $mock = new MockSanitizableDao();
    $mock->id = ['idtest', 'test_fail'];

    $value = new \stdClass();
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
    $this->assertContains(
      'Failed to unserialize property "json" in "Tests\Foundation\MockSanitizableDao". Syntax error',
      file_get_contents($errLog)
    );
    unlink($errLog);
  }

  public function testSerialize()
  {
    $value = new \stdClass();
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

    $mock->addSerializer('id');
    $this->assertEquals(['idtest', 'test2'], $mock->getId(false, false));
    $this->assertEquals('["idtest","test2"]', $mock->getId(false, true));

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

    $value = new \stdClass();
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
      function ($value) {
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
      function ($value) {
        if(Strings::startsWith($value, 'lower'))
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

    $this->setExpectedException('\Exception', 'Not a lower case string');
    $mock->isValid(null, true);
  }

  public function testGetPropertyData()
  {
    $mock = new MockSanitizableDao();
    $mock->addSerializer('json');
    $mock->json = new \stdClass();
    $mock->json->name = 'test';

    $this->assertEquals($mock->json, $mock->getDaoPropertyData(false)['json']);
    $this->assertEquals(
      '{"name":"test"}',
      $mock->getDaoPropertyData(true)['json']
    );
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
  public $id;
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
      function ($value) {
        if(strtolower($value) === $value)
        {
          return true;
        }
        throw new \Exception("Not a lower case string");
      },
      function ($value) {
        if(Strings::startsWith($value, 'lower'))
        {
          return true;
        }
        throw new \Exception("String must start with text 'lower'");
      },
    ];

    $this->_sanetizers['validators']['upper'] = [
      function ($value) {
        if(Strings::startsWith($value, 'UPPER'))
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
