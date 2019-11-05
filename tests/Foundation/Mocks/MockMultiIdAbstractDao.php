<?php
namespace Tests\Foundation\Mocks;

use Packaged\Dal\Foundation\AbstractDao;

class MockMultiIdAbstractDao extends AbstractDao
{
  public $name;
  public $status = 'active';
  public $email = 'nobody@example.com';

  public function init()
  {
    $this->_setDataStoreName('test');
  }

  public function getDaoIDProperties()
  {
    return ['status', 'email'];
  }
}
