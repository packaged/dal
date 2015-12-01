<?php
namespace Packaged\Dal\Ql\Cql;

use Packaged\Dal\DataTypes\Counter;
use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\IDao;
use Packaged\Dal\Ql\QlDao;
use Packaged\Dal\Ql\QlDataStore;
use Packaged\QueryBuilder\Assembler\CQL\CqlAssembler;
use Packaged\QueryBuilder\Builder\CQL\CqlQueryBuilder;
use Packaged\QueryBuilder\Statement\CQL\CqlInsertStatement;
use Packaged\QueryBuilder\Statement\CQL\CqlUpdateStatement;
use Packaged\QueryBuilder\Statement\IStatement;

class CqlDataStore extends QlDataStore
{
  /**
   * @return CqlQueryBuilder
   */
  protected function _getQueryBuilderClass()
  {
    return CqlQueryBuilder::class;
  }

  protected function _getStatement(QlDao $dao)
  {
    if(!$dao->hasCounter())
    {
      return parent::_getStatement($dao);
    }

    $data = $this->_getDaoChanges($dao, false);
    foreach($data as $field => $value)
    {
      if($dao->{$field} instanceof Counter)
      {
        $data[$field] = $this->_getCounterValue($dao, $field);
      }
    }

    if($data)
    {
      $qb = static::_getQueryBuilderClass();
      return $qb::update($dao->getTableName(), $data)->where($dao->getId(true));
    }
    return null;
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
    parent::save($dao);
  }

  protected function _prepareQuery(IStatement $stmt, QlDao $dao)
  {
    if(($stmt instanceof CqlInsertStatement || $stmt instanceof CqlUpdateStatement)
      && ($dao instanceof CqlDao)
    )
    {
      if(($dao->getTtl() !== null && $dao->getTtl() > 0))
      {
        $stmt->usingTtl($dao->getTtl());
      }
      if(($dao->getTimestamp() !== null && $dao->getTimestamp() > 0))
      {
        $stmt->usingTimestamp($dao->getTimestamp());
      }
    }
  }

  protected function _assemble(IStatement $statement, $forPrepare = true)
  {
    return new CqlAssembler($statement);
  }
}
