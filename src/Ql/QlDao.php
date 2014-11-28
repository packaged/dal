<?php
namespace Packaged\Dal\Ql;

use Doctrine\Common\Inflector\Inflector;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Exceptions\DalResolver\DataStoreNotFoundException;
use Packaged\Dal\Exceptions\Dao\MultipleDaoException;
use Packaged\Dal\Foundation\AbstractSanitizableDao;
use Packaged\Dal\Traits\Dao\LSDTrait;
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
      $ns    = get_namespace($class);
      $dirs  = $this->getTableNameExcludeDirs();
      foreach($dirs as $dir)
      {
        $ns = ltrim(string_from($ns, $dir), '\\');
      }
      $this->_tableName = trim(
        Inflector::tableize(
          implode(
            '_',
            [
              Strings::stringToUnderScore($ns),
              Inflector::pluralize(class_shortname($class))
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
   * @param $params
   *
   * @return QlDaoCollection
   */
  public static function loadWhere(...$params)
  {
    $collection = QlDaoCollection::create(get_called_class());
    if(func_num_args() > 0)
    {
      $collection->loadWhere(...$params);
    }
    return $collection;
  }

  /**
   * @return QlDaoCollection
   */
  protected static function _createCollection()
  {
    return QlDaoCollection::create(get_called_class());
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
    return static::loadWhere(...$params)->getRawArray();
  }

  /**
   * Parts of the namespace to exclude when generating a table name
   *
   * @return array
   */
  public function getTableNameExcludeDirs()
  {
    return ['Mappers', 'Daos', 'Dal', 'Ql', 'Models', 'Database', 'Dao'];
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
    try
    {
      return static::$_resolver->getDataStore($this->_dataStoreName);
    }
    catch(DataStoreNotFoundException $e)
    {
      if(static::getDalResolver()->hasConnection($this->_dataStoreName))
      {
        $dataStore = new QlDataStore();
        $dataStore->configure(
          new ConfigSection('', ['connection' => $this->_dataStoreName])
        );
        static::getDalResolver()->addDataStore(
          $this->_dataStoreName,
          $dataStore
        );
        return $dataStore;
      }
      throw $e;
    }
  }
}
