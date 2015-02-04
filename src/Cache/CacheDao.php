<?php
namespace Packaged\Dal\Cache;

use Packaged\Dal\Foundation\AbstractSanitizableDao;
use Packaged\Dal\Traits\Dao\LSDTrait;

/**
 * @method CacheDataStore getDataStore
 */
class CacheDao extends AbstractSanitizableDao
{
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

  public $data;
}
