<?php
namespace Packaged\Dal\Tests\FileSystem;

use Packaged\Dal\DalResolver;
use Packaged\Dal\FileSystem\JsonFileDao;
use Packaged\Dal\Foundation\Dao;
use Packaged\Helpers\Path;
use PHPUnit\Framework\TestCase;

class JsonFileDaoTest extends TestCase
{
  protected function _getResourceLocation($filename)
  {
    return Path::system(dirname(__DIR__), 'resources', 'FileSystem', $filename);
  }

  protected function setUp(): void
  {
    $resolver = new DalResolver();
    $resolver->boot();
  }

  protected function tearDown(): void
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
