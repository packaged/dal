<?php
namespace Packaged\Dal\Ql\Cql;

use Thrift\Transport\TSocketPool;

class DalSocketPool extends TSocketPool
{
  /**
   * Calling close should disconnect persistent connections.
   */
  public function close()
  {
    $persist = $this->persist_;
    $this->persist_ = false;
    parent::close();
    $this->persist_ = $persist;
  }
}
