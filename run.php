<?php
require_once('vendor/autoload.php');
include_once('test.php');

$connectionConfig = new \Packaged\Config\Provider\Ini\IniConfigProvider(
  build_path(__DIR__, 'resources', 'connections.ini')
);
$datastoreConfig  = new \Packaged\Config\Provider\Ini\IniConfigProvider(
  build_path(__DIR__, 'resources', 'datastores.ini')
);

$resolver = new \Packaged\Dal\DalResolver(
  $connectionConfig,
  $datastoreConfig
);
$resolver->boot();

$test = new \Testing\Test();
$test->run();
echo "\n";
