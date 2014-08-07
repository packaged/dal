<?php
namespace Packaged\Dal\Ql;

interface ILastInsertId
{
  /**
   * Retrieve the last inserted ID
   *
   * @return mixed
   */
  public function getLastInsertId();
}
