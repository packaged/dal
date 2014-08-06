<?php
namespace FileSystem;

use Packaged\Dal\DalResolver;
use Packaged\Dal\FileSystem\FileSystemDao;
use Packaged\Dal\FileSystem\JsonFileDao;

class JsonFileDaoTest extends \PHPUnit_Framework_TestCase
{
  protected function _getResourceLocation($filename)
  {
    return build_path(dirname(__DIR__), 'resources', 'FileSystem', $filename);
  }

  protected function setUp()
  {
    FileSystemDao::setDalResolver(new DalResolver());
  }

  protected function tearDown()
  {
    FileSystemDao::unsetDalResolver();
  }

  public function testJsonObjectify()
  {
    $mock = new JsonFileDao(
      $this->_getResourceLocation('content.json')
    );
    $mock->load();

    $this->assertEquals('packaged/dal', $mock->content->name);
    $mock->content->name = 'packaged/dali';
    $mock->save();

    $mock = new JsonFileDao(
      $this->_getResourceLocation('content.json')
    );
    $mock->load();
    $this->assertEquals('packaged/dali', $mock->content->name);

    $mock->content->name = 'packaged/dal';
    $mock->save();
  }
}
