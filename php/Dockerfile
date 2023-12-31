FROM php:8.2.12-fpm

RUN apt-get update && apt-get -y upgrade && apt-get install -y libzip-dev libpng-dev \
    git zip unzip libgd3 libgd-tools libxml2 libxml2-dev mariadb-client \
    && docker-php-ext-install pdo_mysql mysqli gd soap zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
