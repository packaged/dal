<?php
namespace Packaged\Dal\Foundation;

use Packaged\Dal\IDao;

/**
 * Foundation for all DAOs
 */
abstract class AbstractDao implements IDao
{
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
   * Create a new instance of your DAO
   */
  public function __construct()
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
   * @return static
   */
  public function hydrateDao(array $data)
  {
    $hydratable = array_intersect_key($data, $this->getDaoProperties());
    return $hydratable;
  }

  /**
   * Set the current dataset to be that of the data store values
   *
   * @return static
   */
  public function markDaoDatasetAsSaved()
  {
    $this->_savedData = $this->getDaoPropertyData();
    return $this;
  }

  /**
   * Setup the mapper
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
}
