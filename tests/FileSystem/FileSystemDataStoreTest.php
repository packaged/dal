<?php
namespace FileSystem;

use Packaged\Dal\FileSystem\FileSystemDao;
use Packaged\Dal\FileSystem\FileSystemDataStore;

class FileSystemDataStoreTest extends \PHPUnit_Framework_TestCase
{
  protected function _getResourceLocation($filename)
  {
    return build_path(dirname(__DIR__), 'resources', 'FileSystem', $filename);
  }

  public function testLoad()
  {
    $file = new FileSystemDao($this->_getResourceLocation('fs.test'));
    $this->assertFalse($file->isDaoLoaded());
    $file->load();
    $this->assertTrue($file->isDaoLoaded());
  }

  public function testExceptionMissingFile()
  {
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DataStore\DaoNotFoundException'
    );
    $file = new FileSystemDao(
      build_path(dirname(dirname(__DIR__)), 'missing.file')
    );
    $file->load();
  }

  public function testInvalidFilesystemDao()
  {
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DataStore\DataStoreException'
    );
    $dao = $this->getMockForAbstractClass(
      '\Packaged\Dal\Foundation\AbstractDao'
    );
    $fs  = new FileSystemDataStore();
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
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DataStore\DaoNotFoundException'
    );
    $file = new FileSystemDao($this->_getResourceLocation('filesize.missing'));
    $file->getFileSize();
  }

  public function testFileCRUD()
  {
    $crudLoc = $this->_getResourceLocation('crud.test');
    $file    = new FileSystemDao($crudLoc);
    try
    {
      $file->load();
      $this->assertFalse(true, "crud.test file exists, and shouldn't");
    }
    catch(\Exception $e)
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
    $this->assertFileNotExists($crudLoc);
  }

  public function testStaticOpen()
  {
    $file = FileSystemDao::open($this->_getResourceLocation('fs.test'));
    $this->assertEquals("content\n", $file->getContent());
  }
}
