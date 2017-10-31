<?php
namespace Tests\Ql\Mocks\PDO;

class StmtCachePdoConnection extends MockPdoConnection
{
  public function getCachedStatementCount()
  {
    return count($this->_prepareCache);
  }

  public function getCachedStatements()
  {
    return $this->_prepareCache;
  }

  public function getCacheKey($sql)
  {
    return $this->_stmtCacheKey($sql);
  }

  public function setCacheLimit($limit)
  {
    $this->_maxPreparedStatements = $limit;
  }
}