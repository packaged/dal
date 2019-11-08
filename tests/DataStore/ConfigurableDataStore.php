<?php
namespace Packaged\Dal\Tests\DataStore;

class ConfigurableDataStore implements \Packaged\Dal\IDataStore,
                                       \Packaged\Config\ConfigurableInterface
{
  use \Packaged\Config\ConfigurableTrait;

  public function getConfig()
  {
    return $this->_config();
  }

  /**
   * Save a DAO to the data store
   *
   * @param \Packaged\Dal\IDao $dao
   *
   * @return array of changed properties
   *
   * @throws \Packaged\Dal\Exceptions\DataStore\DataStoreException
   */
  public function save(\Packaged\Dal\IDao $dao)
  {
  }

  /**
   * Hydrate a DAO from the data store
   *
   * @param \Packaged\Dal\IDao $dao
   *
   * @return \Packaged\Dal\IDao Loaded DAO
   *
   * @throws \Packaged\Dal\Exceptions\DataStore\DaoNotFoundException
   */
  public function load(\Packaged\Dal\IDao $dao)
  {
  }

  /**
   * Delete the DAO from the data store
   *
   * @param \Packaged\Dal\IDao $dao
   *
   * @return \Packaged\Dal\IDao
   *
   * @throws \Packaged\Dal\Exceptions\DataStore\DataStoreException
   */
  public function delete(\Packaged\Dal\IDao $dao)
  {
  }

  /**
   * Does the object exist in the data store
   *
   * @param \Packaged\Dal\IDao $dao
   *
   * @return bool
   */
  public function exists(\Packaged\Dal\IDao $dao)
  {
  }
}
