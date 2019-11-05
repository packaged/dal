<?php
namespace Tests\Cache\Mocks;

use Packaged\Dal\Cache\CacheDataStore;
use Packaged\Dal\IDataConnection;

class MockCacheDataStore extends CacheDataStore
{
  public function setConnection(IDataConnection $connection)
  {
    $this->_connection = $connection;
    return $this;
  }
}
