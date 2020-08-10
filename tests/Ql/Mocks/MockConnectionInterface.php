<?php

namespace Packaged\Dal\Tests\Ql\Mocks;

use Packaged\Dal\Ql\IQLDataConnection;

interface MockConnectionInterface extends IQLDataConnection
{
  public function truncate();

  public function getMockDao();

  public function getMockCounterDao();
}
