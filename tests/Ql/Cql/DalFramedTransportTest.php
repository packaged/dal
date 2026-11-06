<?php
namespace Packaged\Dal\Tests\Ql\Cql;

use Packaged\Dal\Ql\Cql\DalFramedTransport;
use PHPUnit\Framework\TestCase;
use Thrift\Transport\TMemoryBuffer;

class DalFramedTransportTest extends TestCase
{
  private function _frame(string $payload): string
  {
    return pack('N', strlen($payload)) . $payload;
  }

  public function testReadsSmallFieldsAcrossFrames()
  {
    $transport = new DalFramedTransport(
      new TMemoryBuffer($this->_frame('abcdef') . $this->_frame('ghij'))
    );
    $transport->open();

    $this->assertSame('abcd', $transport->read(4));
    $this->assertSame('ef', $transport->read(4));
    $this->assertSame('gh', $transport->readAll(2));
    $this->assertSame('ij', $transport->read(10));
  }

  public function testReadAllSpansFrames()
  {
    $transport = new DalFramedTransport(
      new TMemoryBuffer($this->_frame('abc') . $this->_frame('def'))
    );
    $this->assertSame('abcdef', $transport->readAll(6));
  }

  public function testPutBackIsReadFirst()
  {
    $transport = new DalFramedTransport(new TMemoryBuffer($this->_frame('abcdef')));

    $this->assertSame('ab', $transport->read(2));
    $transport->putBack('XY');
    $this->assertSame('XYcd', $transport->read(4));
    $this->assertSame('ef', $transport->read(4));

    $transport->putBack('Z');
    $this->assertSame('Z', $transport->read(4));
  }

  public function testUnframedReadPassesThrough()
  {
    $transport = new DalFramedTransport(new TMemoryBuffer('raw-bytes'), false, false);
    $this->assertSame('raw', $transport->read(3));
  }

  public function testWritesAreFramed()
  {
    $buffer = new TMemoryBuffer();
    $transport = new DalFramedTransport($buffer);
    $transport->write('hello');
    $transport->flush();
    $this->assertSame($this->_frame('hello'), $buffer->getBuffer());
  }

  /**
   * The binary protocol reads one field at a time, so a large CQL result is
   * consumed as millions of 1-8 byte reads from a single frame.
   */
  public function testLargeFrameReadInSmallFieldsIsLinear()
  {
    $size = 2 * 1024 * 1024;
    $payload = str_repeat('0123456789abcdef', $size / 16);
    $transport = new DalFramedTransport(new TMemoryBuffer($this->_frame($payload)));

    $start = microtime(true);
    $out = '';
    while(strlen($out) < $size)
    {
      $out .= $transport->read(4);
    }
    $elapsed = microtime(true) - $start;

    $this->assertSame($payload, $out);
    $this->assertLessThan(2.0, $elapsed);
  }
}
