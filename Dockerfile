FROM node:20-alpine AS assets
WORKDIR /app
RUN corepack enable
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile
COPY resources ./resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN pnpm build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

FROM php:8.3-apache
RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && echo 'PassEnv APP_* DB_* AUTH0_* BOOTSTRAP_ADMIN_EMAIL TRUST_PROXY_HEADERS EVENTS_RETENTION_DAYS' >> /etc/apache2/conf-enabled/monolith-env.conf \
    && sed -i 's/Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-enabled/servername.conf

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .
COPY --from=vendor /app/composer.lock ./composer.lock

RUN chown -R www-data:www-data /var/www/html /var/run/apache2 /var/lock/apache2 /var/log/apache2

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://localhost:8080/health || exit 1
