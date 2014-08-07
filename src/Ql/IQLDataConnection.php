<?php
namespace Packaged\Dal\Ql;

use Packaged\Dal\IDataConnection;

interface IQLDataConnection extends IDataConnection
{
  /**
   * Execute a query
   *
   * @param       $query
   * @param array $values
   *
   * @return int number of affected rows
   */
  public function runQuery($query, array $values = null);

  /**
   * Fetch the results of the query
   *
   * @param       $query
   * @param array $values
   *
   * @return array
   */
  public function fetchQueryResults($query, array $values = null);
}
