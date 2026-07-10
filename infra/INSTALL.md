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
| Flutter SDK | 3.38.x (Dart 3.10.x) | app mobile `/mobile` (génération client = pur Dart, sans JVM) |

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

> **Notifications e-mail (Lot 7)** : les notifications (`App\Message\SendNotification`) et les e-mails
> transactionnels passent par le **même transport `async`** — le worker ci-dessus les traite (retry +
> `failed` en cas d'échec SMTP). Les **rappels d'échéance** des décisions sont un job du
> `scheduler_default` (toutes les 15 min, idempotent). Aucune nouvelle variable d'environnement : les
> heures de silence (quiet-hours) et les préférences par catégorie sont stockées par utilisateur en base.
> Le canal `push` et la catégorie `expenses` sont réservés v2 (inactifs).

**Vérification de bon fonctionnement** :
```bash
curl -fsS http://127.0.0.1:8000/api/docs.json | head -c 200      # OpenAPI servi
# Inscription de test → e-mail visible dans MailHog/Mailpit (http://127.0.0.1:8025)
curl -s -X POST http://127.0.0.1:8000/api/auth/register \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"demo@example.com","password":"CorrectHorse42!"}'
```

### B.3 Mobile (Flutter)

Prérequis : **Flutter 3.38.x / Dart 3.10.x** (`flutter doctor` au vert pour Android ; iOS nécessite
un macOS + Xcode). **Aucune JVM n'est requise pour la génération du client** (elle est pure Dart).

```bash
cd mobile
flutter pub get

# 1) Le backend exporte l'OpenAPI (voir C) -> contracts/openapi.json
# 2) Générer le client Dart (clients retrofit/dio + modèles) depuis ce contrat :
dart run swagger_parser
# 3) Générer le code (.g.dart : json_serializable, retrofit, drift) :
dart run build_runner build --delete-conflicting-outputs --force-jit
#    /!\ --force-jit est IMPÉRATIF : certaines dépendances natives (sqlite3,
#        path_provider_foundation) embarquent un hook/build.dart (native-assets) qui fait
#        échouer la compilation AOT du script build_runner sous Dart 3.10. Le mode JIT l'évite.
# 4) Localisations (si chaînes i18n modifiées) :
flutter gen-l10n

# Lancer l'app sur un device/émulateur :
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000   # 10.0.2.2 = hôte depuis l'émulateur Android
#   Sur simulateur iOS / web : --dart-define=API_BASE_URL=http://localhost:8000

# Qualité & tests :
dart analyze
flutter test --coverage          # seuil de couverture ≥ 70 % (décision D8)
```

Le dossier `lib/api/generated/` est **commité et ne doit jamais être édité à la main** : il est
régénéré par les commandes ci-dessus (la régénération est idempotente ; toute divergence avec
`contracts/openapi.json` est détectée en CI par `git diff --exit-code lib/api/generated/`).
Les polices du design system (Bricolage Grotesque, Hanken Grotesk) sont **auto-hébergées** sous
`mobile/assets/fonts/` (OFL).

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
| `RGPD_EXPORT_DIR` | répertoire des archives d'export RGPD (hors web-root) | `%kernel.project_dir%/var/exports` — **prod : stockage objet UE** |
| `RGPD_EXPORT_LINK_TTL` | TTL (s) du lien signé de téléchargement d'export | `3600` |
| `RGPD_ADMIN_STEP_UP_TTL` | TTL (s) du grant step-up admin technique (auth renforcée) | `900` |
| `RGPD_EU_HOSTING` | hébergement UE confirmé (reporté par le rapport de conformité) | `1` |
| `RGPD_RETENTION_*` | durées de rétention/purge (comptes inactifs, invitations, exports, journaux, notifications) | 1095 j / 30 j / 24 h / 1095 j / 90 j |
| `RL_RGPD_EXPORT_LIMIT` | exports RGPD max par utilisateur et par heure | `5` |

> **RGPD (Lot 8)** : en dev/bare-metal les exports sont écrits dans `backend/var/exports` (créé
> automatiquement, hors web-root) et servis par lien signé HMAC ; **en prod**, brancher une impl
> `ExportStorage` sur un stockage objet UE (S3-compatible) et fournir `RGPD_EXPORT_DIR`/équivalent.
> La purge RGPD est planifiée quotidiennement (worker `scheduler_default`). Promouvoir un
> administrateur technique : `UPDATE users SET technical_admin = true WHERE email = '…';` (aucun rôle
> familial n'accède à `/api/admin/rgpd/*` ; auth renforcée par step-up en plus).

> **Prod multi-instances** : passer le transport Messenger et les rate-limiters sur **Redis**
> (verrou partagé), et `COOKIE_SECURE=true`, `TRUSTED_PROXIES` selon l'infra.

---

## E. Build iOS sans Mac (Lot 9 — deux pipelines CI)

> 🚧 **À VENIR — Lot 9 non encore échafaudé.** Cette section décrit la **procédure cible** ; les
> fichiers mentionnés (`.github/workflows/build-ios.yml`, `codemagic.yaml`, `mobile/ios/fastlane/*`,
> `mobile/ios/Gemfile`, dépôt de certificats `match`) **n'existent pas encore** et seront créés lors
> de l'implémentation du Lot 9. Contrairement aux sections A–D (opérationnelles, Lots 0→8), rien ici
> n'est encore exécutable en l'état.

> Objectif : produire un **`.ipa` signé** (artefact CI, puis TestFlight) **sans poste Mac local**.
> La compilation iOS exige un runner macOS ; ce projet en maintiendra **deux, interchangeables** :
> **GitHub Actions** (`macos-latest`, pipeline principal) et **Codemagic** (secours/alternatif).
> Ils partageront la **même logique de build/signature** (un `Fastfile` unique) et le **même dépôt
> de certificats `match`** — basculer de l'un à l'autre ne demandera aucune réécriture.
> Détails et justification : `specs/lots/lot-9-cicd-build-ios-sans-mac.md`.

### E.1 Prérequis (une fois, hors CI)

- **Compte Apple Developer Program** actif (99 $/an) et une **clé API App Store Connect** (`.p8` +
  `key id` + `issuer id`) — pas d'identifiant/mot de passe Apple personnel en CI.
- **Dépôt Git privé de certificats** (`match`), initialisé **une seule fois depuis un Mac** (ou un
  job CI manuel dédié) par un mainteneur habilité :
  ```bash
  cd mobile/ios
  bundle install                       # installe fastlane (voir mobile/ios/Gemfile)
  bundle exec fastlane match init      # crée Matchfile (URL du dépôt de certificats)
  bundle exec fastlane match appstore  # génère + chiffre certificats/profils dans le dépôt privé
  ```
  > La génération/révocation de certificats reste **manuelle et locale** : la CI est **`--readonly`**
  > (elle consomme les certificats, ne les régénère jamais).

### E.2 Secrets à configurer — **dans les deux systèmes**

Mêmes valeurs des deux côtés (elles pointent vers le même dépôt `match` et le même compte ASC) :

| Secret | Rôle | GitHub Actions | Codemagic (groupe `ios_signing`) |
|---|---|---|---|
| `MATCH_GIT_URL` | URL du dépôt de certificats | Repository secret | variable de groupe |
| `MATCH_PASSWORD` | passphrase de déchiffrement `match` | Repository secret | variable de groupe (masquée) |
| `MATCH_GIT_TOKEN` | token d'accès (lecture seule) au dépôt de certificats | Repository secret | variable de groupe |
| `ASC_KEY_ID` / `ASC_ISSUER_ID` / `ASC_KEY_CONTENT` | clé API App Store Connect (upload TestFlight) | Repository secrets | variables de groupe |
| `APPLE_TEAM_ID` | identifiant d'équipe Apple | Repository secret | variable de groupe |

> **Rotation synchronisée** : toute rotation de `MATCH_PASSWORD` ou de la clé API ASC doit être
> répercutée **le même jour dans les deux systèmes**, sinon l'un des deux pipelines cesse de
> fonctionner sans erreur explicite avant son prochain run. Pour révoquer un certificat compromis :
> `bundle exec fastlane match nuke appstore` depuis un Mac, puis régénérer (`match appstore`) et
> mettre à jour `MATCH_PASSWORD` des deux côtés.

### E.3 Déclencher un build

Aucun secret ni certificat n'apparaît jamais en clair dans les logs ou artefacts, sur aucune CI.

- **GitHub Actions** — workflow `.github/workflows/build-ios.yml` :
  - manuellement via **Actions → « iOS build » → Run workflow** (`workflow_dispatch`), en choisissant
    la lane `build` (artefact seul) ou `release` (artefact + upload TestFlight) ;
  - automatiquement sur **push d'un tag `v*`** (release).
- **Codemagic** — workflow `ios-build` (`codemagic.yaml`) : **build manuel depuis le dashboard**
  Codemagic ; un second workflow `ios-release` réalise l'upload TestFlight.

> **Jamais d'upload TestFlight automatique sur simple commit** (sur aucune des deux CI). La lane
> `release` / le workflow `ios-release` sont **explicites**. La soumission à revue App Store reste
> **manuelle** (hors CI).

### E.4 Récupérer le build

- **GitHub Actions** : onglet **Artifacts** du run → `.ipa` téléchargeable (conservé N jours ;
  nommage incluant le commit SHA / tag).
- **Codemagic** : section **Artifacts** du build (`build/ios/ipa/*.ipa`).
- **TestFlight** (si lane `release`) : build installable sur device réel via l'app TestFlight.

### E.5 Basculer d'une CI à l'autre

La logique de build vit dans `mobile/ios/fastlane/Fastfile` (lanes `build` / `release`) ; les deux
CI ne font que l'appeler. Donc :

- **GitHub Actions indisponible / en file d'attente** → déclencher le build sur Codemagic (secrets
  déjà synchronisés, rien à préparer).
- **Ne garder qu'une seule CI** → supprimer le fichier de déclenchement de l'autre
  (`.github/workflows/build-ios.yml` **ou** `codemagic.yaml`) ; `Fastfile`/`Matchfile` et le dépôt
  de certificats restent inchangés.

> **Dépendances ajoutées par ce lot** (installées sur le runner macOS uniquement, pas requises pour
> le dev backend/web) : **Ruby + Bundler**, **Fastlane** (`mobile/ios/Gemfile`), **CocoaPods**
> (`cd ios && pod install`). La version Flutter est **figée, identique au Lot 0**.
