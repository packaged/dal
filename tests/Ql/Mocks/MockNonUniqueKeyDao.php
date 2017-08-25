<?php
namespace Tests\Ql\Mocks;

class MockNonUniqueKeyDao extends MockQlDao
{

  public function getDaoIDProperties()
  {
    return ['username'];
  }

  public function getTableName()
  {
    return 'mock_ql_daos';
  }
}

