FROM php:8.4.3-cli-alpine

RUN apk add --no-cache postgresql-dev libpq unzip \
    && docker-php-ext-install pdo pdo_pgsql bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PORT=8000

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .

RUN php artisan package:discover --ansi

EXPOSE 8000

CMD ["sh", "-c", "php artisan config:cache; php artisan route:cache; php artisan migrate --force; php artisan db:seed --force; php artisan serve --host=0.0.0.0 --port=8000"]
