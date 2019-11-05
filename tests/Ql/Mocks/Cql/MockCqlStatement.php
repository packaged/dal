<?php
namespace Tests\Ql\Mocks\Cql;

use Packaged\Dal\Ql\Cql\CqlStatement;

class MockCqlStatement extends CqlStatement
{
  public function setStatement($stmt)
  {
    $this->_isPrepared = true;
    $this->_rawStatement = $stmt;
  }
}
