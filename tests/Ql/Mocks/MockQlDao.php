<?php
namespace Tests\Ql\Mocks;

use Packaged\Dal\Ql\QlDao;

class MockQlDao extends QlDao
{
  protected $_dataStoreName = 'mockql';

  protected $_tableName = 'mock_ql_daos';

  /**
   * @bigint
   */
  public $id;
  public $username;
  public $display;
  public $boolTest;

  public function setTableName($table)
  {
    $this->_tableName = $table;
    return $this;
  }
}
