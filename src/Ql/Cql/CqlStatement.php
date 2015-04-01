<?php
namespace Packaged\Dal\Ql\Cql;

use cassandra\CassandraClient;
use cassandra\CqlPreparedResult;

class CqlStatement
{
  protected $_isPrepared = false;
  protected $_client;
  protected $_query;
  protected $_compression;

  protected $_rawStatement;

  function __construct(CassandraClient $client, $query, $compression)
  {
    $this->_client = $client;
    $this->_query = $query;
    $this->_compression = $compression;
  }

  public function getCompression()
  {
    return $this->_compression;
  }

  public function getQuery()
  {
    return $this->_query;
  }

  /**
   * @return CqlPreparedResult
   */
  public function getStatement()
  {
    $this->prepare();
    return $this->_rawStatement;
  }

  public function prepare()
  {
    if(!$this->_isPrepared)
    {
      $this->_rawStatement = $this->_client->prepare_cql3_query(
        $this->_query,
        $this->_compression
      );
      $this->_isPrepared = true;
    }
  }
}
