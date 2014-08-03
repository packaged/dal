<?php
namespace Packaged\Dal\FileSystem;

use Packaged\Dal\Foundation\AbstractDao;

class FileSystemDao extends AbstractDao
{
  public $filepath;
  public $filesize;
  public $content;

  /**
   * @param string $filepath Full path to the file
   */
  public function __construct($filepath = null)
  {
    $this->daoConstruct();
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

  /**
   * Load the file from disk
   *
   * @return FileSystemDao
   * @throws \Packaged\Dal\Exceptions\DataStore\DaoNotFoundException
   */
  public function load()
  {
    return (new FileSystemDataStore())->load($this);
  }

  /**
   * Save the file
   *
   * @return array
   */
  public function save()
  {
    return (new FileSystemDataStore())->save($this);
  }

  /**
   * Delete this file
   *
   * @return FileSystemDao
   */
  public function delete()
  {
    return (new FileSystemDataStore())->delete($this);
  }
}
