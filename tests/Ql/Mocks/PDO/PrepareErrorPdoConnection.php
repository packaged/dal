<?php
namespace Tests\Ql\Mocks\PDO;

class PrepareErrorPdoConnection extends \PDO
{
  private $_errorMessage;
  private $_errorCode;

  public function __construct($message, $code = 0)
  {
    $this->_errorMessage = $message;
    $this->_errorCode = $code;
  }

  public function prepare(
    $statement,
    /** @noinspection PhpSignatureMismatchDuringInheritanceInspection */
    $options = null
  )
  {
    $exception = new \PDOException($this->_errorMessage, $this->_errorCode);

    $exception->errorInfo = ['SQLSTATE_CODE', $this->_errorMessage];
    throw $exception;
  }

  public function setAttribute($attribute, $value)
  {
  }
}
