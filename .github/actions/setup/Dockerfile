FROM php:latest

# Install MySQL client and PDO extension
RUN apt-get update && apt-get install -y \
    default-mysql-client \
    libpq-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql

# Install Cassandra CQL driver
RUN apt-get install -y libssl-dev && pecl install cassandra

WORKDIR /app
COPY . /app


RUN composer install -n --prefer-dist
RUN php .circleci/.mysql.php
RUN php .circleci/.cassandra.php
