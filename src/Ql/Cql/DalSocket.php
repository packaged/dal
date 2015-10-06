<?php
namespace Packaged\Dal\Ql\Cql;

use Thrift\Transport\TSocket;

class DalSocket extends TSocket
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
