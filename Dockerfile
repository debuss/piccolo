# FrankenPHP in worker mode: the application is booted once, then handles the requests in a loop.
# See https://frankenphp.dev/docs/worker/ and https://frankenphp.dev/docs/docker/
#
#  docker build -t piccolo .
#  docker run -p 80:80 -p 443:443 -e SERVER_NAME=localhost piccolo
FROM dunglas/frankenphp:1-php8.4

# zip for Composer, OPcache for production
RUN install-php-extensions zip opcache

# The worker script, and the number of requests after which a worker is restarted (unlimited when empty or 0)
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
ENV MAX_REQUESTS=500
ENV APP_ENV=production

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && bin/console cache:warmup
