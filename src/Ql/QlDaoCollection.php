<?php
namespace Packaged\Dal\Ql;

use Packaged\Dal\Collections\IAggregateDaoCollection;
use Packaged\Dal\Foundation\DaoCollection;
use Packaged\QueryBuilder\Builder\QueryBuilder;
use Packaged\QueryBuilder\SelectExpression\AverageSelectExpression;
use Packaged\QueryBuilder\SelectExpression\MaxSelectExpression;
use Packaged\QueryBuilder\SelectExpression\MinSelectExpression;
use Packaged\QueryBuilder\SelectExpression\SumSelectExpression;

/**
 * @method QlDao createNewDao
 */
class QlDaoCollection extends DaoCollection implements IAggregateDaoCollection
{
  /**
   * @return $this
   */
  public function loadWhere(...$params)
  {
    $dao   = $this->createNewDao();
    $query = QueryBuilder::select()->from($dao->getTableName());
    if(func_num_args() > 0)
    {
      $query->where(...$params);
    }
    $results = $dao->getDataStore()->getData($query);
    foreach($results as $result)
    {
      $this->_daos[] = $this->createNewDao()->hydrateDao($result, true);
    }
    return $this;
  }

  public function min($property = 'id')
  {
    if($this->isEmpty())
    {
      $dao       = $this->createNewDao();
      $statement = QueryBuilder::select(MinSelectExpression::create($property))
        ->from($dao->getTableName());
      $results   = $dao->getDataStore()->getData($statement);
      return head(head($results));
    }
    else
    {
      return min(ppull($this->_daos, $property));
    }
  }

  public function max($property = 'id')
  {
    if($this->isEmpty())
    {
      $dao       = $this->createNewDao();
      $statement = QueryBuilder::select(MaxSelectExpression::create($property))
        ->from($dao->getTableName());
      $results   = $dao->getDataStore()->getData($statement);
      return head(head($results));
    }
    else
    {
      return max(ppull($this->_daos, $property));
    }
  }

  public function avg($property = 'id')
  {
    if($this->isEmpty())
    {
      $dao       = $this->createNewDao();
      $statement = QueryBuilder::select(
        AverageSelectExpression::create($property)
      )
        ->from($dao->getTableName());
      $results   = $dao->getDataStore()->getData($statement);
      return head(head($results));
    }
    else
    {
      $values = ppull($this->_daos, $property);
      return array_sum($values) / count($values);
    }
  }

  public function sum($property = 'id')
  {
    if($this->isEmpty())
    {
      $dao       = $this->createNewDao();
      $statement = QueryBuilder::select(
        SumSelectExpression::create($property)
      )
        ->from($dao->getTableName());
      $results   = $dao->getDataStore()->getData($statement);
      return head(head($results));
    }
    else
    {
      return array_sum(ppull($this->_daos, $property));
    }
  }

  /**
   * Find all distinct values of a property in the collection
   *
   * @param $property
   *
   * @return array
   */
  public function distinct($property)
  {
    if($this->isEmpty())
    {
      $dao       = $this->createNewDao();
      $statement = QueryBuilder::selectDistinct($property)
        ->from($dao->getTableName());
      $results   = $dao->getDataStore()->getData($statement);
      if(empty($results))
      {
        return [];
      }
      return ipull($results, $property);
    }
    else
    {
      return parent::distinct($property);
    }
  }
}
