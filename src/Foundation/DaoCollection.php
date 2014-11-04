<?php
namespace Packaged\Dal\Foundation;

use Packaged\Dal\IDao;
use Packaged\Dal\IDaoCollection;
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

  /**
   * @param $fresh bool Create a new instance of the DAO Class
   *
   * @return IDao
   */
  public function createNewDao($fresh = true)
  {
    if($fresh || $this->_dao === null)
    {
      $class = newv($this->_daoClass, []);
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
   * @param string $daoClass
   *
   * @return static
   */
  public static function create($daoClass)
  {
    $collection            = new static;
    $collection->_daoClass = $daoClass;
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
    return (array)$this->_daos;
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
    return array_unique(ppull((array)$this->_daos, $property));
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
    return ppull((array)$this->_daos, $property, $keyProperty);
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
    $result = [];
    foreach((array)$this->_daos as $i => $dao)
    {
      $key          = idp($dao, $keyProperty, $i);
      $result[$key] = [];
      foreach($properties as $property)
      {
        $result[$key][$property] = idp($dao, $property, null);
      }
    }
    return $result;
  }

  /**
   * (PHP 5 &gt;= 5.0.0)<br/>
   * Retrieve an external iterator
   * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
   * @return Traversable An instance of an object implementing <b>Iterator</b> or
   * <b>Traversable</b>
   */
  public function getIterator()
  {
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
        $response[] = get_public_properties($dao);
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
    return count($this->_daos);
  }
}
