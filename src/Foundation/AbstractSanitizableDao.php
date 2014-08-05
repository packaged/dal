<?php
namespace Packaged\Dal\Foundation;

abstract class AbstractSanitizableDao extends AbstractDao
{
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
          $validator($value);
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

  public function getPropertySerialized($property)
  {
    return json_encode($this->getDaoProperty($property));
  }

  public function getPropertyUnserialized($property)
  {
    return json_decode($this->getDaoProperty($property));
  }
}
