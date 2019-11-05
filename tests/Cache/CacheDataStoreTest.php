<?php
namespace Tests\Cache;

use Exception;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Cache\CacheDao;
use Packaged\Dal\Cache\CacheDataStore;
use Packaged\Dal\Cache\CacheItem;
use Packaged\Dal\Cache\Ephemeral\EphemeralConnection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\Dao;
use PHPUnit_Framework_TestCase;
use Tests\Cache\Mocks\MockCacheDataStore;

class CacheDataStoreTest extends PHPUnit_Framework_TestCase
{
  public function testInvalidDao()
  {
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DataStore\DataStoreException'
    );
    $dao = $this->getMockForAbstractClass(
      '\Packaged\Dal\Foundation\AbstractDao'
    );
    $fs = new CacheDataStore();
    $fs->load($dao);
  }

  public function testUnconfiguredGetConnection()
  {
    $datastore = new CacheDataStore();
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException'
    );
    $datastore->getConnection();
  }

  public function testFlow()
  {
    $datastore = new MockCacheDataStore();
    $connection = new EphemeralConnection();
    $connection->configure(
      new ConfigSection('ephemeral', ['pool_name' => 'cdst'])
    );
    $datastore->setConnection($connection);

    $resolver = new DalResolver();
    $resolver->addDataStore('cache', $datastore);
    $resolver->boot();

    $item = new CacheItem('abc', '123');
    $connection->connect();
    $connection->saveItem($item);
    $connection->disconnect();

    $dao = new CacheDao();
    $dao->key = 'abc';
    $datastore->load($dao);
    $this->assertTrue($datastore->exists($dao));
    $this->assertTrue($dao->exists());
    $this->assertEquals('123', $dao->data);

    $datastore->delete($dao);

    $dao = new CacheDao();
    $dao->key = 'abc';
    try
    {
      $datastore->load($dao);
      $this->assertTrue(false);
    }
    catch(Exception $e)
    {
      $this->assertFalse($datastore->exists($dao));
    }

    $dao->data = '123';
    $datastore->save($dao);
    $this->assertTrue($datastore->exists($dao));
    $this->assertTrue($dao->exists());
    $this->assertEquals('123', $dao->data);

    Dao::unsetDalResolver();
  }

  public function testLoadNone()
  {
    $datastore = new MockCacheDataStore();
    $connection = new EphemeralConnection();
    $connection->configure(
      new ConfigSection('ephemeral', ['pool_name' => 'cdst'])
    );
    $datastore->setConnection($connection);
    $connection->connect();

    $dao = new CacheDao();
    $dao->key = 'xyz';
    $this->assertFalse($datastore->exists($dao));

    $this->setExpectedException(
      'Packaged\Dal\Exceptions\DataStore\DaoNotFoundException',
      'Cache Item Not Found'
    );
    $datastore->load($dao);
  }

  public function testGetConnection()
  {
    $datastore = new MockCacheDataStore();
    $datastore->configure(
      new ConfigSection('cache', ['connection' => 'cacheconn'])
    );
    $connection = new EphemeralConnection();
    $connection->configure(
      new ConfigSection('ephemeral', ['pool_name' => 'mock'])
    );

    $resolver = new DalResolver();
    $resolver->addDataStore('cache', $datastore);
    $resolver->addConnection('cacheconn', $connection);
    $resolver->boot();

    $this->assertSame($connection, $datastore->getConnection());

    Dao::unsetDalResolver();
  }
}
