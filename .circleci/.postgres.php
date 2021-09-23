<?php

use Packaged\Dal\DalResolver;
use Packaged\Dal\Ql\PostgresConnection;

require_once(__DIR__ . '/../vendor/autoload.php');

$_connection = new PostgresConnection();
$_connection->setResolver(new DalResolver());
$_connection->getConfig()->addItem('port', 5432);
$_connection->connect();

$_connection->runQuery('CREATE DATABASE IF NOT EXISTS `packaged_dal`;');
$_connection->runQuery('DROP TABLE IF EXISTS `packaged_dal`.`mock_ql_daos`');
$_connection->runQuery('DROP TABLE IF EXISTS `packaged_dal`.`mock_counter_daos`');
$_connection->runQuery('DROP TABLE IF EXISTS `packaged_dal`.`mock_set_daos`');
$_connection->runQuery(
  'CREATE TABLE IF NOT EXISTS `packaged_dal`.`mock_ql_daos` ( `id` int(11) unsigned NOT NULL AUTO_INCREMENT, `username` varchar(50) DEFAULT NULL, `display` varchar(50) DEFAULT NULL, `boolTest` boolean, PRIMARY KEY (`id`) ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;'
);
$_connection->runQuery(
  'CREATE TABLE IF NOT EXISTS `packaged_dal`.`mock_counter_daos` ( `id` varchar(50) NOT NULL, `c1` int(11) DEFAULT NULL, `c2` int(11) DEFAULT NULL, `c3` decimal(10,2) DEFAULT NULL, PRIMARY KEY (`id`) ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;'
);
$_connection->runQuery(
  'CREATE TABLE IF NOT EXISTS `packaged_dal`.`mock_set_daos` ( `id` varchar(50) NOT NULL, `s` SET(\'one\',\'two\',\'three\',\'four\',\'five\') DEFAULT NULL, PRIMARY KEY (`id`) ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;'
);
