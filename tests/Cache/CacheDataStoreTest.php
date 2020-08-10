<?php
namespace Packaged\Dal\Tests\Cache;

use Exception;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\Cache\CacheDao;
use Packaged\Dal\Cache\CacheDataStore;
use Packaged\Dal\Cache\CacheItem;
use Packaged\Dal\Cache\Ephemeral\EphemeralConnection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Exceptions\DalResolver\ConnectionNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DaoNotFoundException;
use Packaged\Dal\Exceptions\DataStore\DataStoreException;
use Packaged\Dal\Foundation\AbstractDao;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Tests\Cache\Mocks\MockCacheDataStore;
use PHPUnit\Framework\TestCase;

class CacheDataStoreTest extends TestCase
{
  public function testInvalidDao()
  {
    $this->expectException(DataStoreException::class);
    $dao = $this->getMockForAbstractClass(AbstractDao::class);
    $fs = new CacheDataStore();
    $fs->load($dao);
  }

  public function testUnconfiguredGetConnection()
  {
    $datastore = new CacheDataStore();
    $this->expectException(ConnectionNotFoundException::class);
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

    $this->expectException(DaoNotFoundException::class);
    $this->expectExceptionMessage('Cache Item Not Found');
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
