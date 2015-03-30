<?php
namespace Packaged\Dal\Ql\Cql;

use Packaged\Dal\Ql\QlDaoCollection;
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
}
