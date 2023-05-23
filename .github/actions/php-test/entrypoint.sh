#!/bin/sh

set -e

# Start MySQL server
docker run -d --name mysql-server -e MYSQL_ALLOW_EMPTY_PASSWORD=yes mysql:latest

# Start Cassandra server
docker run -d --name cassandra-server cassandra:latest

# Run the tests in the PHP container
docker build -t php-test .
docker run --rm --link mysql-server:mysql --link cassandra-server:cassandra php-test