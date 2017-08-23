<?php
namespace Tests\Ql\Mocks;

class MockNonUniqueKeyDao extends MockQlDao
{
  protected $_tableName = 'mock_non_unique_key_daos';

  public function getDaoIDProperties()
  {
    return ['username'];
  }

  public function getTableName()
  {
    return 'mock_ql_daos';
  }
}

