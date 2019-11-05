<?php
namespace Tests\Ql\Mocks\Cql;

use cassandra\CassandraClient;
use cassandra\CqlPreparedResult;
use cassandra\TimedOutException;

class WriteTimeoutClient extends CassandraClient
{
  public function prepare_cql3_query($query, $compression)
  {
    return new CqlPreparedResult();
  }

  public function execute_prepared_cql3_query(
    $itemId, array $values, $consistency
  )
  {
    throw new TimedOutException();
  }
}
