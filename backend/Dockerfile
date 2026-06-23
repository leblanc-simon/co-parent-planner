# Image applicative CoParentPlanner — FrankenPHP (sert l'app web + l'API), réutilisée par le
# worker Messenger et le scheduler (commande différente). Cf. cdc §4.8.
FROM dunglas/frankenphp:1-php8.4

# Sert en HTTP simple sur :80 en dev (TLS terminé en amont en prod).
ENV SERVER_NAME=:80 \
    APP_ENV=dev

# Extensions PHP requises + Composer.
RUN install-php-extensions pdo_pgsql intl zip opcache apcu redis @composer

WORKDIR /app

# Dépendances (couche cachable).
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist --no-progress

# Code applicatif (vendor/var/.git exclus via .dockerignore).
COPY . .
RUN composer dump-autoload --no-interaction

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
 && mkdir -p var/cache var/log config/jwt \
 && chown -R www-data:www-data var config/jwt

ENTRYPOINT ["entrypoint"]
# Sert l'app via le Caddyfile par défaut de l'image (racine public/, SERVER_NAME=:80).
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
