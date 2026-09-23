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
| `fonttools` (pip) | optionnel | **régénération** des coupes de polices (`mobile/tool/build_fonts.py`) ; les fichiers produits sont commités |

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

**Mercure** (temps réel) — lancer le binaire avec le secret `MERCURE_JWT_SECRET`. `SERVER_NAME=':80'`
est **nécessaire en local** : sans lui, le hub force HTTPS et redirige vers un port fermé, ce qui rend
le temps réel inexerçable en développement (revue 2026-08, constat C11). Les origines du front doivent
être listées explicitement — l'abonnement web est **authentifié par cookie**, un `*` interdirait donc
l'envoi des identifiants :
```bash
SERVER_NAME=':3000' \
MERCURE_PUBLISHER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' \
MERCURE_SUBSCRIBER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' \
MERCURE_EXTRA_DIRECTIVES='cors_origins http://127.0.0.1:8000 http://localhost:8000' \
./mercure run --config Caddyfile.dev
```
> Avec Docker, `backend/compose.yaml` fournit le même réglage et **publie le port hôte 3000**
> (identique à la stack `infra/`), ce qui rend `MERCURE_URL`/`MERCURE_PUBLIC_URL` prévisibles.
> Les valeurs par défaut de `backend/.env` pointent sur ce hub local : elles ne doivent **jamais**
> désigner un domaine tiers, l'URL du topic contenant l'identifiant de la famille (C11).

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
`mobile/assets/fonts/` (OFL), en **coupes statiques** (une par graisse, déclarées avec leur
`weight:` au `pubspec.yaml`) : Flutter ne pilote pas les axes d'une police variable depuis
`TextStyle.fontWeight`. Ces fichiers sont **commités** — construire l'application ne demande donc
rien de plus. Les **régénérer** (mise à jour d'une police, nouvelle graisse) demande `fonttools`,
outil de génération et non dépendance de build :

```bash
pip install fonttools && python3 tool/build_fonts.py   # depuis mobile/
```

Détail des coupes et de leurs usages : `mobile/assets/fonts/README.md`.

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
| `ACCESS_TOKEN_TTL` / `REFRESH_TOKEN_TTL` / `ONE_TIME_TOKEN_TTL` | durées de vie (s) ; `ACCESS_TOKEN_TTL` = tolérance d'inactivité du web (session glissante : chaque navigation ré-émet le JWT) | 3600 / 2592000 / 3600 |
| `COOKIE_SECURE` | force le flag Secure des cookies d'auth | `false` (dev) — **`true` en prod** |
| `TRUSTED_PROXIES` | proxies de confiance (CIDR) | vide — **à définir derrière un LB** |
| `RGPD_EXPORT_DIR` | répertoire des archives d'export RGPD (hors web-root) | `%kernel.project_dir%/var/exports` — **prod : stockage objet UE** |
| `RGPD_EXPORT_LINK_TTL` | TTL (s) du lien signé de téléchargement d'export | `3600` |
| `RGPD_ADMIN_STEP_UP_TTL` | TTL (s) du grant step-up admin technique (auth renforcée) | `900` |
| `RGPD_EU_HOSTING` | hébergement UE confirmé (reporté par le rapport de conformité) | `1` |
| `RGPD_RETENTION_*` | durées de rétention/purge (comptes inactifs, invitations, exports, journaux, notifications) | 1095 j / 30 j / 24 h / 1095 j / 90 j |
| `RL_RGPD_EXPORT_LIMIT` | exports RGPD max par utilisateur et par heure | `5` |
| `EXPENSE_REMINDER_COOLDOWN_HOURS` | délai minimal entre deux rappels **d'une même dépense** (module Dépenses, Lot 10) — anti-harcèlement, un dépassement répond 429 | `72` |
| `FCM_CREDENTIALS_FILE` | chemin du JSON du compte de service Firebase (push mobile, Lot 11) — **hors dépôt, hors web-root** ; vide = canal push éteint (§G) | vide |
| `RGPD_RETENTION_DEVICE_TOKENS_DAYS` / `RGPD_RETENTION_DORMANT_DEVICES_DAYS` | purge des jetons push révoqués/invalides, puis des jetons actifs restés muets (Lot 11) | `30` / `270` |

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

> ✅ **Échafaudé (Lot 9).** Fichiers livrés : `.github/workflows/build-ios.yml` (pipeline
> principal), `codemagic.yaml` (secours), `mobile/ios/fastlane/{Fastfile,Matchfile,Appfile}`,
> `mobile/ios/Gemfile`, `mobile/ios/ExportOptions.plist`. **Reste à faire une fois, hors dépôt**
> (action du porteur produit / mainteneur, cf. §E.1) : créer le compte Apple Developer + la clé
> API ASC, initialiser le dépôt de certificats `match`, et renseigner les secrets (§E.2) dans les
> deux CI. Tant que ces prérequis manquants ne sont pas fournis, chaque pipeline **échoue tôt et
> proprement** (message listant les secrets absents) plutôt que de produire un build non signé.

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
  job CI manuel dédié) par un mainteneur habilité. Le `Matchfile` est déjà commité
  (`mobile/ios/fastlane/Matchfile`) : il lit l'URL du dépôt dans `MATCH_GIT_URL`, la passphrase dans
  `MATCH_PASSWORD` et l'auth Git dans `MATCH_GIT_BASIC_AUTHORIZATION`. La génération initiale utilise
  la lane dédiée `certificates` (seule à écrire dans le dépôt, `readonly:false`) :
  ```bash
  cd mobile/ios
  bundle install                              # installe fastlane + cocoapods (mobile/ios/Gemfile)
  export MATCH_GIT_URL="git@…/certificats-ios.git"   # dépôt PRIVÉ dédié
  export MATCH_PASSWORD="…"                    # passphrase de chiffrement match
  export APPLE_TEAM_ID="…" ASC_KEY_ID="…" ASC_ISSUER_ID="…"
  export ASC_KEY_CONTENT="$(base64 -i ~/secrets/AuthKey_XXXX.p8)"   # .p8 HORS dépôt (cf. §E.2)
  bundle exec fastlane ios certificates       # génère + chiffre certificats/profils dans le dépôt
  ```
  > La génération/révocation de certificats reste **manuelle et locale** (lane `certificates`) : la
  > CI est **toujours `readonly`** (lanes `build`/`release`) — elle consomme les certificats, ne les
  > régénère jamais (invariant §6.3). Toute la logique vit dans `mobile/ios/fastlane/Fastfile` ; les
  > deux CI ne font que l'appeler (§E.5).

