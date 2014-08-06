<?php
namespace Packaged\Dal\Foundation;

use Packaged\Dal\ISanitizableDao;

abstract class AbstractSanitizableDao extends AbstractDao
  implements ISanitizableDao
{
  const SERIALIZATION_NONE = 'none';
  const SERIALIZATION_JSON = 'json';
  const SERIALIZATION_PHP = 'php';

  /**
   * @var callable[]
   */
  protected $_sanetizers = [
    'filters'     => [],
    'validators'  => [],
    'serializers' => []
  ];

  /**
   * Set the value of a property, and filter when setting
   *
   * @param $key
   * @param $value
   *
   * @return self
   */
  public function setDaoProperty($key, $value)
  {
    return parent::setDaoProperty($key, $this->filterDaoProperty($key, $value));
  }

  /**
   * Filter the value for a property
   *
   * @param $key
   * @param $value
   *
   * @return mixed filtered value of the property
   */
  public function filterDaoProperty($key, $value)
  {
    if(isset($this->_sanetizers['filters'][$key]))
    {
      foreach($this->_sanetizers['filters'][$key] as $filter)
      {
        $value = $filter($value);
      }
    }
    return $value;
  }

  /**
   * Validate a single property
   *
   * @param string $key
   * @param mixed  $value
   * @param bool   $stopFirst Stop on the first error
   * @param bool   $throw
   *
   * @return bool|\Exception[]
   *
   * @throws \Exception
   */
  public function validateDaoProperty(
    $key, $value, $stopFirst = true, $throw = false
  )
  {
    $errors = [];
    if(isset($this->_sanetizers['validators'][$key]))
    {
      foreach($this->_sanetizers['validators'][$key] as $validator)
      {
        try
        {
          $result = $validator($value);
          if($result === false)
          {
            throw new \Exception(
              "An unknown error occurred when validating $key"
            );
          }
        }
        catch(\Exception $e)
        {
          if($throw)
          {
            throw $e;
          }

          $errors[] = $e;

          if($stopFirst)
          {
            break;
          }
        }
      }

      if(!empty($errors))
      {
        return $errors;
      }
    }
    return true;
  }

  /**
   * Validate the whole DAO, or select properties
   *
   * @param array $properties
   * @param bool  $throw
   *
   * @return array|bool
   * @throws \Exception
   * @throws mixed
   */
  public function isValid(array $properties = null, $throw = false)
  {
    $isValid = true;
    if($properties === null)
    {
      $properties = $this->getDaoProperties();
    }

    $errors = [];

    foreach($properties as $property)
    {
      $exceptions = $this->validateDaoProperty(
        $property,
        $this->getDaoProperty($property)
      );

      if($exceptions !== true)
      {
        if($throw)
        {
          throw reset($exceptions);
        }
        $errors[$property] = $exceptions;
      }
    }

    if(!empty($errors))
    {
      return $errors;
    }

    return $isValid;
  }

  /**
   * Serialize a value based on the rules of a property
   *
   * @param $property
   * @param $value
   *
   * @return string
   */
  public function getPropertySerialized($property, $value)
  {
    if(isset($this->_sanetizers['serializers'][$property]))
    {
      foreach($this->_sanetizers['serializers'][$property] as $type)
      {
        switch($type)
        {
          case self::SERIALIZATION_JSON:
            $value = json_encode($value);
            break;
          case self::SERIALIZATION_PHP:
            $value = serialize($value);
            break;
          case is_array($type) && isset($type['serializer']):
            $value = $type['serializer']($value);
        }
      }
    }
    return $value;
  }

  /**
   * Unserialize a value based on the rules of a property
   *
   * @param $property
   * @param $value
   *
   * @return mixed
   */
  public function getPropertyUnserialized($property, $value)
  {
    if(isset($this->_sanetizers['serializers'][$property]))
    {
      $reversed = $this->_sanetizers['serializers'][$property];
      foreach(array_reverse($reversed) as $type)
      {
        switch($type)
        {
          case self::SERIALIZATION_JSON:
            $value = json_decode($value);
            break;
          case self::SERIALIZATION_PHP:
            $value = unserialize($value);
            break;
          case is_array($type) && isset($type['unserializer']):
            $value = $type['unserializer']($value);
        }
      }
    }
    return $value;
  }

  /**
   * Hydrate the DAO with raw data
   *
   * @param array $data
   * @param bool  $raw Is being hydrated with datastore values
   *
   * @return self
   */
  public function hydrateDao(array $data, $raw = false)
  {
    $hydratable = array_intersect_key(
      $data,
      array_flip($this->getDaoProperties())
    );
    foreach($hydratable as $key => $value)
    {
      if($raw)
      {
        $value = $this->getPropertyUnserialized($key, $value);
      }
      $this->setDaoProperty($key, $value);
    }
    return $this;
  }

  /**
   * Add a serializer to a property
   *
   * @param        $property
   * @param null   $alias
   * @param string $serializer
   *
   * @return $this
   */
  protected function _addSerializer(
    $property, $alias = null, $serializer = self::SERIALIZATION_JSON
  )
  {
    if($alias === null)
    {
      $alias = $serializer;
    }

    $this->_sanetizers['serializers'][$property][$alias] = $serializer;
    return $this;
  }

  /**
   * Add a custom serializer to a property, these are callbacks to serialize
   * and unserialize
   *
   * @param          $property
   * @param          $alias
   * @param callable $serializer
   * @param callable $unserializer
   *
   * @return $this
   */
  protected function _addCustomSerializer(
    $property, $alias, callable $serializer, callable $unserializer
  )
  {
    $this->_sanetizers['serializers'][$property][$alias] = [
      'serializer'   => $serializer,
      'unserializer' => $unserializer
    ];
    return $this;
  }

  /**
   * Remove a serializer for a property by its alias
   *
   * @param $property
   * @param $alias
   *
   * @return $this
   */
  protected function _removeSerializer($property, $alias)
  {
    unset($this->_sanetizers['serializers'][$property][$alias]);
    return $this;
  }

  /**
   * Remove all serializers on a property
   *
   * @param $property
   *
   * @return $this
   */
  protected function _clearSerializers($property)
  {
    $this->_sanetizers['serializers'][$property] = [];
    return $this;
  }

  /**
   * Add a filter callback to a property
   *
   * @param          $property
   * @param          $alias
   * @param callable $filter
   *
   * @return $this
   */
  protected function _addFilter($property, $alias, callable $filter)
  {
    $this->_sanetizers['filters'][$property][$alias] = $filter;
    return $this;
  }

  /**
   * Remove a filter by its alias from a property
   *
   * @param $property
   * @param $alias
   *
   * @return $this
   */
  protected function _removeFilter($property, $alias)
  {
    unset($this->_sanetizers['filters'][$property][$alias]);
    return $this;
  }

  /**
   * Clear all filters for a property
   *
   * @param $property
   *
   * @return $this
   */
  protected function _clearFilters($property)
  {
    $this->_sanetizers['filters'][$property] = [];
    return $this;
  }

  /**
   * Add a validator to a property, should return true, or throw Exception
   *
   * @param          $property
   * @param          $alias
   * @param callable $filter
   *
   * @return $this
   */
  protected function _addValidator($property, $alias, callable $filter)
  {
    $this->_sanetizers['validators'][$property][$alias] = $filter;
    return $this;
  }

  /**
   * Remove a validator from a property by its alias
   *
   * @param $property
   * @param $alias
   *
   * @return $this
   */
  protected function _removeValidator($property, $alias)
  {
    unset($this->_sanetizers['validators'][$property][$alias]);
    return $this;
  }

  /**
   * Clear all validators for a property
   *
   * @param $property
   *
   * @return $this
   */
  protected function _clearValidators($property)
  {
    $this->_sanetizers['validators'][$property] = [];
    return $this;
  }

  /**
   * Get the current properties on the dao
   *
   * @param bool $serialized Return the values serialized
   *
   * @return ISanitizableDao
   */
  public function getDaoPropertyData($serialized = true)
  {
    if(!$serialized)
    {
      return parent::getDaoPropertyData();
    }

    $data = [];
    foreach($this->getDaoProperties() as $property)
    {
      $data[$property] = $this->getPropertySerialized(
        $property,
        $this->getDaoProperty($property)
      );
    }
    return $data;
  }
}
