<?php
namespace Packaged\Dal\Ql;

use Doctrine\Common\Inflector\Inflector;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Exceptions\DalResolver\DataStoreNotFoundException;
use Packaged\Dal\Exceptions\Dao\MultipleDaoException;
use Packaged\Dal\Foundation\AbstractSanitizableDao;
use Packaged\Dal\Traits\Dao\LSDTrait;
use Packaged\Helpers\Objects;
use Packaged\Helpers\Strings;

abstract class QlDao extends AbstractSanitizableDao
{
  use LSDTrait;

  protected $_tableName;

  /**
   * Retrieve the table name for this DAO
   *
   * @return string
   */
  public function getTableName()
  {
    if($this->_tableName === null)
    {
      $class = get_called_class();
      $ns = Objects::getNamespace($class);
      $dirs = $this->getTableNameExcludeDirs();
      foreach($dirs as $dir)
      {
        $ns = ltrim(Strings::offset($ns, $dir), '\\');
      }
      $this->_tableName = trim(
        Inflector::tableize(
          implode(
            '_',
            [
              Strings::stringToUnderScore($ns),
              Inflector::pluralize(Objects::classShortname($class)),
            ]
          )
        ),
        '_ '
      );
      $this->_tableName = str_replace('__', '_', $this->_tableName);
    }
    return $this->_tableName;
  }

  /**
   * @deprecated
   *
   * @param $params
   *
   * @return QlDaoCollection
   */
  public static function loadWhere(...$params)
  {
    $collection = static::_createCollection();
    if(func_num_args() > 0)
    {
      $collection->loadWhere(...$params);
    }
    return $collection;
  }

  /**
   * @param string|object|null $class
   *
   * @return QlDaoCollection
   */
  protected static function _createCollection($class = null)
  {
    if($class === null)
    {
      $class = get_called_class();
    }

    return QlDaoCollection::create($class);
  }

  /**
   * @param $params
   *
   * @return QlDaoCollection
   */
  public static function collection(...$params)
  {
    $collection = static::_createCollection();
    if(func_num_args() > 0)
    {
      $collection->where(...$params);
    }
    return $collection;
  }

  /**
   * @param $params
   *
   * @return QlDaoCollection
   */
  public function getCollection(...$params)
  {
    $collection = static::_createCollection($this);
    if(func_num_args() > 0)
    {
      $collection->where(...$params);
    }
    return $collection;
  }

  /**
   * @param $params
   *
   * @return static
   *
   * @throws MultipleDaoException
   */
  public static function loadOneWhere(...$params)
  {
    $collection = static::_createCollection();
    if(func_num_args() > 0)
    {
      $collection->where(...$params);
    }
    $collection->limit(2);
    $collection->load();
    if($collection->count() === 2)
    {
      throw new MultipleDaoException(
        "Multiple Objects were located when trying to load one"
      );
    }

    return $collection->first();
  }

  /**
   * @param $params
   *
   * @return static[]
   */
  public static function each(...$params)
  {
    return static::collection(...$params)->getRawArray();
  }

  /**
   * Parts of the namespace to exclude when generating a table name
   *
   * @return array
   */
  public function getTableNameExcludeDirs()
  {
    return [
      'Database',
      'Mappers',
      'Storage',
      'Models',
      'Mocks',
      'Daos',
      'Dao',
      'Dal',
      'Ql',
    ];
  }

  /**
   * Get the data store for this dao
   *
   * @return QlDataStore
   *
   * @throws DataStoreNotFoundException
   */
  public function getDataStore()
  {
    $resolver = static::getDalResolver();
    try
    {
      return $resolver->getDataStore($this->_dataStoreName);
    }
    catch(DataStoreNotFoundException $e)
    {
      if($resolver->hasConnection($this->_dataStoreName))
      {
        $config = new ConfigSection(
          $this->_dataStoreName,
          ['connection' => $this->_dataStoreName]
        );
        $dataStore = new QlDataStore();
        $dataStore->configure($config);
        $resolver->addDataStoreConfig($config);
        $resolver->addDataStore($this->_dataStoreName, $dataStore);
        return $dataStore;
      }
      throw $e;
    }
  }
}
