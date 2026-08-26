# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — PHP eklentileri (build ve runtime'in ORTAK tabani)
#
# Eklentiler kaynaktan derleniyor ve imajin en pahali adimi bu. Daha once
# hem build hem runtime asamasi ayni listeyi ayri ayri derliyordu; tek bir
# tabana cikarilinca is bir kez yapilir ve iki asama ayni katmani paylasir.
# ---------------------------------------------------------------------------
FROM php:8.4-cli-alpine AS php-base
RUN apk add --no-cache curl ca-certificates \
 && curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o /usr/local/bin/install-php-extensions \
 && chmod +x /usr/local/bin/install-php-extensions \
 && install-php-extensions pdo_sqlite pdo_pgsql pdo_mysql redis bcmath intl zip pcntl opcache sockets

# ---------------------------------------------------------------------------
# Stage 2 — PHP Dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --ignore-platform-reqs --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ---------------------------------------------------------------------------
# Stage 3 — Frontend Build (Vite + Wayfinder)
# ---------------------------------------------------------------------------
FROM php-base AS build
WORKDIR /app
RUN apk add --no-cache nodejs npm git unzip libpng-dev libzip-dev

COPY --from=vendor /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative
RUN cp .env.example .env && php artisan key:generate --force
RUN npm install && npm run build && rm -rf node_modules .env

# ---------------------------------------------------------------------------
# Stage 4 — Runtime (Laravel Octane + RoadRunner)
# ---------------------------------------------------------------------------
FROM php-base AS app
WORKDIR /app

ENV OCTANE_SERVER=roadrunner

# RoadRunner Binary
COPY --from=ghcr.io/roadrunner-server/roadrunner:2024.3 /usr/bin/rr /usr/local/bin/rr

RUN apk add --no-cache shadow gosu

COPY --from=build /app /app

# octane:start her aciliste .rr.yaml'i touch'lar; dosyayi burada olusturup
# www-data'ya veriyoruz, aksi halde root'a ait /app icinde yazamaz.
RUN chmod +x docker/entrypoint.sh \
 && chmod +x /usr/local/bin/rr \
 && ln -sf /usr/local/bin/rr /app/rr \
 && touch /app/.rr.yaml \
 && chown -R www-data:www-data storage bootstrap/cache public /app/.rr.yaml

ENTRYPOINT ["docker/entrypoint.sh"]
EXPOSE 8000
CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8000"]
