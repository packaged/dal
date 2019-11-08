<?php
namespace Packaged\Dal\Tests\Ql\Mocks;

use Packaged\Dal\DataTypes\Counter;
use Packaged\Dal\Ql\QlDao;
use Packaged\Dal\Ql\QlDataStore;

class MockCounterDao extends QlDao
{
  public $id;
  /**
   * @counter
   * @var Counter
   */
  public $c1;
  /**
   * @counter
   * @var Counter
   */
  public $c2;
  /**
   * @counter
   * @var Counter
   */
  public $c3;
  protected $_dataStoreName = 'mockql';
  protected $_ttl;
  protected $_dataStore;

  protected $_tableName = 'mock_counter_daos';

  public function getTableName()
  {
    return $this->_tableName;
  }

  public function setTableName($table)
  {
    $this->_tableName = $table;
    return $this;
  }

  public function getTtl()
  {
    return $this->_ttl;
  }

  public function setTtl($ttl)
  {
    $this->_ttl = $ttl;
    return $this;
  }

  public function getDataStore()
  {
    if($this->_dataStore === null)
    {
      return parent::getDataStore();
    }
    return $this->_dataStore;
  }

  public function setDataStore(QlDataStore $store)
  {
    $this->_dataStore = $store;
    return $this;
  }
}
