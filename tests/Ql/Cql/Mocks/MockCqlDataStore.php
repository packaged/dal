<?php
namespace Packaged\Dal\Tests\Ql\Cql\Mocks;

use Packaged\Dal\Ql\Cql\CqlDataStore;
use Packaged\Dal\Ql\IQLDataConnection;

class MockCqlDataStore extends CqlDataStore
{
  public function setConnection(IQLDataConnection $connection)
  {
    $this->_connection = $connection;
    return $this;
  }
}
