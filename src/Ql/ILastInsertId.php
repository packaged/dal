<?php
namespace Packaged\Dal\Ql;

interface ILastInsertId
{
  /**
   * Retrieve the last inserted ID
   *
   * @param string $name Name of the sequence object from which the ID should be returned.
   *
   * @return mixed
   */
  public function getLastInsertId($name = null);
}
