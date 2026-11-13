# Development image: the source code is mounted from the host (see compose.yaml),
# so code changes apply without rebuilding.
FROM php:8.5-fpm

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl zip pcntl redis opcache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-tuning.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

# php-fpm listens on 9000; nginx (the `web` service) exposes it on 8080.
EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm", "-R"]
