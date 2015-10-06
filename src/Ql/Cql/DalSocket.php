<?php
namespace Packaged\Dal\Ql\Cql;

use Thrift\Transport\TSocket;

class DalSocket extends TSocket
{
  private $_connectTimeoutMs = 500;
  private $_sendTimeoutMs = 0;

  public function open()
  {
    parent::setSendTimeout($this->_connectTimeoutMs);
    try
    {
      parent::open();
    }
    finally
    {
      parent::setSendTimeout($this->_sendTimeoutMs);
    }
  }

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

  public function setConnectTimeout($connectTimeoutMs)
  {
    $this->_connectTimeoutMs = $connectTimeoutMs;
  }

  public function setSendTimeout($timeout)
  {
    $this->_sendTimeoutMs = $timeout;
    parent::setSendTimeout($timeout);
  }
}
