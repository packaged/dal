<?php
namespace Packaged\Dal\Tests\Ql\Cql;

use cassandra\ConsistencyLevel;
use Packaged\Dal\Ql\Cql\CqlConnection;

class CqlQueryObserverConnection extends CqlConnection
{
  protected $_queryLog = [];

  public function getQueries()
  {
    return $this->_queryLog;
  }

  public function runQuery($query, array $values = null)
  {
    $this->_queryLog[] = ['runQuery', $query, $values];
    return parent::runQuery($query, $values);
  }

  public function runRawQuery($query, $consistency = ConsistencyLevel::QUORUM, $retries = null)
  {
    $this->_queryLog[] = ['runRawQuery', $query, $consistency, $retries];
    return parent::runRawQuery($query, $consistency, $retries);
  }
}
