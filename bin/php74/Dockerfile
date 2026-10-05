FROM php:7.4-apache-bullseye

ARG DEBIAN_FRONTEND=noninteractive

# Update & install semua dependency sekaligus untuk efisiensi
RUN apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y --no-install-recommends \
        apt-utils \
        nano \
        wget \
        dialog \
        libsqlite3-dev \
        libsqlite3-0 \
        default-mysql-client \
        zlib1g-dev \
        libzip-dev \
        libicu-dev \
        build-essential \
        git \
        curl \
        libonig-dev \
        iputils-ping \
        libcurl4 \
        libcurl4-openssl-dev \
        zip \
        openssl \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libmagickwand-dev && \
    rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PECL extensions
RUN pecl install xdebug-3.1.4 && \
    docker-php-ext-enable xdebug && \
    mkdir /var/log/xdebug

RUN pecl install redis-5.1.1 && \
    docker-php-ext-enable redis

RUN pecl install imagick && \
    docker-php-ext-enable imagick

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    pdo_sqlite \
    bcmath \
    mysqli \
    curl \
    tokenizer \
    json \
    zip \
    intl \
    mbstring \
    gettext \
    calendar \
    exif && \
    docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd

# Apache config
RUN a2enmod ssl rewrite headers

# Cleanup
RUN rm -rf /usr/src/*

