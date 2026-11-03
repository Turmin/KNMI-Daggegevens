# PHP-FPM image for the KNMI site (also used by the "cron" service).
FROM php:8.3-fpm-alpine

RUN apk add --no-cache libpq libzip tzdata \
    && apk add --no-cache --virtual .build-deps postgresql-dev libzip-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql zip opcache \
    && apk del .build-deps

ENV TZ=Europe/Amsterdam
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'date.timezone=Europe/Amsterdam\nexpose_php=Off\nmemory_limit=256M\nmax_execution_time=300\n' > "$PHP_INI_DIR/conf.d/knmi.ini" \
    # Pass the container environment (DB_*, APP_ENV, KNMI_*) to PHP-FPM workers
    && printf '[www]\nclear_env = no\n' > /usr/local/etc/php-fpm.d/zz-knmi.conf

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html
COPY docker/cron/run.sh /usr/local/bin/knmi-cron
RUN chmod +x /usr/local/bin/knmi-cron \
    && rm -rf docker docker-compose.yml .github .env* \
    && mkdir -p /data /config && chown www-data:www-data /data /config

USER www-data
