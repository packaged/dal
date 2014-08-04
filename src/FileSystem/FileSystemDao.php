<?php
namespace Packaged\Dal\FileSystem;

use Packaged\Dal\Foundation\AbstractDao;
use Packaged\Dal\Traits\Dao\LSDTrait;

/**
 * @method FileSystemDao load Load the file from disk
 * @method FileSystemDao save Save the file to disk
 * @method FileSystemDao delete Delete file from disk
 */
class FileSystemDao extends AbstractDao
{
  use LSDTrait;

  public $filepath;
  public $filesize;
  public $content;

  /**
   * @param string $filepath Full path to the file
   */
  public function __construct($filepath = null)
  {
    $this->daoConstruct();
    $this->_setDataStoreName('filesystem');
    $this->filepath = $filepath;
  }

  /**
   * Open a file from the filesystem
   *
   * @param $filepath
   *
   * @return FileSystemDao
   */
  public static function open($filepath)
  {
    return (new self($filepath))->load();
  }

  /**
   * Get the extension of the file
   *
   * @return string
   */
  public function getExtension()
  {
    return pathinfo($this->filepath)['extension'];
  }

  /**
   * Get the filename, without the extension
   *
   * @return string
   */
  public function getFilename()
  {
    return pathinfo($this->filepath)['filename'];
  }

  /**
   * Get the full filename without the directory
   *
   * @return string
   */
  public function getBasename()
  {
    return pathinfo($this->filepath)['basename'];
  }

  /**
   * Get the parent directory of the file
   *
   * @return string
   */
  public function getDirectory()
  {
    return pathinfo($this->filepath)['dirname'];
  }

  /**
   * Get the full location of the file
   *
   * @return string
   */
  public function getFilePath()
  {
    return $this->filepath;
  }

  /**
   * Retrieve the size of the file
   *
   * @return int
   */
  public function getFileSize()
  {
    if(!$this->isDaoLoaded())
    {
      (new FileSystemDataStore())->getFilesize($this);
    }

    return $this->filesize;
  }

  /**
   * Retrieve the content of the fle
   *
   * @return mixed
   */
  public function getContent()
  {
    return $this->content;
  }
}
