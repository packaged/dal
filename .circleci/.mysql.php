<?php

$db = mysqli_init();
mysqli_real_connect($db,'127.0.0.1','root');
mysqli_query($db,'create database packaged_dal;');
mysqli_query($db,'CREATE TABLE `packaged_dal`.`mock_ql_daos` ( `id` int(11) unsigned NOT NULL AUTO_INCREMENT, `username` varchar(50) DEFAULT NULL, `display` varchar(50) DEFAULT NULL, `boolTest` boolean, PRIMARY KEY (`id`) ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;');
mysqli_query($db,'CREATE TABLE `packaged_dal`.`mock_counter_daos` ( `id` varchar(50) NOT NULL, `c1` int(11) DEFAULT NULL, `c2` int(11) DEFAULT NULL, `c3` decimal(10,2) DEFAULT NULL, PRIMARY KEY (`id`) ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;');
