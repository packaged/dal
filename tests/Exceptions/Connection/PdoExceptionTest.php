<?php
namespace Tests\Exceptions\Connection;

use Packaged\Dal\Exceptions\Connection\PdoException;

class PdoExceptionTest extends \PHPUnit_Framework_TestCase
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
        $this->assertContains($contain, $formed->getMessage());
      }
    }
    else
    {
      $this->assertContains($contains, $formed->getMessage());
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
