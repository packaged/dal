<?php
namespace Tests\Ql\Mocks;

class MockMultiKeyQlDao extends MockQlDao
{
  protected $_tableName = 'mock_multi_key_ql_daos';

  public function getDaoIDProperties()
  {
    return ['id', 'username'];
  }
}
