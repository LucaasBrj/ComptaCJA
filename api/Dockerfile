FROM php:8.4-cli

COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_pgsql intl zip gd opcache bcmath

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
