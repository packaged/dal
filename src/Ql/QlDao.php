<?php
namespace Packaged\Dal\Ql;

use Doctrine\Common\Inflector\Inflector;
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

      $this->_tableName = ltrim(
        Inflector::tableize(
          implode(
            '_',
            [
              Strings::stringToUnderScore($ns),
              Inflector::pluralize(class_shortname($class))
            ]
          )
        ),
        '_'
      );
    }
    return $this->_tableName;
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