### E.2 Secrets à configurer — **dans les deux systèmes**

Mêmes valeurs des deux côtés (elles pointent vers le même dépôt `match` et le même compte ASC) :

| Secret | Rôle | GitHub Actions | Codemagic (groupe `ios_signing`) |
|---|---|---|---|
| `MATCH_GIT_URL` | URL du dépôt de certificats | Repository secret | variable de groupe |
| `MATCH_PASSWORD` | passphrase de déchiffrement `match` | Repository secret | variable de groupe (masquée) |
| `MATCH_GIT_TOKEN` | token d'accès (lecture seule) au dépôt de certificats | Repository secret | variable de groupe |
| `ASC_KEY_ID` / `ASC_ISSUER_ID` / `ASC_KEY_CONTENT` | clé API App Store Connect (upload TestFlight) | Repository secrets | variables de groupe |
| `APPLE_TEAM_ID` | identifiant d'équipe Apple | Repository secret | variable de groupe |

**Formats attendus** (identiques des deux côtés) :
- `ASC_KEY_CONTENT` = **contenu du fichier `.p8` encodé en base64** (`base64 -i ~/secrets/AuthKey_XXXX.p8`,
  depuis un emplacement **hors dépôt**). Le `Fastfile` le décode (`is_key_content_base64: true`). Ne
  jamais committer le `.p8` (le `.gitignore` racine ignore `*.p8`/`*.p12`/`*.cer`/`*.mobileprovision`
  par filet de sécurité, mais le bon réflexe reste de garder la clé hors de l'arbre suivi par git).
- `MATCH_GIT_TOKEN` = **token brut** (PAT en lecture seule sur le dépôt de certificats). Les deux
  pipelines en dérivent automatiquement `MATCH_GIT_BASIC_AUTHORIZATION`
  (`base64("x-access-token:$MATCH_GIT_TOKEN")`) écrit dans l'env du job — le token n'apparaît jamais
  dans les logs (secrets masqués par la CI). Alternative : `MATCH_GIT_URL` en HTTPS avec le token
  déjà inclus (déconseillé — le token transiterait dans l'URL).
- `MATCH_GIT_URL` = URL SSH ou HTTPS du **dépôt de certificats dédié** (jamais le dépôt applicatif).

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
  - automatiquement sur **push d'un tag `v*`** (déclenche la lane `release`).
  - Un job préalable `mobile-tests` (runner Linux : `flutter test` + `dart analyze` + drift OpenAPI)
    **conditionne** le build signé (§3.4) : pas d'`.ipa` si les tests Flutter échouent.
- **Codemagic** — workflow `ios-build` (`codemagic.yaml`) : **build manuel depuis le dashboard**
  Codemagic ; un second workflow `ios-release` réalise l'upload TestFlight.

> **Jamais d'upload TestFlight automatique sur simple commit** (sur aucune des deux CI). La lane
> `release` / le workflow `ios-release` sont **explicites**. La soumission à revue App Store reste
> **manuelle** (hors CI).

### E.4 Récupérer le build

- **GitHub Actions** : onglet **Artifacts** du run → `.ipa` téléchargeable (conservé N jours ;
  nommage incluant le commit SHA / tag).
- **Codemagic** : section **Artifacts** du build (`mobile/build/ios/ipa/*.ipa`).
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
> le dev backend/web) : **Ruby 3.3 + Bundler**, **Fastlane `~> 2.226`** et **CocoaPods `~> 1.16`**
> (bornées dans `mobile/ios/Gemfile` ; committer `mobile/ios/Gemfile.lock` depuis un poste macOS fige
> complètement la résolution). `pod install` n'est **pas** une étape CI séparée : le `Fastfile` le
> déclenche via `flutter build ios --no-codesign` (le projet ne committe pas de `Podfile`, généré par
> le tool Flutter au 1er build). La version **Flutter est figée à `3.38.9`**, identique au Lot 0.

---

## F. Signature release Android

> Ajouté par la **revue mobile 2026-08** (constat C73). Le projet signait ses builds release avec la
> clé de **debug** — clé publiquement connue, donc n'importe qui pouvait produire une mise à jour
> acceptée par l'appareil. Le `buildTypes.release` ne référence plus cette clé : sans configuration,
> l'APK/AAB sort **non signé** (échec visible) plutôt que faussement signé.

Même principe que Fastlane match côté iOS (§E) : **aucune clé, aucun mot de passe au dépôt**
(`android/key.properties`, `*.jks` et `*.keystore` sont git-ignorés).

### F.1 Créer le keystore (une fois, mainteneur)

```bash
keytool -genkey -v -keystore ~/coparentplanner-release.jks \
  -keyalg RSA -keysize 4096 -validity 10000 -alias coparentplanner
```

Le sauvegarder **hors du dépôt** et hors de la machine de dev (perdre ce fichier interdit toute mise
à jour de l'app publiée ; il n'existe aucun recours côté Google Play hors procédure Play App Signing).

### F.2 Build local

Créer `mobile/android/key.properties` :

```properties
storeFile=/chemin/absolu/vers/coparentplanner-release.jks
storePassword=…
keyAlias=coparentplanner
keyPassword=…
```

```bash
cd mobile
flutter build appbundle --release --dart-define=API_BASE_URL=https://api.example.tld
```

L'URL d'API **doit** être passée en `--dart-define` : la valeur par défaut
(`http://10.0.2.2:8000`, alias de la machine de dev depuis l'émulateur) est en clair et sera de
toute façon bloquée par la politique de trafic Android en release.

Pour un build qui reçoit les notifications push (Lot 11), ajouter les quatre identifiants Firebase
Android de §G.3 (`FIREBASE_PROJECT_ID`, `FIREBASE_SENDER_ID`, `FIREBASE_ANDROID_APP_ID`,
`FIREBASE_ANDROID_API_KEY`) en `--dart-define` ; sans eux, le push de ce build est éteint.

### F.3 Build en CI

Le `build.gradle.kts` accepte les mêmes valeurs par variables d'environnement, ce qui évite d'écrire
un fichier de secrets sur le runner :

| Variable | Contenu |
|---|---|
| `ANDROID_KEYSTORE_PATH` | chemin du `.jks` décodé sur le runner |
| `ANDROID_KEYSTORE_PASSWORD` | mot de passe du keystore |
| `ANDROID_KEY_ALIAS` | alias de la clé |
| `ANDROID_KEY_PASSWORD` | mot de passe de la clé |

Le keystore lui-même se transporte en secret **base64** (`base64 -w0 coparentplanner-release.jks`),
décodé au début du job vers un chemin temporaire — jamais committé, jamais loggué.

### F.4 Ce que le manifeste garantit désormais

- `<uses-permission android:name="android.permission.INTERNET"/>` est déclarée dans
  `src/main/AndroidManifest.xml` — les manifestes `debug`/`profile` générés par Flutter ne sont
  **pas** fusionnés en release, d'où une app release sans réseau avant ce correctif (C4/C63).
- `android:allowBackup="false"` — la base locale (outbox + cache de lecture, en clair) et le magasin
  de jetons ne partent plus dans la sauvegarde cloud de l'utilisateur (C6).

---

## G. Notifications push mobile (Lot 11)

Le push passe **entièrement par Firebase Cloud Messaging** (arbitrage du 2026-09-23) : FCM livre
les appareils Android et **relaie vers APNs** les appareils iOS. Le backend ne détient donc qu'un
identifiant — le compte de service Firebase — et aucune clé APNs.

**Tout est facultatif** : sans configuration, le backend n'enfile aucun push (l'e-mail n'est pas
affecté), et l'app affiche « notifications push indisponibles » dans ses réglages. La CI tourne sur
une passerelle factice (`App\Tests\Support\FakePushSender`) : aucun appel à Google en test.

### G.1 Projet Firebase (une fois, mainteneur)

1. Créer un projet sur <https://console.firebase.google.com> (région de données : UE si proposée)
   et y déclarer **deux applications** :
   - Android, package `eu.coparentplanner.app` ;
   - iOS, bundle `eu.coparentplanner.app`.
   Télécharger `google-services.json` / `GoogleService-Info.plist` seulement pour y lire les
   clés API (§G.3) : ils **ne sont pas committés**, l'app reçoit ses identifiants au build.
2. **iOS — clé APNs** : dans le portail Apple Developer, *Keys* → créer une clé avec le service
   *Apple Push Notifications service (APNs)* (fichier `.p8`, téléchargeable une seule fois). La
   déposer dans Firebase : *Paramètres du projet → Cloud Messaging → Configuration des applications
   Apple → Clé d'authentification APNs* (avec le Key ID et le Team ID). C'est Firebase, pas notre
   backend, qui la détient.
3. **iOS — capacité Push de l'App ID** : *Identifiers* → `eu.coparentplanner.app` →
   cocher **Push Notifications**, puis **régénérer les profils match** (le profil doit porter
   l'entitlement `aps-environment` que déclare `mobile/ios/Runner/Runner.entitlements`) :
   ```bash
   cd mobile/ios && bundle exec fastlane ios certificates   # §E.1 ; ajouter force: true si besoin
   ```
   Sans cette étape, l'export gym échoue sur un profil sans capacité Push.

### G.2 Backend : compte de service

*Paramètres du projet → Comptes de service → Générer une nouvelle clé privée* → fichier JSON. Le
déposer **hors du dépôt et hors web-root**, lisible du seul utilisateur PHP, puis :

```bash
# .env.local (bare-metal) ou variable d'environnement du conteneur (Docker) :
FCM_CREDENTIALS_FILE=/etc/coparentplanner/fcm-service-account.json
chmod 600 /etc/coparentplanner/fcm-service-account.json
```

En Docker, monter le fichier en lecture seule (secret compose ou volume `:ro`) et pointer
`FCM_CREDENTIALS_FILE` dessus. Aucune dépendance PHP supplémentaire : l'OAuth 2 (assertion JWT
RS256) et l'API HTTP v1 passent par `symfony/http-client` et l'extension `openssl`, déjà requis.
Les envois partent du worker Messenger (`App\Message\SendPushNotification` sur `async`) : **le
worker doit tourner** pour que le push parte.

Rotation : générer une nouvelle clé, remplacer le fichier, redémarrer le worker, puis révoquer
l'ancienne clé dans la console. Un identifiant refusé ne supprime **jamais** les jetons des
utilisateurs : les envois sont rejoués puis parqués dans la file `failed`
(`php bin/console messenger:failed:retry` une fois la clé corrigée).

### G.3 Mobile : identifiants publics au build

Les identifiants de l'app Firebase (*Paramètres du projet → Vos applications*) sont **publics** —
ils finissent dans le binaire. Ils passent en `--dart-define` plutôt qu'en fichiers committés :

| Variable | Où la lire |
|---|---|
| `FIREBASE_PROJECT_ID` | console : ID du projet |
| `FIREBASE_SENDER_ID` | console : Cloud Messaging → ID de l'expéditeur |
| `FIREBASE_ANDROID_APP_ID` | console : app Android → ID d'application (`1:…:android:…`) |
| `FIREBASE_ANDROID_API_KEY` | `google-services.json` → `client[].api_key[].current_key` |
| `FIREBASE_IOS_APP_ID` | console : app iOS → ID d'application (`1:…:ios:…`) |
| `FIREBASE_IOS_API_KEY` | `GoogleService-Info.plist` → `API_KEY` |

> Firebase crée **une clé API par application**, restreinte à son package ou à son bundle : ne pas
> réutiliser la clé Android pour iOS (ni la « clé API Web » du projet). Les deux fichiers de
> configuration servent seulement à lire ces valeurs ; ils restent hors dépôt.

```bash
cd mobile
flutter run \
  --dart-define=API_BASE_URL=http://10.0.2.2:8000 \
  --dart-define=FIREBASE_PROJECT_ID=… --dart-define=FIREBASE_SENDER_ID=… \
  --dart-define=FIREBASE_ANDROID_APP_ID=… --dart-define=FIREBASE_ANDROID_API_KEY=…
```

- **CI iOS** : déclarer `FIREBASE_PROJECT_ID`, `FIREBASE_SENDER_ID`, `FIREBASE_IOS_APP_ID`,
  `FIREBASE_IOS_API_KEY` (et `API_BASE_URL`) comme **variables de dépôt** GitHub (`vars`, pas
  `secrets`) et dans le groupe `ios_signing` de Codemagic ; le `Fastfile` les relaie en
  `--dart-define` (§E). Absentes, le build reste valide, push éteint.
- **Plateformes** : iOS **15.0** minimum (exigence de `firebase_messaging`) ; Android 7.0 (API 24,
  défaut Flutter). La permission Android 13+ `POST_NOTIFICATIONS` est fusionnée depuis le plugin.
- **Nouvelles dépendances Flutter** : `firebase_core`, `firebase_messaging`, `device_info_plus`,
  `package_info_plus` (`flutter pub get`). Aucune n'a de hook natif qui gêne `build_runner`.

### G.4 Valider sur appareil

- **Android** : un émulateur *avec Google Play* suffit.
- **iOS** : **impossible sur simulateur** (pas de jeton APNs) — build signé installé via TestFlight
  (§E.3), sur un iPhone réel. C'est le prérequis mainteneur qui conditionne la validation iOS du lot.

Parcours de recette : se connecter → Réglages › Notifications → activer « Échanges » (l'invite
système s'affiche à ce moment-là, pas au lancement) → depuis un autre compte de la famille, écrire
dans un échange → la notification arrive app fermée, et le tap ouvre le fil. Couper la permission
dans les réglages du téléphone puis revenir dans l'app : l'appareil disparaît de la liste
« Appareils » (révocation serveur).

