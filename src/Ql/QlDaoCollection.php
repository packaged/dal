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
use Packaged\QueryBuilder\SelectExpression\AllSelectExpression;
use Packaged\QueryBuilder\SelectExpression\AverageSelectExpression;
use Packaged\QueryBuilder\SelectExpression\CountSelectExpression;
use Packaged\QueryBuilder\SelectExpression\ISelectExpression;
use Packaged\QueryBuilder\SelectExpression\MaxSelectExpression;
use Packaged\QueryBuilder\SelectExpression\MinSelectExpression;
use Packaged\QueryBuilder\SelectExpression\SubQuerySelectExpression;
use Packaged\QueryBuilder\SelectExpression\SumSelectExpression;
use Packaged\QueryBuilder\Statement\IStatement;
use Packaged\QueryBuilder\Statement\IStatementSegment;
use Packaged\QueryBuilder\Statement\QueryStatement;

/**
 * @method QlDao createNewDao($fresh = true)
 */
class QlDaoCollection extends DaoCollection
  implements IAggregateDaoCollection, IStatement
{
  /**
   * @var QueryStatement
   */
  protected $_query;

  protected $_isLoaded;

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
    $this->_query = $this->_getNewStatement();
    $dao = $this->createNewDao(false);
    $select = new SelectClause();
    $select->addExpression(AllSelectExpression::create($dao->getTableName()));
    $this->_query->addClause($select);
    $this->_query->from($dao->getTableName());
    return $this;
  }

  /**
   * @return QueryStatement
   */
  protected function _getNewStatement()
  {
    return new QueryStatement();
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

  public function getClause($action)
  {
    return $this->_query->getClause($action);
  }

  public function hasClause($action)
  {
    return $this->_query->hasClause($action);
  }

  public function removeClause($action)
  {
    return $this->_query->removeClause($action);
  }

  /**
   * @return IStatementSegment[]
   */
  public function getSegments()
  {
    return $this->_query->getSegments();
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

  protected function _prepareDaos()
  {
    if(!$this->_isLoaded)
    {
      $this->load();
    }
    parent::_prepareDaos();
  }

  public function clear()
  {
    $this->_isLoaded = false;
    parent::clear();
  }

  /**
   * @param mixed ...$params expressions to pass to where clause
   *
   * @return $this
   */
  public function loadWhere(...$params)
  {
    $this->resetQuery();
    $this->clear();

    if(func_num_args() > 0)
    {
      $this->_query->where(...$params);
    }
    $this->load();
    return $this;
  }

  public function load()
  {
    $dao = $this->createNewDao(false);
    $results = $dao->getDataStore()->getData($this);
    $this->clear();
    if(!empty($results))
    {
      foreach($results as $result)
      {
        $this->_daos[] = $this->createNewDao()
          ->hydrateDao($result, true)
          ->markDaoAsLoaded()->markDaoDatasetAsSaved();
      }
    }
    $this->_isLoaded = true;
    return $this;
  }

  /**
   * Retrieve the first available dao
   *
   * @param mixed $default
   *
   * @return QlDao
   */
  public function first($default = null)
  {
    if(!$this->isEmpty())
    {
      return parent::first($default);
    }
    if(!$this->_isLoaded)
    {
      $limit = $this->getClause('LIMIT');
      $this->limit(1);
      $this->load();
      $this->removeClause('LIMIT');
      if($limit !== null)
      {
        $this->_query->addClause($limit);
      }
    }
    if(!$this->isEmpty())
    {
      $dao = head($this->_daos);
      $this->clear();
      return $dao;
    }
    return $default;
  }

  public function min($property = 'id')
  {
    return $this->_getAggregate(
      __FUNCTION__,
      MinSelectExpression::create($property)
    );
  }

  public function max($property = 'id')
  {
    return $this->_getAggregate(
      __FUNCTION__,
      MaxSelectExpression::create($property)
    );
  }

  public function avg($property = 'id')
  {
    return $this->_getAggregate(
      __FUNCTION__,
      AverageSelectExpression::create($property)
    );
  }

  public function sum($property = 'id')
  {
    return $this->_getAggregate(
      __FUNCTION__,
      SumSelectExpression::create($property)
    );
  }

  public function count()
  {
    return (int)$this->_getAggregate(__FUNCTION__, new CountSelectExpression());
  }

  protected function _getAggregate($method, ISelectExpression $expression)
  {
    if($this->isEmpty() && !$this->_isLoaded)
    {
      $aggregateQuery = $this->_getNewStatement();
      $aggregateQuery->addClause(
        (new SelectClause())->addExpression($expression)
      );
      $aggregateQuery->from(
        SubQuerySelectExpression::create($this->_query, '_')
      );
      $result = head(head($this->_getDataStore()->getData($aggregateQuery)));
      return $result;
    }
    return parent::$method();
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
      $originalClause = $this->_query->getClause('SELECT');
      $this->_query->addClause($select);
      $results = $this->_getDataStore()->getData($this->_query);
      $this->_query->addClause($originalClause);
      if(empty($results))
      {
        return [];
      }
      return ipull($results, $property);
    }
    return parent::distinct($property);
  }
}
