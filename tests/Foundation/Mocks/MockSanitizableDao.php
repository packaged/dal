<?php
namespace Packaged\Dal\Tests\Foundation\Mocks;

use Exception;
use Packaged\Dal\Foundation\AbstractSanitizableDao;
use Packaged\Helpers\Strings;

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
  public $json_array;
  public $php;
  public $filtration;
  public $lower;
  public $upper;
  public $cereal;
  public $validator;

  public function __call($method, $args)
  {
    $method = '_' . $method;
    return call_user_func_array([$this, $method], $args);
  }

  protected function _configure()
  {
    $this->_sanetizers['validators']['lower'] = [
      function ($value) {
        if(strtolower($value) === $value)
        {
          return true;
        }
        throw new Exception("Not a lower case string");
      },
      function ($value) {
        if(Strings::startsWith($value, 'lower'))
        {
          return true;
        }
        throw new Exception("String must start with text 'lower'");
      },
    ];

    $this->_sanetizers['validators']['upper'] = [
      function ($value) {
        if(Strings::startsWith($value, 'UPPER'))
        {
          return true;
        }
        throw new Exception("String must start with text 'UPPER'");
      },
    ];
  }
}
