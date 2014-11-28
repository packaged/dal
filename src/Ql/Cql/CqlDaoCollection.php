<?php
namespace Packaged\Dal\Ql\Cql;

use Packaged\Dal\Ql\QlDaoCollection;
use Packaged\QueryBuilder\Clause\SelectClause;
use Packaged\QueryBuilder\SelectExpression\AllSelectExpression;
use Packaged\QueryBuilder\Statement\CQL\CqlQueryStatement;

class CqlDaoCollection extends QlDaoCollection
{
  /**
   * Reset the query to a single select * FROM table
   * @return $this
   */
  public function resetQuery()
  {
    $this->_query = new CqlQueryStatement();
    $dao          = $this->createNewDao(false);
    $select       = new SelectClause();
    $select->addExpression(AllSelectExpression::create($dao->getTableName()));
    $this->_query->addClause($select);
    $this->_query->from($dao->getTableName());
    return $this;
  }
}
