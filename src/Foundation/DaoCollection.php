<?php
namespace Packaged\Dal\Foundation;

use Packaged\Dal\IDao;
use Packaged\Dal\IDaoCollection;
use Packaged\Helpers\Arrays;
use Packaged\Helpers\Objects;
use Traversable;

class DaoCollection implements IDaoCollection
{
  /**
   * @var IDao[]
   */
  protected $_daos;

  /**
   * @var IDao
   */
  protected $_dao;
  /**
   * Class for the DAOs contained
   *
   * @var string
   */
  protected $_daoClass;

  final private function __construct()
  {
  }

  protected function _prepareDaos()
  {
  }

  /**
   * @param $fresh bool Create a new instance of the DAO Class
   *
   * @return IDao
   */
  public function createNewDao($fresh = true)
  {
    if($fresh || $this->_dao === null)
    {
      $class = Objects::create($this->_daoClass, []);
      if($class instanceof IDao)
      {
        if(!$fresh)
        {
          $this->_dao = $class;
        }
        return $class;
      }
      else
      {
        throw new \RuntimeException(
          "'$this->_daoClass' is not a valid DAO Class"
        );
      }
    }
    return $this->_dao;
  }

  /**
   * Create a new collection based on a DAO class
   *
   * @param string|object $daoClass
   *
   * @return static
   */
  public static function create($daoClass)
  {
    $collection = new static;
    if(is_object($daoClass))
    {
      $collection->_dao = $daoClass;
      $collection->_daoClass = get_class($collection->_dao);
    }
    else
    {
      $collection->_daoClass = $daoClass;
    }
    $collection->_init();
    return $collection;
  }

  protected function _init()
  {
  }

  public function clear()
  {
    $this->_daos = [];
    return $this;
  }

  public function getRawArray()
  {
    $this->_prepareDaos();
    return (array)$this->_daos;
  }

  /**
   * Retrieve the first available dao
   *
   * @param mixed $default
   *
   * @return IDao
   */
  public function first($default = null)
  {
    $this->_prepareDaos();
    if(empty($this->_daos))
    {
      return $default;
    }
    return Arrays::first((array)$this->_daos);
  }

  /**
   * Execute a callback over each dao in the collection
   *
   * @param \Closure $callback
   *
   * @return $this
   */
  public function each(\Closure $callback)
  {
    $this->_prepareDaos();
    array_map($callback, $this->_daos);
  }

  /**
   * True if not DAOs exist within the collection
   *
   * @return bool
   */
  public function isEmpty()
  {
    return empty($this->_daos);
  }

  /**
   * Find all distinct values of a property in the collection
   *
   * @param $property
   *
   * @return array
   */
  public function distinct($property)
  {
    $this->_prepareDaos();
    return array_unique(Objects::ppull((array)$this->_daos, $property));
  }

  /**
   * Pull all properties from the collection
   * optionally keyed by another property
   *
   * @param string      $property
   * @param null|string $keyProperty
   *
   * @return mixed
   */
  public function ppull($property, $keyProperty = null)
  {
    $this->_prepareDaos();
    return Objects::ppull((array)$this->_daos, $property, $keyProperty);
  }

  /**
   * Pull an array of properties from the collection
   * optionally keyed by another property
   *
   * @param string[]    $properties
   * @param null|string $keyProperty
   *
   * @return mixed
   */
  public function apull(array $properties, $keyProperty = null)
  {
    $this->_prepareDaos();
    $result = [];
    foreach((array)$this->_daos as $i => $dao)
    {
      $key = Objects::property($dao, $keyProperty, $i);
      $result[$key] = [];
      foreach($properties as $property)
      {
        $result[$key][$property] = Objects::property($dao, $property, null);
      }
    }
    return $result;
  }

  /**
   * (PHP 5 &gt;= 5.0.0)<br/>
   * Retrieve an external iterator
   * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
   * @return Traversable An instance of an object implementing <b>Iterator</b>
   *                     or
   * <b>Traversable</b>
   */
  public function getIterator()
  {
    $this->_prepareDaos();
    return new \ArrayIterator((array)$this->_daos);
  }

