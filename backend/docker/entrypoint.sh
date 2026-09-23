#!/bin/sh
set -e

mkdir -p config/jwt var/cache var/log

# Attente de la base (tous les services en dépendent).
echo "En attente de la base de données…"
until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
    sleep 2
done

# Initialisation effectuée par le seul service applicatif (RUN_MIGRATIONS=1) pour éviter les courses :
# génération des clés JWT (si absentes) + migrations. Quand des migrations ont effectivement tourné,
# la garde matérialisée est régénérée dans la foulée : une migration peut changer ce que le moteur
# écrit (ex. `custody_days.segments`), et l'extension quotidienne ne passe qu'à 03:00.
if [ "$RUN_MIGRATIONS" = "1" ]; then
    php bin/console lexik:jwt:generate-keypair --skip-if-exists -n
    if ! php bin/console doctrine:migrations:up-to-date >/dev/null 2>&1; then
        php bin/console doctrine:migrations:migrate -n --allow-no-migration
        php bin/console app:custody:regenerate -n
    fi
fi

exec "$@"
