<?php
namespace Tests\Ql\Mocks;

use Packaged\Dal\Ql\QlDaoCollection;
use Packaged\Helpers\ValueAs;
use stdClass;
use Tests\Foundation\Mocks\MockAbstractDao;

class MockQlDaoCollection extends QlDaoCollection
{
  /**
   * @param null $daoClass
   *
   * @return static
   */
  public static function create($daoClass = null)
  {
    if($daoClass === null)
    {
      $daoClass = MockQlDao::class;
    }
    return parent::create($daoClass);
  }

  public function setDaos(array $daos)
  {
    $this->_daos = $daos;
    return $this;
  }

  public function getDaos()
  {
    return $this->_daos;
  }

  public function setDummyData()
  {
    $this->_daos = [];
    $this->_daos[1] = ValueAs::obj(['name' => 'Test', 'id' => 1]);
    $this->_daos[2] = ValueAs::obj(['name' => 'Test', 'id' => 2]);
    $user = new stdClass();
    $user->name = 'User';
    $user->id = 5;
    $this->_daos[5] = $user;
    $mock = new MockAbstractDao();
    $mock->name = 'Testing';
    $mock->id = 8;
    $this->_daos[8] = $mock;
    $this->_isLoaded = true;
  }
}
