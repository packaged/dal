<?php
namespace Packaged\Dal\Tests\Exceptions\Connection;

use Packaged\Dal\Exceptions\Connection\PdoException;
use PHPUnit\Framework\TestCase;

class PdoExceptionTest extends TestCase
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
    $formed = PdoException::from($exception);
    $this->assertInstanceOf(
      '\Packaged\Dal\Exceptions\Connection\PdoException',
      $formed
    );
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

  public function exceptionProvider()
  {
    $errorInfoTest = new \PDOException();
    $errorInfoTest->errorInfo = ['', 501, 'my message'];
    return [
      [new \PDOException('timeout',404), 404, 'timeout'],
      [new \Exception('my error', 500), 500, 'my error'],
      [$errorInfoTest, 501, 'my message']
    ];
  }
}