  /**
   * (PHP 5 &gt;= 5.0.0)<br/>
   * Whether a offset exists
   * @link http://php.net/manual/en/arrayaccess.offsetexists.php
   *
   * @param mixed $offset <p>
   *                      An offset to check for.
   *                      </p>
   *
   * @return boolean true on success or false on failure.
   * </p>
   * <p>
   * The return value will be casted to boolean if non-boolean was returned.
   */
  public function offsetExists($offset)
  {
    $this->_prepareDaos();
    return isset($this->_daos[$offset]);
  }

  /**
   * (PHP 5 &gt;= 5.0.0)<br/>
   * Offset to retrieve
   * @link http://php.net/manual/en/arrayaccess.offsetget.php
   *
   * @param mixed $offset <p>
   *                      The offset to retrieve.
   *                      </p>
   *
   * @return mixed Can return all value types.
   */
  public function offsetGet($offset)
  {
    $this->_prepareDaos();
    return isset($this->_daos[$offset]) ? $this->_daos[$offset] : null;
  }

  /**
   * (PHP 5 &gt;= 5.0.0)<br/>
   * Offset to set
   * @link http://php.net/manual/en/arrayaccess.offsetset.php
   *
   * @param mixed $offset <p>
   *                      The offset to assign the value to.
   *                      </p>
   * @param mixed $value  <p>
   *                      The value to set.
   *                      </p>
   *
   * @return void
   */
  public function offsetSet($offset, $value)
  {
    $this->_prepareDaos();
    if($offset === null)
    {
      $this->_daos[] = $value;
    }
    else
    {
      $this->_daos[$offset] = $value;
    }
  }

  /**
   * (PHP 5 &gt;= 5.0.0)<br/>
   * Offset to unset
   * @link http://php.net/manual/en/arrayaccess.offsetunset.php
   *
   * @param mixed $offset <p>
   *                      The offset to unset.
   *                      </p>
   *
   * @return void
   */
  public function offsetUnset($offset)
  {
    $this->_prepareDaos();
    unset($this->_daos[$offset]);
  }

  /**
   * (PHP 5 &gt;= 5.4.0)<br/>
   * Specify data which should be serialized to JSON
   * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
   * @return mixed data which can be serialized by <b>json_encode</b>,
   * which is a value of any type other than a resource.
   */
  public function jsonSerialize()
  {
    $this->_prepareDaos();
    if($this->isEmpty())
    {
      return [];
    }
    $response = [];
    foreach($this->_daos as $dao)
    {
      if($dao instanceof \JsonSerializable)
      {
        $response[] = $dao->jsonSerialize();
      }
      else
      {
        $response[] = Objects::propertyValues($dao);
      }
    }
    return $response;
  }

  /**
   * Convert this collection to a string
   *
   * @return string
   */
  public function __toString()
  {
    $this->_prepareDaos();
    return json_encode($this);
  }

  /**
   * (PHP 5 &gt;= 5.1.0)<br/>
   * Count elements of an object
   * @link http://php.net/manual/en/countable.count.php
   * @return int The custom count as an integer.
   * </p>
   * <p>
   * The return value is cast to an integer.
   */
  public function count()
  {
    $this->_prepareDaos();
    return count($this->_daos);
  }

  public function min($property = 'id')
  {
    $this->_prepareDaos();
    if(empty($this->_daos))
    {
      return null;
    }
    return min(Objects::ppull($this->_daos, $property));
  }

  public function max($property = 'id')
  {
    $this->_prepareDaos();
    if(empty($this->_daos))
    {
      return null;
    }
    return max(Objects::ppull($this->_daos, $property));
  }

  public function avg($property = 'id')
  {
    $this->_prepareDaos();
    if(empty($this->_daos))
    {
      return null;
    }
    $values = Objects::ppull($this->_daos, $property);
    return array_sum($values) / count($values);
  }

  public function sum($property = 'id')
  {
    $this->_prepareDaos();
    if(empty($this->_daos))
    {
      return null;
    }
    return array_sum(Objects::ppull($this->_daos, $property));
  }
}
