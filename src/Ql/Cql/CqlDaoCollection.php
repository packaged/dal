<?php
namespace Packaged\Dal\Ql\Cql;

use Packaged\Dal\Ql\QlDaoCollection;
use Packaged\Helpers\Arrays;
use Packaged\QueryBuilder\Builder\CQL\CqlQueryBuilder;
use Packaged\QueryBuilder\Clause\SelectClause;
use Packaged\QueryBuilder\SelectExpression\ISelectExpression;

/**
 * @template T of CqlDao
 * @extends QlDaoCollection<T>
 */
class CqlDaoCollection extends QlDaoCollection
{
  /**
   * @return class-string<CqlQueryBuilder>
   */
  protected function _getQueryBuilder()
  {
    return CqlQueryBuilder::class;
  }

  protected function _getAggregate($method, ISelectExpression $expression)
  {
    $originalClause = $this->_query->getClause('SELECT');
    $this->_query->addClause(
      (new SelectClause())->addExpression($expression)
    );
    $result = Arrays::first(
      Arrays::first($this->_getDataStore()->getData($this->_query))
    );
    $this->_query->addClause($originalClause);
    return $result;
  }
}
