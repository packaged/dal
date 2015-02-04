<?php
namespace Packaged\Dal\Cache;

use Packaged\Dal\Foundation\AbstractSanitizableDao;
use Packaged\Dal\Traits\Dao\LSDTrait;

/**
 * @method CacheDataStore getDataStore
 */
class CacheDao extends AbstractSanitizableDao
{
  protected $_dataStoreName = 'cache';

  use LSDTrait;

  protected $_ttl;

  public function getTtl()
  {
    return $this->_ttl;
  }

  public function setTtl($seconds)
  {
    $this->_ttl = $seconds;
    return $this;
  }

  public function getDaoIDProperties()
  {
    return ['key'];
  }

  public $key;
  public $data;
}
