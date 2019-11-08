<?php
namespace Packaged\Dal\Tests\Ql\Mocks\Cql;

use Packaged\Dal\Ql\Cql\CqlDao;
use Packaged\Dal\Ql\Cql\CqlDataStore;

class MockCqlDao extends CqlDao
{
  public $id;
  public $id2;
  public $username;
  public $display;
  public $intVal;
  public $bigintVal;
  public $doubleVal;
  public $floatVal;
  public $negDecimalVal;
  public $decimalVal;
  public $timestampVal;
  public $boolVal;
  protected $_dataStoreName = 'mockcql';
  protected $_ttl;
  protected $_timestamp;
  protected $_dataStore;

  public function getDaoIDProperties()
  {
    return ['id', 'id2'];
  }

  public function getTableName()
  {
    return "mock_ql_daos";
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

  public function getTimestamp()
  {
    return $this->_timestamp;
  }

  public function setTimestamp($timestamp)
  {
    $this->_timestamp = $timestamp;
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
