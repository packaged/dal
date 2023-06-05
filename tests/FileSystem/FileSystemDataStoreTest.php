<?php
namespace Packaged\Dal\Tests\FileSystem;

use Exception;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\DataStore\DaoNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\FileSystem\FileSystemDao;
use Packaged\Dal\FileSystem\FileSystemDataStore;
use Packaged\Dal\Foundation\AbstractDao;
use Packaged\Dal\Foundation\Dao;
use Packaged\Helpers\Path;
use PHPUnit\Framework\TestCase;

class FileSystemDataStoreTest extends TestCase
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

  public function testIdProperty()
  {
    $mock = new FileSystemDao();
    $this->assertEquals(['filepath'], $mock->getDaoIDProperties());
  }

  public function testLoad()
  {
    $file = new FileSystemDao($this->_getResourceLocation('fs.test'));
    $this->assertFalse($file->isDaoLoaded());
    $this->assertTrue($file->exists());
    $file->load();
    $this->assertTrue($file->isDaoLoaded());
  }

  public function testExceptionMissingFile()
  {
    $this->expectException(DaoNotFoundException::class);
    $file = new FileSystemDao(
      Path::system(dirname(dirname(__DIR__)), 'missing.file')
    );
    $file->load();
  }

  public function testInvalidFilesystemDao()
  {
    $this->expectException(DataStoreException::class);
    $dao = $this->getMockForAbstractClass(AbstractDao::class);
    $fs = new FileSystemDataStore();
    $fs->load($dao);
  }

  public function testFilePathInfo()
  {
    $file = new FileSystemDao(
      $this->_getResourceLocation('fs.test')
    );
    $this->assertEquals('test', $file->getExtension());
    $this->assertEquals('fs', $file->getFilename());
    $this->assertEquals('fs.test', $file->getBasename());
    $this->assertEquals($this->_getResourceLocation(''), $file->getDirectory());
    $this->assertEquals(
      $this->_getResourceLocation('fs.test'),
      $file->getFilePath()
    );
  }

  public function testFileSize()
  {
    $file = new FileSystemDao($this->_getResourceLocation('filesize.test'));
    $file->load();
    $this->assertEquals(14, $file->getFileSize());
  }

  public function testFilesizeWithoutLoad()
  {
    $file = new FileSystemDao($this->_getResourceLocation('filesize.test'));
    $this->assertEquals(14, $file->getFileSize());
  }

  public function testFilesizeWithoutLoadException()
  {
    $this->expectException(DaoNotFoundException::class);
    $file = new FileSystemDao($this->_getResourceLocation('filesize.missing'));
    $file->getFileSize();
  }

  public function testFileCRUD()
  {
    $crudLoc = $this->_getResourceLocation('crud.test');
    $file = new FileSystemDao($crudLoc);
    try
    {
      $file->load();
      $this->assertFalse(true, "crud.test file exists, and shouldn't");
    }
    catch(Exception $e)
    {
      $this->assertEquals(404, $e->getCode());
    }

    $file->content = 'Test Content';
    $file->save();

    $file = null;
    $file = new FileSystemDao($crudLoc);
    $file->load();
    $this->assertEquals('Test Content', $file->content);
    $this->assertEquals('Test Content', $file->getContent());
    $this->assertFileExists($crudLoc);
    $file->delete();
    $this->assertFileDoesNotExist($crudLoc);
  }

  public function testStaticOpen()
  {
    $file = FileSystemDao::open($this->_getResourceLocation('fs.test'));
    $this->assertEquals("content\n", $file->getContent());
  }
}
