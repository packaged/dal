<?php
namespace Tests\Ql\Mocks;

use Packaged\Dal\Exceptions\Connection\ConnectionException;

class FailingPrepareRawConnection
{
  private $_errorMessage;
  private $_errorCode;

  public function __construct($message, $code = 0)
  {
    $this->_errorMessage = $message;
    $this->_errorCode = $code;
  }

  public function ping()
  {
    return true;
  }

  public function prepare()
  {
    $exception = new ConnectionException(
      $this->_errorMessage, $this->_errorCode
    );
    throw $exception;
  }

  public function setAttribute($attribute, $value)
  {
  }
}
