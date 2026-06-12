FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
        libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) mysqli gd zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

RUN { \
        echo "file_uploads = On"; \
        echo "upload_max_filesize = 20M"; \
        echo "post_max_size = 25M"; \
        echo "memory_limit = 256M"; \
        echo "max_execution_time = 120"; \
        echo "date.timezone = Asia/Ho_Chi_Minh"; \
    } > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p public/uploads/avatars public/uploads/company-logos public/uploads/cv \
    && chown -R www-data:www-data /var/www/html
