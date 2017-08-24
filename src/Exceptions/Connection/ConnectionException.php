<?php
namespace Packaged\Dal\Exceptions\Connection;

use Packaged\Dal\Exceptions\DalException;

/**
 * Exceptions for connection issues
 */
class ConnectionException extends DalException
{
  /**
   * Create an standardized exception
   *
   * @param \Exception $e
   *
   * @return ConnectionException
   */
  public static function from(\Exception $e)
  {
    return new self($e->getMessage(), $e->getCode(), $e);
  }
}
