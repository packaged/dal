<?php
namespace Packaged\Dal\Ql\Cql;

use Thrift\Transport\TFramedTransport;
use Thrift\Transport\TTransport;

/**
 * Framed transport that serves reads from the current frame by offset.
 *
 * The binary protocol reads a CQL result one 1-8 byte field at a time.
 * TFramedTransport re-slices the remaining frame on every read, so a frame of
 * n bytes costs O(n^2) copying; a few MB is enough to exhaust the request's
 * execution time. Reading by offset keeps it linear.
 */
class DalFramedTransport extends TFramedTransport
{
  /** @var TTransport */
  private $_transport;
  /** @var bool */
  private $_readFramed;
  /** @var string */
  private $_buffer = '';
  /** @var int */
  private $_offset = 0;
  /** @var int */
  private $_length = 0;

  public function __construct($transport = null, $read = true, $write = true)
  {
    parent::__construct($transport, $read, $write);
    $this->_transport = $transport;
    $this->_readFramed = $read;
  }

  public function read($len)
  {
    if(!$this->_readFramed)
    {
      return $this->_transport->read($len);
    }

    if($this->_offset >= $this->_length)
    {
      $this->_readFrame();
      if($this->_length === 0)
      {
        return '';
      }
    }

    $out = substr($this->_buffer, $this->_offset, $len);
    $this->_offset += strlen($out);
    if($this->_offset >= $this->_length)
    {
      $this->_setBuffer('');
    }
    return $out;
  }

  public function putBack($data)
  {
    $remaining = $this->_offset < $this->_length ? substr($this->_buffer, $this->_offset) : '';
    $this->_setBuffer($data . $remaining);
  }

  private function _readFrame()
  {
    $size = unpack('N', $this->_transport->readAll(4))[1];
    $this->_setBuffer($size > 0 ? $this->_transport->readAll($size) : '');
  }

  private function _setBuffer(string $buffer)
  {
    $this->_buffer = $buffer;
    $this->_offset = 0;
    $this->_length = strlen($buffer);
  }
}
