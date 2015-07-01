<?php
namespace Packaged\Dal\Foundation;

use Packaged\Dal\DalResolver;
use Packaged\Dal\IDao;
use Packaged\Dal\IDataStore;
use Packaged\Helpers\Arrays;
use Packaged\Helpers\Objects;

/**
 * Foundation for all DAOs
 */
abstract class AbstractDao implements IDao
{
  /**
   * @var DalResolver
   */
  protected static $_resolver;
  /**
   * @var string name/alias for the datastore
   */
  protected $_dataStoreName;
  /**
   * A copy of the data that is known to be held in the datastore
   *
   * @var array[property] = value
   */
  protected $_savedData = [];

  /**
   * Cache of public properties and their default values
   *
   * @var array[class][property] = default
   */
  protected static $_properties;

  /**
   * A cache of the called class
   *
   * @var string
   */
  protected $_calledClass;

  /**
   * Check to see if the DAO has been loaded
   *
   * @var bool
   */
  protected $_isLoaded;

  /**
   * Create a new instance of your DAO
   *
   * @param mixed $constructArgs Arguments to construct class with
   */
  public function __construct(...$constructArgs)
  {
    $this->daoConstruct(...$constructArgs);
  }

  /**
   * Hook into the construct event
   */
  protected function _construct()
  {
  }

  /**
   * Construct the class with daoConstruct
   * This should always be called at the first line of your __construct
   *
   * @param mixed $constructArgs Arguments to construct class with
   */
  final public function daoConstruct(...$constructArgs)
  {
    //Calculate public properties
    $this->_startup();

    //Configure the DAO
    $this->_configure();

    //Set the current dataset with the defaults from public properties
    $this->hydrateDao(static::$_properties[$this->_calledClass]);

    $this->markDaoDatasetAsSaved();

    //Run any specific constructor
    $this->_construct(...$constructArgs);
  }

  /**
   * Get all changed properties since load
   *
   * @return array[property] => ['from' => '','to' => '']
   */
  public function getDaoChanges()
  {
    $current = (array)$this->getDaoPropertyData();
    $changes = [];
    foreach($current as $key => $val)
    {
      if($val !== $this->_savedData[$key])
      {
        $changes[$key] = [
          'from' => Arrays::value($this->_savedData, $key),
          'to'   => Arrays::value($current, $key)
        ];
      }
    }
    return $changes;
  }

  /**
   * Check to see if a property data has changed
   *
   * @param $property
   *
   * @return bool
   */
  public function hasChanged($property)
  {
    if(!isset($this->_savedData[$property])
      || $this->_savedData[$property] !== $this->getDaoProperty($property)
    )
    {
      return true;
    }
    return false;
  }

  /**
   * Check for if any properties have changed
   *
   * @return bool
   */
  public function hasChanges()
  {
    return !empty($this->getDaoChanges());
  }

  /**
   * Get the current properties on the dao
   *
   * @return array[property] = value
   */
  public function getDaoPropertyData()
  {
    return array_intersect_key(
      Objects::propertyValues($this),
      array_flip($this->getDaoProperties())
    );
  }

  /**
   * Get an array of the properties maintained within this DAO
   *
   * @return array properties
   */
  public function getDaoProperties()
  {
    return array_keys(static::$_properties[$this->_calledClass]);
  }

  /**
   * Hydrate the DAO with raw data
   *
   * @param array $data
   *
   * @return self
   */
  public function hydrateDao(array $data)
  {
    $hydratable = array_intersect_key(
      $data,
      array_flip($this->getDaoProperties())
    );
    foreach($hydratable as $key => $value)
    {
      $this->setDaoProperty($key, $value);
    }
    $this->postHydrateDao();
    return $this;
  }

  /**
   * Method called after dao is hydrated
   */
  public function postHydrateDao()
  {
  }

  /**
   * Set the value of a property
   *
   * @param $key
   * @param $value
   *
   * @return self
   */
  public function setDaoProperty($key, $value)
  {
    $this->$key = $value;
    return $this;
  }

  /**
   * Retrieve the value of a property
   *
   * @param $key
   *
   * @return mixed
   */
  public function getDaoProperty($key)
  {
    return $this->$key;
  }

  /**
   * Set the current dataset to be that of the data store values
   *
   * @return self
   */
  public function markDaoDatasetAsSaved()
  {
    $this->_savedData = $this->getDaoPropertyData();
    return $this;
  }

  /**
   * Setup the dao on unserialize
   */
  public function __wakeup()
  {
    //Calculate public properties
    $this->_startup();

    //Configure the DAO
    $this->_configure();
  }

  public function __sleep()
  {
    return array_keys(get_object_vars($this));
  }

  /**
   * Setup the dao
   */
  final protected function _startup()
  {
    //Cache the called class
    if($this->_calledClass === null)
    {
      $this->_calledClass = get_called_class();
    }

    //If the properties have already been configured, do not re-execute startup
    if(isset(static::$_properties[$this->_calledClass]))
    {
      return;
    }

    //Initialise the properties array to disable starting from running even if
    //no properties are located
    static::$_properties[$this->_calledClass] = [];

    $reflect = new \ReflectionClass($this->_calledClass);
    foreach($reflect->getProperties(\ReflectionProperty::IS_PUBLIC) as $pub)
    {
      if(!$pub->isStatic())
      {
        //Add all non static properties to the cache with their default values
        static::$_properties[$this->_calledClass][$pub->getName()]
          = $pub->getValue($this);
      }
    }

    //Run dao type specific startup methods
    $this->_postStartup();
  }

  /**
   * This method is executed when your DAO is constructed
   */
  protected function _postStartup()
  {
  }

  /**
   * Configure your DAO on every construct
   */
  protected function _configure()
  {
  }

  /**
   * Retrieve an array of ID fields used for the primary key
   *
   * @return array
   */
  public function getDaoIDProperties()
  {
    return ['id'];
  }

  /**
   * Check to see if the DAO has been loaded
   *
   * @return bool
   */
  public function isDaoLoaded()
  {
    return (bool)$this->_isLoaded;
  }

  /**
   * Set the DAO as loaded
   *
   * @param bool $isLoaded
   *
   * @returns self
   */
  public function markDaoAsLoaded($isLoaded = true)
  {
    $this->_isLoaded = $isLoaded;
    if(!$isLoaded)
    {
      $this->_savedData = static::$_properties[$this->_calledClass];
    }
    return $this;
  }

  /**
   * Get the current DAL resolver
   *
   * @return DalResolver
   */
  public static function getDalResolver()
  {
    return static::$_resolver;
  }

  /**
   * Set the data store name for this dao to use
   *
   * @param $name
   *
   * @return $this
   */
  protected function _setDataStoreName($name)
  {
    $this->_dataStoreName = $name;
    return $this;
  }

  /**
   * Get the data store for this dao
   *
   * @return IDataStore
   */
  public function getDataStore()
  {
    return static::$_resolver->getDataStore($this->_dataStoreName);
  }

  /**
   * Retrieve the ID for this DAO, if multiple properties make up the ID,
   * they will be returned in an array
   *
   * @param bool $forceArray Force an array return, even with single property
   *
   * @return array|mixed
   */
  public function getId($forceArray = false)
  {
    $id = [];
    foreach($this->getDaoIDProperties() as $property)
    {
      $id[$property] = $this->getDaoProperty($property);
    }
    if(!$forceArray && count($id) === 1)
    {
      return reset($id);
    }
    return $id;
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
    return Objects::propertyValues($this);
  }
}
