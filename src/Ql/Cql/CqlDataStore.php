<?php
namespace Packaged\Dal\Ql\Cql;

use Exception;
use Packaged\Dal\DataTypes\Counter;
use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\IDao;
use Packaged\Dal\Ql\QlDao;
use Packaged\Dal\Ql\QlDataStore;
use Packaged\QueryBuilder\Assembler\CQL\CqlAssembler;
use Packaged\QueryBuilder\Builder\CQL\CqlQueryBuilder;
use Packaged\QueryBuilder\Expression\DecrementExpression;
use Packaged\QueryBuilder\Expression\IncrementExpression;
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
      $data[$field] = $this->_getCounterValue($dao, $field, $value);
    }

    if($data)
    {
      $qb = static::_getQueryBuilderClass();
      return $qb::update($dao->getTableName(), $data)->where($dao->getId(true));
    }
    return null;
  }

  protected function _getCounterValue(QlDao $dao, $field, $value)
  {
    $newValue = $dao->{$field};
    if($newValue instanceof Counter)
    {
      if($newValue->isIncrement())
      {
        $value = IncrementExpression::create($field, $newValue->getIncrement());
      }
      else if($newValue->isDecrement())
      {
        $value = DecrementExpression::create($field, $newValue->getDecrement());
      }
      else if($newValue->isFixedValue())
      {
        if($newValue->current() === null && $newValue->calculated() == 0)
        {
          //Allow counter row initialisation
          $value = [
            IncrementExpression::create($field, 1),
            DecrementExpression::create($field, 1),
          ];
        }
        else
        {
          throw new Exception('Setting counters to specific values in CQL is not supported');
        }
      }
    }
    return $value;
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
    return parent::save($dao);
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

  protected function _getInsertData(QlDao $dao)
  {
    $changes = $this->_getDaoChanges($dao);
    if(!$dao->isDaoLoaded())
    {
      foreach($dao->getDefaultDaoValues() as $k => $v)
      {
        if($v !== null && !isset($changes[$k]))
        {
          $changes[$k] = $dao->getPropertySerialized($k, $v);
        }
      }
    }

    foreach($dao->getDaoIDProperties() as $prop)
    {
      if(!isset($changes[$prop]))
      {
        $changes[$prop] = $dao->getPropertySerialized($prop, $dao->getDaoProperty($prop));
      }
    }
    return $changes;
  }
}
