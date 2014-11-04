<?php
namespace Packaged\Dal\Ql;

use Packaged\Dal\Collections\IAggregateDaoCollection;
use Packaged\Dal\Foundation\DaoCollection;
use Packaged\QueryBuilder\Builder\Traits\HavingTrait;
use Packaged\QueryBuilder\Builder\Traits\JoinTrait;
use Packaged\QueryBuilder\Builder\Traits\LimitTrait;
use Packaged\QueryBuilder\Builder\Traits\OrderByTrait;
use Packaged\QueryBuilder\Builder\Traits\WhereTrait;
use Packaged\QueryBuilder\Clause\IClause;
use Packaged\QueryBuilder\Clause\SelectClause;
use Packaged\QueryBuilder\SelectExpression\AverageSelectExpression;
use Packaged\QueryBuilder\SelectExpression\MaxSelectExpression;
use Packaged\QueryBuilder\SelectExpression\MinSelectExpression;
use Packaged\QueryBuilder\SelectExpression\SumSelectExpression;
use Packaged\QueryBuilder\Statement\QueryStatement;

/**
 * @method QlDao createNewDao
 */
class QlDaoCollection extends DaoCollection implements IAggregateDaoCollection
{
  /**
   * @var QueryStatement
   */
  protected $_query;

  use JoinTrait;
  use WhereTrait;
  use OrderByTrait;
  use LimitTrait;
  use HavingTrait;

  protected function _init()
  {
    $this->resetQuery();
  }

  /**
   * Reset the query to a single select * FROM table
   * @return $this
   */
  public function resetQuery()
  {
    $this->_query = new QueryStatement();
    $dao          = $this->createNewDao(false);
    $this->_query->addClause(new SelectClause());
    $this->_query->from($dao->getTableName());
    return $this;
  }

  /**
   * @return QueryStatement
   */
  public function getQuery()
  {
    return $this->_query;
  }

  public function addClause(IClause $clause)
  {
    $this->_query->addClause($clause);
    $this->clear();
    return $this;
  }

  /**
   * @return QlDataStore
   * @throws \Exception
   * @throws \Packaged\Dal\Exceptions\DalResolver\DataStoreNotFoundException
   */
  protected function _getDataStore()
  {
    return $this->createNewDao(false)->getDataStore();
  }

  /**
   * @return $this
   */
  public function loadWhere(...$params)
  {
    if(func_num_args() > 0)
    {
      $this->_query->where(...$params);
    }
    $this->load();
    return $this;
  }

  public function load()
  {
    $dao     = $this->createNewDao(false);
    $results = $dao->getDataStore()->getData($this->_query);
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
      $this->_query->addClause(
        (new SelectClause())->addExpression(
          MinSelectExpression::create($property)
        )
      );
      return head(head($this->_getDataStore()->getData($this->_query)));
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
      $this->_query->addClause(
        (new SelectClause())->addExpression(
          MaxSelectExpression::create($property)
        )
      );
      return head(head($this->_getDataStore()->getData($this->_query)));
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
      $this->_query->addClause(
        (new SelectClause())->addExpression(
          AverageSelectExpression::create($property)
        )
      );
      return head(head($this->_getDataStore()->getData($this->_query)));
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
      $this->_query->addClause(
        (new SelectClause())->addExpression(
          SumSelectExpression::create($property)
        )
      );
      return head(head($this->_getDataStore()->getData($this->_query)));
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
      $select = new SelectClause();
      $select->setDistinct(true);
      $select->addField($property);
      $this->_query->addClause($select);
      $results = $this->_getDataStore()->getData($this->_query);
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
