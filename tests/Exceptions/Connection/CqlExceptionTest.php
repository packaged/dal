<?php
namespace Packaged\Dal\Tests\Exceptions\Connection;

use cassandra\AuthenticationException;
use cassandra\AuthorizationException;
use cassandra\InvalidRequestException;
use cassandra\NotFoundException;
use cassandra\SchemaDisagreementException;
use cassandra\TimedOutException;
use cassandra\UnavailableException;
use Packaged\Dal\Exceptions\Connection\CqlException;
use PHPUnit\Framework\TestCase;
use Thrift\Exception\TApplicationException;
use Thrift\Exception\TException;

class CqlExceptionTest extends TestCase
{
  /**
   * @dataProvider exceptionProvider
   *
   * @param $exception
   * @param $code
   * @param $contains
   */
  public function testExceptions($exception, $code, $contains)
  {
    $formed = CqlException::from($exception);
    $this->assertInstanceOf(CqlException::class, $formed);
    $this->assertEquals($code, $formed->getCode());
    if(is_array($contains))
    {
      foreach($contains as $contain)
      {
        $this->assertStringContainsString($contain, $formed->getMessage());
      }
    }
    else
    {
      $this->assertStringContainsString($contains, $formed->getMessage());
    }
    $this->assertSame($exception, $formed->getPrevious());
  }

  public static function exceptionProvider()
  {
    return [
      [new NotFoundException(), 404, "does not exist"],
      [
        new InvalidRequestException(["why" => "tester"]),
        400,
        ["malformed", "tester"]
      ],
      [new UnavailableException(), 503, "all the replicas required"],
      [new TimedOutException(), 408, "did not respond during"],
      [new TApplicationException(), 500, "server error or invalid Thrift"],
      [new AuthenticationException(), 401, "Invalid authentication request"],
      [new AuthorizationException(), 403, "Invalid authorization request"],
      [new SchemaDisagreementException(), 500, "Schemas are not in agreement"],
      [new TException("Thrift", 500), 500, "Thrift"],
    ];
  }
}
