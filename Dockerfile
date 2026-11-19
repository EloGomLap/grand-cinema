# Image de production : PHP 8.3 + Apache
FROM php:8.3-apache

# Composer et extensions utiles
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite

# Le site est servi depuis le dossier public/ de Symfony
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . .

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

# Dépendances de production, cache, base SQLite et comptes de démonstration
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && php bin/console cache:clear \
    && php bin/console app:initialiser-base \
    && chown -R www-data:www-data var

# Render indique le port à utiliser dans la variable PORT (10000 par défaut)
ENV PORT=10000
RUN sed -ri -e 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri -e 's/:80>/:${PORT}>/' /etc/apache2/sites-available/000-default.conf
EXPOSE 10000
