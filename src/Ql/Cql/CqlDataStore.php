<?php
namespace Packaged\Dal\Ql\Cql;

use Packaged\Dal\DataTypes\Counter;
use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\IDao;
use Packaged\Dal\Ql\QlDao;
use Packaged\Dal\Ql\QlDataStore;
use Packaged\QueryBuilder\Assembler\CQL\CqlAssembler;
use Packaged\QueryBuilder\Builder\CQL\CqlQueryBuilder;
use Packaged\QueryBuilder\Expression\DecrementExpression;
use Packaged\QueryBuilder\Expression\IncrementExpression;
use Packaged\QueryBuilder\Expression\ValueExpression;
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
    foreach($dao->getDaoPropertyData(false) as $field => $value)
    {
      if($value instanceof Counter)
      {
        if($value->isIncrement())
        {
          $data[$field] = IncrementExpression::create($field)->setExpression(
            ValueExpression::create(
              $dao->getPropertySerialized($field, $value->getIncrement())
            )
          );
        }
        elseif($value->isDecrement())
        {
          $data[$field] = DecrementExpression::create($field)->setExpression(
            ValueExpression::create(
              $dao->getPropertySerialized($field, $value->getDecrement())
            )
          );
        }
        else
        {
          unset($data[$field]);
        }
      }
    }

    $qb = static::_getQueryBuilderClass();
    return $qb::update($dao->getTableName(), $data)
      ->where($dao->getId(true));
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
    return new CqlAssembler($statement, false);
  }
}
