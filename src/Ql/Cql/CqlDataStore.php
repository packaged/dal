<?php
namespace Packaged\Dal\Ql\Cql;

use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\Helpers\Phid;
use Packaged\Dal\IDao;
use Packaged\Dal\Ql\QlDao;
use Packaged\Dal\Ql\QlDataStore;
use Packaged\QueryBuilder\Assembler\CQL\CqlAssembler;
use Packaged\QueryBuilder\Statement\IStatement;

class CqlDataStore extends QlDataStore
{
  /**
   * CQL does not require any on duplicate key, as an insert will overwrite
   *
   * @param QlDao $dao
   */
  protected function _saveInsertDuplicate(QlDao $dao)
  {
    $this->_saveInsert($dao);
  }

  public function escapeTableName($table)
  {
    return "\"$table\"";
  }

  public function escapeColumn($column)
  {
    return "\"$column\"";
  }

  /**
   * Save a DAO to the data store
   *
   * @param IDao $dao
   *
   * @return array of changed properties
   *
   * @throws DataStoreException
   */
  public function save(IDao $dao)
  {
    $dao = $this->_verifyDao($dao);
    if($dao->getId(false, false) === null) //TODO: Check for ID Type
    {
      foreach($dao->getDaoIDProperties() as $key)
      {
        $dao->setDaoProperty($key, Phid::generate($dao));
      }
    }
    return parent::save($dao);
  }

  protected function _prepareQuery(QlDao $dao)
  {
    if($dao instanceof CqlDao)
    {
      if($dao->getTtl() !== null && $dao->getTtl() > 0)
      {
        $this->_query .= " USING TTL " . (int)$dao->getTtl();
      }
    }
  }

  protected function _assemble(IStatement $statement)
  {
    return CqlAssembler::stringify($statement);
  }
}
