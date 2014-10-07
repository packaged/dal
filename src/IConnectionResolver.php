<?php
namespace Packaged\Dal;

use Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException;

/**
 * Interface IConnectionResolver Connection Retrieval
 *
 * @package Packaged\Dal
 */
interface IConnectionResolver
{
  /**
   * Retrieve a connection from the resolver
   *
   * @param $name
   *
   * @return IDataConnection
   *
   * @throws ConnectionNotFoundException
   */
  public function getConnection($name);
}
