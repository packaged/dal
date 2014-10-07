<?php
namespace Packaged\Dal;

use Packaged\Dal\Exceptions\Connection\ConnectionException;

/**
 * Interface IDataConnection Responsible for communication
 *
 * @example The connection between the client and server
 *
 * @package Packaged\Dal
 */
interface IDataConnection
{
  /**
   * Open the connection
   *
   * @return static
   *
   * @throws ConnectionException
   */
  public function connect();

  /**
   * Check to see if the connection is already open
   *
   * @return bool
   */
  public function isConnected();

  /**
   * Disconnect the open connection
   *
   * @return static
   *
   * @throws ConnectionException
   */
  public function disconnect();
}
