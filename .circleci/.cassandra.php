<?php

require_once(__DIR__ . '/../vendor/autoload.php');

use Packaged\Dal\DalResolver;
use Packaged\Dal\Ql\Cql\CqlConnection;

$_connection = new CqlConnection();
$_connection->setConfig('connect_timeout', 10000);
$_connection->setConfig('receive_timeout', 10000);
$_connection->setConfig('send_timeout', 10000);

$_connection->setResolver(new DalResolver());
$_connection->connect();
$_connection->runQuery(
  "CREATE KEYSPACE IF NOT EXISTS packaged_dal WITH REPLICATION = {'class' : 'SimpleStrategy','replication_factor' : 1};"
);
$_connection->runQuery(
  "CREATE KEYSPACE IF NOT EXISTS packaged_dal_switch WITH REPLICATION = {'class' : 'SimpleStrategy','replication_factor' : 1};"
);
$_connection->runQuery(
  'DROP TABLE IF EXISTS packaged_dal.mock_ql_daos'
);
$_connection->runQuery(
  'CREATE TABLE packaged_dal.mock_ql_daos ('
  . '"id" varchar,'
  . '"id2" int,'
  . '"username" varchar,'
  . '"display" varchar,'
  . '"intVal" int,'
  . '"bigintVal" bigint,'
  . '"doubleVal" double,'
  . '"floatVal" float,'
  . '"decimalVal" decimal,'
  . '"negDecimalVal" decimal,'
  . '"timestampVal" timestamp,'
  . '"boolVal" boolean,'
  . ' PRIMARY KEY ((id), id2));'
);
$_connection->runQuery(
  'DROP TABLE IF EXISTS packaged_dal.mock_counter_daos'
);
$_connection->runQuery(
  'CREATE TABLE packaged_dal.mock_counter_daos ('
  . '"id" varchar PRIMARY KEY,'
  . '"c1" counter,'
  . '"c2" counter,'
  . ');'
);
$_connection->runQuery(
  'DROP TABLE IF EXISTS packaged_dal.mock_set_daos'
);
$_connection->runQuery(
  'CREATE TABLE packaged_dal.mock_set_daos ('
  . '"id" varchar PRIMARY KEY,'
  . '"s" set<text>,'
  . ');'
);
