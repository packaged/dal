<?php
namespace Packaged\Dal\Cache;

use Packaged\Dal\Exceptions\DataStore\DaoNotFoundException;

abstract class CacheObject extends CacheDao
{
  public function getTtl()
  {
    //Default cache for 24 hours
    return 86400;
  }

  final public function __construct($cacheKey)
  {
    $this->key = $cacheKey;
    $this->_addSerializer("data", self::SERIALIZATION_PHP);
    parent::__construct();
  }

  protected function _construct()
  {
  }

  public static function i()
  {
    return new static(static::class);
  }

  abstract protected function _retrieve();

  public function retrieve()
  {
    try
    {
      $this->load();
      if($this->data === false || $this->data === null)
      {
        throw new DaoNotFoundException("Invalid Data");
      }
    }
    catch(DaoNotFoundException $e)
    {
      $this->data = $this->getPropertyUnserialized("data", $this->_retrieve());
      if($this->data !== null)
      {
        $this->save();
      }
    }

    return $this->data;
  }
}
