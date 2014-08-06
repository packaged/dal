<?php
namespace Packaged\Dal\Foundation;

use Packaged\Dal\DalResolver;

final class Dao extends AbstractDao
{
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
}
