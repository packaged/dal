<?php
namespace Packaged\Dal\Ql;

use Doctrine\Common\Inflector\Inflector;
use Packaged\Dal\Foundation\AbstractSanitizableDao;
use Packaged\Dal\Foundation\DaoCollection;
use Packaged\Dal\IDaoCollection;
use Packaged\Dal\Traits\Dao\LSDTrait;
use Packaged\Helpers\Strings;

/**
 * @method QLDataStore getDataStore
 */
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
   * @return static[]
   */
  public static function loadWhere(...$params)
  {
    return static::collection(...$params);
  }

  /**
   * @param $params
   *
   * @return QlDaoCollection
   */
  public static function collection(...$params)
  {
    $collection = QlDaoCollection::create(get_called_class());
    if(func_num_args() > 0)
    {
      $collection->loadWhere(...$params);
    }
    return $collection;
  }

  /**
   * Parts of the namespace to exclude when generating a table name
   *
   * @return array
   */
  public function getTableNameExcludeDirs()
  {
    return ['Mappers', 'Daos', 'Dal', 'Ql', 'Models', 'Database'];
  }
}
