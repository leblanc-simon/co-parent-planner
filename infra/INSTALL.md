# Installation — CoParentPlanner

Deux voies : **Docker** (tout-en-un, recommandé pour démarrer) et **bare-metal** (sans Docker,
pas à pas, reproductible). Ce guide est **maintenu à jour à chaque ajout de dépendance** (DoD).

Hébergement de production : **UE obligatoire** (RGPD, cdc §4.8). Les secrets de prod (clé de
chiffrement, passphrase JWT, secret Mercure) viennent d'un coffre/KMS UE — **jamais** du dépôt.

---

## A. Docker (recommandé)

### Prérequis
- Docker Engine ≥ 24 et Docker Compose v2.

### Démarrage
```bash
cd infra
docker compose up --build
```
Au premier démarrage, le service `app` génère les clés JWT (volume partagé) et applique les
migrations automatiquement. Services exposés :

| Service | URL / port | Rôle |
|---|---|---|
| app (FrankenPHP) | http://localhost:8000 | API REST + front web |
| OpenAPI | http://localhost:8000/api/docs.json | contrat (client mobile) |
| Mailpit | http://localhost:8025 | capture des e-mails (dev) |
| Mercure | http://localhost:3000/.well-known/mercure | hub temps réel |
| PostgreSQL | localhost:5432 | base (app/!ChangeMe!/app) |
| Redis | localhost:6379 | cache / rate-limit |

`worker` (Messenger) et `scheduler` tournent en arrière-plan. Vérification :
```bash
curl -fsS http://localhost:8000/api/docs.json | head -c 200
```

### Arrêt / réinitialisation
```bash
docker compose down            # arrête
docker compose down -v         # arrête + supprime les volumes (base, clés JWT)
```

---

## B. Bare-metal (sans Docker)

### B.1 Prérequis et versions

| Outil | Version | Notes |
|---|---|---|
| PHP | **8.4+** | extensions : `pdo_pgsql`, `pgsql`, `intl`, `sodium`, `openssl`, `mbstring`, `ctype`, `iconv`, `zip`, `redis`, `apcu`, `opcache` |
| Composer | 2.x | |
| PostgreSQL | **16** | l'extension `citext` est créée par la 1ʳᵉ migration |
| Redis | 7 | recommandé (cache, rate-limit) ; optionnel en dev (repli filesystem) |
| Symfony CLI | ≥ 5 | recommandé (`symfony server`, injection d'env) |
| Binaire Mercure | récent | optionnel en dev (temps réel) |
| Node.js | optionnel | AssetMapper ne requiert pas Node |
| Flutter SDK | 3.x | pour l'app mobile (`/mobile`, à venir) |

Vérifier : `php -v && php -m | grep -E 'pdo_pgsql|sodium|intl|redis'`.

### B.2 Backend — installation pas à pas

```bash
cd backend
composer install
```

**Variables d'environnement** — créer `backend/.env.local` (jamais committé) :
```dotenv
# Base locale
DATABASE_URL="postgresql://app:app@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
# Clé de chiffrement AES-256-GCM (32 octets, base64) — UNIQUE par environnement
APP_ENCRYPTION_KEY="$(openssl rand -base64 32)"
# Mailer local (MailHog/Mailpit) ; ou null://null pour ne rien envoyer
MAILER_DSN="smtp://127.0.0.1:1025"
```
> L'application **refuse de démarrer** (fail-fast) si `APP_ENCRYPTION_KEY` ou les clés JWT manquent.

**Clés JWT** (lexik) :
```bash
php bin/console lexik:jwt:generate-keypair
# Génère config/jwt/{private,public}.pem (ignorés par git). Passphrase : JWT_PASSPHRASE (.env).
```

**Base de données + migrations** :
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n
# Base de test :
php bin/console --env=test doctrine:database:create
php bin/console --env=test doctrine:migrations:migrate -n
```

**Mercure** (optionnel, temps réel) — lancer le binaire avec le secret `MERCURE_JWT_SECRET` :
```bash
MERCURE_PUBLISHER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' \
MERCURE_SUBSCRIBER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' \
./mercure run --config Caddyfile.dev
```

**Lancer l'application** :
```bash
symfony server:start -d           # ou : php -S 127.0.0.1:8000 -t public
php bin/console messenger:consume async -v        # worker (e-mails, jobs async)
php bin/console messenger:consume scheduler_default -v  # scheduler (tâches planifiées)
```

**Vérification de bon fonctionnement** :
```bash
curl -fsS http://127.0.0.1:8000/api/docs.json | head -c 200      # OpenAPI servi
# Inscription de test → e-mail visible dans MailHog/Mailpit (http://127.0.0.1:8025)
curl -s -X POST http://127.0.0.1:8000/api/auth/register \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"demo@example.com","password":"CorrectHorse42!"}'
```

### B.3 Mobile (Flutter)
À venir (Lot 0, partie mobile). Le client Dart est **généré depuis l'OpenAPI** exporté par le
backend (`contracts/openapi.json`).

---

## C. Outillage qualité (les deux voies)

```bash
cd backend
# Tests (DB requise) — via le binaire symfony pour injecter l'env (port DB auto en Docker)
symfony php bin/phpunit
# ... ou en bare-metal classique :
php bin/phpunit

vendor/bin/phpstan analyse --memory-limit=1G          # analyse statique niveau 8
PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run --diff
php bin/console api:openapi:export --output=../contracts/openapi.json   # contrat OpenAPI
```

---

## D. Variables d'environnement (référence)

| Variable | Rôle | Dev (défaut) |
|---|---|---|
| `APP_SECRET` | secret Symfony | dev-only (`.env`) |
| `APP_ENCRYPTION_KEY` | clé AES-256-GCM des champs 🔒 (base64, 32 o.) | dev-only — **prod : coffre** |
| `DATABASE_URL` | connexion PostgreSQL | `…@127.0.0.1:5432/app` |
| `JWT_PASSPHRASE` | passphrase de la clé privée JWT | dev-only (`.env`) |
| `MERCURE_JWT_SECRET` | secret de signature des JWT Mercure | dev-only |
| `MAILER_DSN` | transport e-mail | `smtp://127.0.0.1:1025` |
| `ACCESS_TOKEN_TTL` / `REFRESH_TOKEN_TTL` / `ONE_TIME_TOKEN_TTL` | durées de vie (s) | 900 / 2592000 / 3600 |
| `COOKIE_SECURE` | force le flag Secure des cookies d'auth | `false` (dev) — **`true` en prod** |
| `TRUSTED_PROXIES` | proxies de confiance (CIDR) | vide — **à définir derrière un LB** |

> **Prod multi-instances** : passer le transport Messenger et les rate-limiters sur **Redis**
> (verrou partagé), et `COOKIE_SECURE=true`, `TRUSTED_PROXIES` selon l'infra.
