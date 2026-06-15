# syntax=docker/dockerfile:1

# ---------- frontend build ----------
FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package*.json vite.config.js tailwind.config.js postcss.config.js ./
RUN npm ci
COPY resources ./resources
RUN npm run build

# ---------- app ----------
FROM dunglas/frankenphp:1-php8.4-bookworm

# Tier 2 uses plain HTTP pagination, so no browser is needed at runtime.
# (The optional headless fallback in YandexReviewsScraper needs Chromium +
# `npm install puppeteer@22` — not installed by default to keep the image lean.)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip \
    && install-php-extensions pdo_sqlite intl zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

# PHP deps (no dev in image).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN composer dump-autoload --optimize \
    && cp -n .env.example .env || true

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p database storage/framework/{cache,sessions,views} storage/logs \
    && chown -R www-data:www-data /app/storage /app/database /app/bootstrap/cache

ENV SERVER_NAME=:80
EXPOSE 80
ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
