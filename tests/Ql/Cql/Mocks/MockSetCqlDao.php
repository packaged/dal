<?php
namespace Packaged\Dal\Tests\Ql\Cql\Mocks;

use Packaged\Dal\DataTypes\UniqueList;
use Packaged\Dal\Ql\Cql\CqlDao;
use Packaged\Dal\Ql\QlDataStore;

class MockSetCqlDao extends CqlDao
{
  public $id;
  /**
   * @uniqueList
   * @var UniqueList
   */
  public $s;

  protected $_dataStoreName = 'mockcql';
  protected $_ttl;
  protected $_dataStore;

  protected $_tableName = 'mock_set_daos';

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
