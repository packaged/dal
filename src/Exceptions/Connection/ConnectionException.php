<?php
namespace Packaged\Dal\Exceptions\Connection;

use Packaged\Dal\Exceptions\DalException;

/**
 * Exceptions for connection issues
 */
class ConnectionException extends DalException
{
  /**
   * Create a CQL Exception from various thrift & cassandra exceptions
   *
   * @param \Exception $e
   *
   * @return ConnectionException
   */
  public static function from(\Exception $e)
  {
    try
    {
      throw $e;
    }
    catch(\Exception $e)
    {
      return new self($e->getMessage(), $e->getCode(), $e);
    }
  }
}
