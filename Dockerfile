FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    python3 \
    python3-requests \
    unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY index.php get_nombres.php header.php styles.css extract_key_value.py health.php ./

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
