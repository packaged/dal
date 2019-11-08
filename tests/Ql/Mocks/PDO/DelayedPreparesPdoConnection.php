<?php
namespace Packaged\Dal\Tests\Ql\Mocks\PDO;

class DelayedPreparesPdoConnection extends MockPdoConnection
{
  protected $_lastQueryCacheKey;

  protected function _getStatement($query)
  {
    $res = parent::_getStatement($query);
    $this->_lastQueryCacheKey = $this->_stmtCacheKey($query);
    return $res;
  }

  public function getLastQueryDelayCount()
  {
    return isset($this->_prepareDelayCount[$this->_lastQueryCacheKey])
      ? $this->_prepareDelayCount[$this->_lastQueryCacheKey] : 0;
  }
}
