<?php
namespace Packaged\Dal\Exceptions\Connection;

use cassandra\AuthenticationException;
use cassandra\AuthorizationException;
use cassandra\InvalidRequestException;
use cassandra\NotFoundException;
use cassandra\SchemaDisagreementException;
use cassandra\TimedOutException;
use cassandra\UnavailableException;
use Thrift\Exception\TApplicationException;

class CqlException extends ConnectionException
{
  public function __construct($msg = "", $code = 0, \Exception $previous = null)
  {
    if($previous !== null)
    {
      $prevMsg = null;
      if(isset($previous->why))
      {
        $prevMsg = $previous->why;
      }
      else
      {
        $prevMsg = $previous->getMessage();
      }

      if((!empty($prevMsg)) && ($prevMsg != $msg))
      {
        $msg = $prevMsg . "\n" . $msg;
      }
    }
    parent::__construct($msg, $code, $previous);
  }

  /**
   * Create a CQL Exception from various thrift & cassandra exceptions
   *
   * @param \Exception $e
   *
   * @return CqlException
   */
  public static function from(\Exception $e)
  {
    try
    {
      throw $e;
    }
    catch(NotFoundException $e)
    {
      return new self(
        "A specific column was requested that does not exist.", 404, $e
      );
    }
    catch(InvalidRequestException $e)
    {
      return new self(
        "Invalid request could mean keyspace or column family does not exist," .
        " required parameters are missing, or a parameter is malformed. " .
        "why contains an associated error message.", 400, $e
      );
    }
    catch(UnavailableException $e)
    {
      return new self(
        "Not all the replicas required could be created and/or read", 503, $e
      );
    }
    catch(TimedOutException $e)
    {
      return new self(
        "The node responsible for the write or read did not respond during" .
        " the rpc interval specified in your configuration (default 10s)." .
        " This can happen if the request is too large, the node is" .
        " oversaturated with requests, or the node is down but the failure" .
        " detector has not yet realized it (usually this takes < 30s).",
        408, $e
      );
    }
    catch(TApplicationException $e)
    {
      return new self(
        "Internal server error or invalid Thrift method (possible if " .
        "you are using an older version of a Thrift client with a " .
        "newer build of the Cassandra server).", 500, $e
      );
    }
    catch(AuthenticationException $e)
    {
      return new self(
        "Invalid authentication request " .
        "(user does not exist or credentials invalid)", 401, $e
      );
    }
    catch(AuthorizationException $e)
    {
      return new self(
        "Invalid authorization request (user does not have access to keyspace)",
        403, $e
      );
    }
    catch(SchemaDisagreementException $e)
    {
      return new self(
        "Schemas are not in agreement across all nodes", 500, $e
      );
    }
    catch(\Exception $e)
    {
      return new self($e->getMessage(), $e->getCode() ?: 500, $e);
    }
  }
}
