<?php
namespace Packaged\Dal\Tests\Ql\Cql\Mocks;

use Packaged\Dal\DataTypes\Counter;
use Packaged\Dal\Ql\Cql\CqlDao;
use Packaged\Dal\Ql\Cql\CqlDataStore;

class MockCounterCqlDao extends CqlDao
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
  protected $_dataStoreName = 'mockcql';
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

  public function setDataStore(CqlDataStore $store)
  {
    $this->_dataStore = $store;
    return $this;
  }
}
