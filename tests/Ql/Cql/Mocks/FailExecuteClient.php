<?php
namespace Packaged\Dal\Tests\Ql\Cql\Mocks;

use cassandra\CassandraClient;
use cassandra\CqlPreparedResult;
use Thrift\Exception\TTransportException;

class FailExecuteClient extends CassandraClient
{
  public function prepare_cql3_query($query, $compression)
  {
    return new CqlPreparedResult();
  }

  public function execute_prepared_cql3_query(
    $itemId, array $values, $consistency
  )
  {
    throw new TTransportException('Class: timed out reading 123 bytes');
  }
}
