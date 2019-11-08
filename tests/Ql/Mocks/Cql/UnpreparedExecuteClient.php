<?php
namespace Packaged\Dal\Tests\Ql\Mocks\Cql;

use cassandra\CassandraClient;
use cassandra\CqlPreparedResult;
use Exception;

class UnpreparedExecuteClient extends CassandraClient
{
  public function prepare_cql3_query($query, $compression)
  {
    return new CqlPreparedResult();
  }

  public function execute_prepared_cql3_query(
    $itemId, array $values, $consistency
  )
  {
    throw new Exception(
      'Prepared query with ID 1 not found (either the query was not prepared on this host (maybe the host has been restarted?) or you have prepared too many queries and it has been evicted from the internal cache)'
    );
  }
}
