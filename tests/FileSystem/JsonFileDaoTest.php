<?php
namespace Tests\FileSystem;

use Packaged\Dal\DalResolver;
use Packaged\Dal\FileSystem\FileSystemDao;
use Packaged\Dal\FileSystem\JsonFileDao;
use Packaged\Dal\Foundation\Dao;
use Packaged\Helpers\Path;

class JsonFileDaoTest extends \PHPUnit_Framework_TestCase
{
  protected function _getResourceLocation($filename)
  {
    return Path::build(dirname(__DIR__), 'resources', 'FileSystem', $filename);
  }

  protected function setUp()
  {
    $resolver = new DalResolver();
    $resolver->boot();
  }

  protected function tearDown()
  {
    Dao::unsetDalResolver();
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
