#!/usr/bin/env bash
# Lance la stack web minimale et exécute les tests E2E Playwright en local.
# - PostgreSQL : conteneur Docker du backend (port hôte auto, détecté par `symfony`).
# - Mailpit    : conteneur sur des ports dédiés (18025 API/UI, 11025 SMTP).
# - App        : `symfony serve` en dev, avec le transport Messenger `async` routé
#                vers `sync://` (l'e-mail d'auth part en synchrone -> pas de worker).
# Idempotent : nettoie ses ressources en sortie.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BACKEND="$ROOT/backend"
E2E="$ROOT/web-e2e"
APP_PORT="${APP_PORT:-8000}"
MAILPIT_NAME="cpp-e2e-mailpit"

cleanup() {
  echo "== nettoyage =="
  (cd "$BACKEND" && symfony server:stop >/dev/null 2>&1) || true
  docker rm -f "$MAILPIT_NAME" >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "== PostgreSQL (conteneur backend) =="
(cd "$BACKEND" && docker compose up -d database >/dev/null)

echo "== Mailpit (18025 API, 11025 SMTP) =="
docker rm -f "$MAILPIT_NAME" >/dev/null 2>&1 || true
docker run -d --name "$MAILPIT_NAME" -p 18025:8025 -p 11025:1025 axllent/mailpit >/dev/null

echo "== Migrations (base dev) =="
(cd "$BACKEND" && symfony console doctrine:database:create --if-not-exists >/dev/null 2>&1 || true)
(cd "$BACKEND" && symfony console doctrine:migrations:migrate -n >/dev/null)

echo "== Démarrage de l'app (port $APP_PORT) =="
cd "$BACKEND"
export MESSENGER_TRANSPORT_DSN="sync://"
export MAILER_DSN="smtp://127.0.0.1:11025"
export MAILER_FROM="no-reply@coparentplanner.test"
export APP_WEB_BASE_URL="http://127.0.0.1:$APP_PORT"
symfony server:stop >/dev/null 2>&1 || true
symfony serve -d --port="$APP_PORT" --no-tls >/dev/null

echo "== Attente de l'app =="
for i in $(seq 1 30); do
  if curl -fsS "http://127.0.0.1:$APP_PORT/login" >/dev/null 2>&1; then break; fi
  sleep 1
done

echo "== Playwright =="
cd "$E2E"
export E2E_BASE_URL="http://127.0.0.1:$APP_PORT"
export MAILPIT_API="http://127.0.0.1:18025"
npx playwright test "$@"
