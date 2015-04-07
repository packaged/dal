<?php
namespace Packaged\Dal\Ql\Cql;

use Packaged\Dal\Ql\QlDaoCollection;
use Packaged\QueryBuilder\Clause\SelectClause;
use Packaged\QueryBuilder\SelectExpression\ISelectExpression;
use Packaged\QueryBuilder\Statement\CQL\CqlQueryStatement;

/**
 * @method CqlDao createNewDao($fresh = true)
 */
class CqlDaoCollection extends QlDaoCollection
{
  /**
   * @return CqlQueryStatement
   */
  protected function _getNewStatement()
  {
    return new CqlQueryStatement();
  }

  protected function _getAggregate($method, ISelectExpression $expression)
  {
    $originalClause = $this->_query->getClause('SELECT');
    $this->_query->addClause(
      (new SelectClause())->addExpression($expression)
    );
    $result = head(head($this->_getDataStore()->getData($this->_query)));
    $this->_query->addClause($originalClause);
    return $result;
  }
}
