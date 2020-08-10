<?php
namespace Packaged\Dal\Tests\Ql\Cql\Mocks;

use cassandra\CassandraClient;
use cassandra\TimedOutException;

class FailPrepareClient extends CassandraClient
{
  public function prepare_cql3_query($query, $compression)
  {
    throw new TimedOutException();
  }
}
