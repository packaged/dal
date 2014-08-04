<?php
namespace Packaged\Dal\Foundation;

use Packaged\Dal\DalResolver;
use Packaged\Dal\IDao;
use Packaged\Dal\IDataStore;

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
   */
  public function __construct()
  {
    $this->daoConstruct();
  }

  /**
   * Construct the class with daoConstruct
   *
   * This should always be called at the first line of your __construct
   */
  final public function daoConstruct()
  {
    //Calculate public properties
    $this->_startup();

    //Configure the DAO
    $this->_configure();

    //Set the current dataset with the defaults from public properties
    $this->hydrateDao(static::$_properties[$this->_calledClass]);
  }

  /**
   * Get all changed properties since load
   *
   * @return array[property] => ['from' => '','to' => '']
   */
  public function getDaoChanges()
  {
    $current    = (array)$this->getDaoPropertyData();
    $changeKeys = array_keys(array_diff($this->_savedData, $current));

    $changes = [];

    if($changeKeys)
    {
      foreach($changeKeys as $key)
      {
        $changes[$key] = [
          'from' => idx($this->_savedData, $key),
          'to'   => idx($current, $key)
        ];
      }
    }
    return $changes;
  }

  /**
   * Get the current properties on the dao
   *
   * @return array[property] = value
   */
  public function getDaoPropertyData()
  {
    return array_intersect_key(
      get_public_properties($this),
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
    return $this;
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
    return $this;
  }

  /**
   * Set the DAL resolver
   *
   * @param DalResolver $resolver
   */
  public static function setDalResolver(DalResolver $resolver)
  {
    static::$_resolver = $resolver;
  }

  /**
   * unset the DAL resolver
   */
  public static function unsetDalResolver()
  {
    static::$_resolver = null;
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
}
