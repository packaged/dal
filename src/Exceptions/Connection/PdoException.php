<?php
namespace Packaged\Dal\Exceptions\Connection;

class PdoException extends ConnectionException
{
  /**
   * Create a CQL Exception from various thrift & cassandra exceptions
   *
   * @param \Exception $e
   *
   * @return PdoException
   */
  public static function from(\Exception $e)
  {
    try
    {
      throw $e;
    }
    catch(\PDOException $e)
    {
      if(isset($e->errorInfo[2]))
      {
        return new self($e->errorInfo[2], $e->errorInfo[1], $e);
      }
      return new self($e->getMessage(), $e->getCode(), $e);
    }
    catch(\Exception $e)
    {
      return new self($e->getMessage(), $e->getCode(), $e);
    }
  }
}
