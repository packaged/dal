<?php
namespace Packaged\Dal\FileSystem;

class JsonFileDao extends FileSystemDao
{
  protected function _configure()
  {
    $this->_addSerializer('content', 'json');
  }
}
