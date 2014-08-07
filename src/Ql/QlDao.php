<?php
namespace Packaged\Dal\Ql;

use Packaged\Dal\Foundation\AbstractSanitizableDao;
use Packaged\Dal\Traits\Dao\LSDTrait;

abstract class QlDao extends AbstractSanitizableDao
{
  use LSDTrait;

  public function getTableName()
  {
    return 'mock';
  }
}
