<?php
namespace Packaged\Dal\Tests\Foundation\Mocks;

use Packaged\Dal\Foundation\AbstractDao;

class MockAbstractDao extends AbstractDao
{
  public $name;
  public $email = 'nobody@example.com';

  public function init()
  {
    $this->_setDataStoreName('test');
  }

  public function getDaoIDProperties()
  {
    return ['email'];
  }
}
