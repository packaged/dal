Data Access Layer
===

[![Latest Stable Version](https://poser.pugx.org/packaged/dal/version.png)](https://packagist.org/packages/packaged/dal)
[![Total Downloads](https://poser.pugx.org/packaged/dal/d/total.png)](https://packagist.org/packages/packaged/dal)
[![CircleCI](https://circleci.com/gh/packaged/dal.svg?style=shield)](https://circleci.com/gh/packaged/dal)
[![Dependency Status](https://www.versioneye.com/php/packaged:dal/badge.png)](https://www.versioneye.com/php/packaged:dal)
[![Coverage Status](https://coveralls.io/repos/packaged/dal/badge.png)](https://coveralls.io/r/packaged/dal)

Getting Started
===
    
    $connectionConfig = new \Packaged\Config\Provider\Ini\IniConfigProvider(
      build_path('config', 'connections.ini')
    );
    $datastoreConfig  = new \Packaged\Config\Provider\Ini\IniConfigProvider(
      build_path('config', 'datastores.ini')
    );
    
    $resolver = new \Packaged\Dal\DalResolver($connectionConfig,$datastoreConfig);
    $resolver->boot();
    
    
  connections.ini

    [users]
    construct_class = \Packaged\Dal\Ql\PdoConnection

  datastores.ini
  
    [users]
    construct_class = \Packaged\Dal\Ql\QlDataStore
    connection = users
    
  users.php
    
    class User extends QlDao
    {
      protected $_dataStoreName = 'users';
      public $id;
      public $name;
    }
    
  Basic Usage
  
    $user       = new User();
    $user->name = 'Test';
    $user->save();
    
    $user->name = 'Testing';
    $user->save();
    
    $user->delete();
    
    $user     = new User();
    $user->id = 4;
    $user->load();
    
    $tbUsers = User::collection(['name' => ['Test','Testing']]);
    foreach($tbUsers as $user)
    {
    echo "Located $user->name\n";
    }
    
    $users = User::collection();
    var_dump($users->min('id'));
    var_dump($users->max('id'));
    var_dump($users->sum('id'));
    var_dump($users->avg('id'));
    
    var_dump_json($users->distinct('name'));

FYI
===
DAO = Data Access Object

SQL Translation
====

**IDao** A Single Row within the database

**IDataStore** The Table within the database

**IDataConection** The connection to the database server e.g. PDO
