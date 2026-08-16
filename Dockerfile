FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev pkg-config \
    && docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/hackview-entrypoint
COPY . /var/www/html/

RUN chmod +x /usr/local/bin/hackview-entrypoint \
    && mkdir -p /var/www/html/database /var/www/html/storage/raw /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/database /var/www/html/storage \
    && php bin/migrate.php

ENTRYPOINT ["/usr/local/bin/hackview-entrypoint"]

EXPOSE 80
