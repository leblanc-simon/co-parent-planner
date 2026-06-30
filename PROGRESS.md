# Avancement d'implémentation — CoParentPlanner

> **Document de reprise.** Mis à jour régulièrement pour reprendre le travail facilement entre
> sessions. Source de vérité fonctionnelle : `specs/cdc.md` + `specs/lots/`. Invariants : `CLAUDE.md`.
> Maquettes (rendu visuel fait foi) : `specs/design/` (`CoParentPlanner Desktop.dc.html`, variante
> mobile, `ios-frame.jsx`, `screenshots/`).

Dernière mise à jour : **2026-06-30** — **Lot 1 TERMINÉ** (familles/membres/droits/invitations/liens) : backend + web + mobile + E2E.

---

## 0. Où en est-on ?

**Lot 0 — Fondations** : ✅ **TERMINÉ**.

**Lot 1 — Familles/membres/droits/invitations/liens** (`specs/lots/lot-1-*.md`) : ✅ **TERMINÉ** (backend, contrat OpenAPI, client Dart, UI web, UI mobile, E2E).
Prochaine étape : **Lot 2 — Enfants & notes** (`specs/lots/lot-2-*.md`).

- **Backend** : **101 tests / 748 assertions**, couverture **88,4 %** (≥80 %), PHPStan niv.8, cs-fixer verts, schéma en sync.
- **Mobile** : client régénéré depuis l'OpenAPI Lot 1 (sans drift), **108 tests** verts, couverture **81 %** (≥70 %), `dart analyze`/`format` propres.
- **E2E web (Playwright)** : **5 tests verts** (Lot 0 auth + responsive + **Lot 1 : créer famille → réglages → inviter → accepter (Mailpit) → vérifier accès → ajuster droits**).
- **UI web** : `/settings` (Paramètres › Compte & Famille) — réglages famille (admin), membres + matrice de droits, invitation + récap droits, invitations en attente + révocation, familles liées (créer/activer/délier) ; sélecteur de famille persisté (cookie `cpp_family`) ; page web `/invitations/accept`.
- **UI mobile** : écran Membres (liste + invitations en attente + révocation), formulaire d'invitation (rôle + droits), bascule de famille active (→ X-Family-Id), branchés dans l'onglet Réglages.
- **Correctif Lot 0** : `data-controller="auth-form"` était sur la carte d'onboarding (et non le `<form>`) → création de famille via le front cassée ; déplacé sur le formulaire.

### Surface backend Lot 1 livrée
- `PATCH /api/families/{id}` (réglages, `family_settings:edit`), réglages garde **persistés** (settings jsonb), non interprétés.
- `GET /api/families/{id}/members` (matrice de droits effectifs, identité 🔒 déchiffrée).
- `PATCH /api/memberships/{id}` (rôle/admin/droits/couleur ; invariant `is_admin⇒parent` 422 ; dernier admin 409 ; couleur self sans droit), `DELETE /api/memberships/{id}` (retrait/auto-départ).
- `POST|GET /api/families/{id}/invitations`, `DELETE /api/invitations/{id}`, `POST /api/invitations/accept` durci (expiré 410, déjà-membre 409, mismatch 403, concurrence FOR UPDATE, droits normalisés, admin honoré si parent). E-mail d'invitation async (Messenger + Twig localisé). Purge planifiée (Scheduler).
- `GET|POST /api/families/{id}/links`, `POST .../links/{linkId}/activate`, `DELETE .../links/{linkId}` (paire canonique unique, anti-auto-lien, **sans overlay**).
- Scope `X-Family-Id` durci : cohérence path/header (`/errors/family-scope-mismatch`), `FamilyScope` (service partagé providers/processors) + `FamilyContextResolver` (contrôleurs).
- Services : `RightsNormalizer`, `MembershipWriter` (invariant §3.7), `LastAdminGuard` (FOR UPDATE), `BlindIndexHasher` (email_hash).
- Modèle : entité **FamilyLink** + enum `FamilyLinkStatus` ; `Invitation.emailHash` (blind index) + `proposedAdmin` ; CHECK `is_admin=false OR role='parent'` ; index unique partiel `pending` ; migration `Version20260630193850`.
- Exceptions RFC 7807 déclaratives (`ProblemException` + `AbstractProblemException`) ; registre RGPD à jour (`Invitation.emailHash`) ; OpenAPI exporté + client Dart régénéré.

- **Backend** : **68 tests / 428 assertions**, couverture **86,9 %** (≥ seuil D8 80 %), PHPStan niv.8, cs-fixer verts.
- **Mobile** : **105 tests**, couverture **84,3 %** (≥ seuil D8 70 %), `dart analyze`/`format` propres, client OpenAPI sans drift.
- **E2E web (Playwright)** : **4 tests verts** (inscription → vérif e-mail Mailpit → connexion ; identifiants invalides ; responsive desktop+mobile).
- **CI** : `.github/workflows/ci.yml` (jobs backend + web-e2e + mobile), garde-fous de couverture + drift OpenAPI/client.

---

## 1. Environnement vérifié (poste de dev)

| Outil | Version | | Outil | Version |
|---|---|---|---|---|
| PHP | 8.5.7 (NTS) | | Symfony (framework) | **8.1.0 stable** |
| Composer | 2.10.1 | | API Platform | 4.3.14 |
| Symfony CLI | 5.17.1 | | lexik/jwt | 3.2.0 |
| Node / npm | 25.9.0 / 11.12.1 | | Mercure / bundle | 0.7.2 / 0.4.2 |
| Docker / Compose | 29.5.3 / v5.1.4 | | Flutter / Dart | 3.38.9 / 3.10.8 |
| PostgreSQL (client) | 16.14 | | git | 2.43.0 |

Extensions PHP présentes : pdo_pgsql, pgsql, sodium, openssl, intl, redis, apcu, mbstring, zip.

---

## 2. Décisions tranchées (Lot 0 §0.5) — à respecter

- **D1** Aucun token avant vérification e-mail. Login d'un compte `pending` → 403.
- **D2** Web en Bearer JWT mais cookies `HttpOnly/Secure/SameSite=Lax` ; refresh sur cookie dédié ; pas de session PHP ; CSRF sur formulaires web.
- **D3** Refresh strict (pas de grâce) ; réutilisation → invalidation de la famille de tokens.
- **D4** `Membership.child_id` = UUID nullable SANS FK au Lot 0 (FK au Lot 2). ✅ implémenté ainsi.
- **D5** Lien e-mail = page web `GET /verify-email?token=…` → `POST /api/auth/verify-email` ; deep link mobile `coparentplanner://verify-email?token=…`. Idem reset.
- **D6** État mobile : **Riverpod**.
- **D7** Endpoints non implémentés mais dans l'OpenAPI → **501** (jamais 404).
- **D8** Couverture : backend ≥ 80 %, mobile ≥ 70 %.

---

## 3. Suivi des tâches Lot 0

| # | Tâche | Statut | Vérifié |
|---|---|---|---|
| 1 | Monorepo skeleton + PROGRESS.md | ✅ | — |
| 2 | Backend Symfony 8.1 + bundles (API Platform, JWT, Mercure, rate-limiter, PHPStan, php-cs-fixer, Foundry…) | ✅ | kernel boot OK |
| 4 | Entités + enums + migration | ✅ | `schema:validate` en sync |
| 5 | Chiffrement AES-256-GCM des champs 🔒 | ✅ | 7 tests unitaires |
| 7 | Voters par domaine + scope X-Family-Id | ✅ | 6 Voters + `FamilyContextResolver` ; RBAC + resolver unit-testés (header requis / UUID / membre / anti-énum) |
| 9 | Mercure + /api/realtime/token (claims vides) | ✅ | JWT Mercure scopé famille, `subscribe` vide au Lot 0, recalcul à la bascule ; 3 tests fonctionnels |
| 3 | Infra docker-compose dev + INSTALL.md | ✅ | stack complète (app FrankenPHP + worker + scheduler + PG + Redis + Mercure + Mailpit) **vérifiée** ; `INSTALL.md` Docker+bare-metal ; Scheduler + job de santé |
| 6 | Auth HTTP + compte + onboarding | ✅ | 7 endpoints auth + `/api/me` + `/api/families` + `/api/invitations/accept` + endpoints 501 RGPD ; audit sécu passé |
| 8 | i18n fr-FR ICU + Mailer localisé + Messenger async | ✅ | catalogue ICU, 3 e-mails Twig localisés, envoi async (cœur Lot 0 ; prefs notif détaillées = Lot 7) |
| 10 | Squelette RGPD + registre-donnees + test cohérence CI | ✅ | registre + test cohérence + attribut PersonalData + endpoints 501 (D7) ; RGPD Manager complet = Lot 8 |
| 11 | Front web : shell + design system + écrans auth | ✅ | tokens §8, shell sidebar responsive + sélecteur famille, écrans auth (login/register/forgot/reset/verify) + onboarding + accueil ; auth par cookie JWT (D2) testée. Polish visuel fin vs maquette = ultérieur |
| 12 | App mobile Flutter : squelette + client généré + auth | ✅ | projet iOS+Android, client **dio/retrofit généré depuis l'OpenAPI** (swagger_parser, pur Dart), Riverpod, Drift (outbox), deep links, i18n ARB, thème §8 ; **105 tests, couverture 84,3 %** |
| 13 | Harnais tests + CI + INSTALL.md | ✅ | **CI** `.github/workflows/ci.yml` (backend+web-e2e+mobile). **Backend** : 68 tests, couverture **86,9 %** (garde-fou `bin/coverage-gate.php`). **E2E** Playwright `/web-e2e` (4 tests : inscription→vérif Mailpit→connexion + responsive). **Mobile** : 105 tests, 84,3 %, drift client vérifié. INSTALL.md à jour |

**Portes qualité actuellement vertes** (toutes encodées dans `.github/workflows/ci.yml`)
- **Backend** : `phpunit` (**68 tests / 428 assertions**), couverture **86,9 %** (≥80 %, `php bin/coverage-gate.php`), `phpstan` (niveau 8, 0), `php-cs-fixer --dry-run` (0), drift OpenAPI (`api:openapi:export` + `git diff`).
- **Mobile** (`/mobile`) : `flutter test` (**105 tests**), couverture **84,3 %** (≥70 %, `python3 tool/check_coverage.py`), `dart analyze` (0), `dart format` (0), drift client (`git diff lib/api/generated`).
- **E2E web** (`/web-e2e`, Playwright) : **4 tests verts** ; lancement local : `web-e2e/scripts/e2e-local.sh` (DB + Mailpit + `symfony serve` avec `async`→`sync://`).

> ⚠️ **Le conteneur DB doit tourner** pour les tests fonctionnels : `cd backend && docker compose up -d database` (il n'est pas auto-démarré ; sans lui, `symfony` retombe sur un PostgreSQL local 5432 et l'auth échoue).

Légende : ⬜ à faire · 🟡 en cours · ✅ fait

---

## 4. Ce qui est en place (détail backend)

```
backend/src/
  Attribute/PersonalData.php            # registre RGPD déclaratif (cdc §7.1)
  Doctrine/Type/{CitextType,EncryptedStringType}.php
  Enum/                                 # 14 enums backed (FamilyType, MembershipRole, RightDomain/Level, …)
  Entity/                               # User, RefreshToken, OneTimeToken, Family, Membership, Right,
                                        #   Invitation, NotificationPref, AuditLog(append-only), RgpdOperation
  Entity/Concern/TimestampableTrait.php # created/updated via Symfony Clock (UTC)
  Repository/                           # User, Family, Membership, RefreshToken, OneTimeToken, Invitation, AuditLog
  Security/Encryption/FieldEncryptor.php
  Security/FamilyContext.php + FamilyContextResolver.php   # scope X-Family-Id (§3.6)
  Security/Voter/                       # AbstractDomainVoter + 6 Voters (un par domaine)
  Exception/Family{ScopeRequired,AccessDenied}Exception.php
backend/tests/Unit/Security/            # FieldEncryptorTest (7), RbacTruthTableTest (5)
backend/migrations/Version20260623202428.php   # schéma initial + extension citext
```

Config clé : `config/packages/doctrine.yaml` (types citext/encrypted_string), `framework.yaml`
(default_locale fr, enabled_locales [fr]), `services.yaml` (FieldEncryptor ← env base64),
`.env` (APP_ENCRYPTION_KEY dev, TTL tokens, MAILER→Mailpit).

**Points d'attention / dette connue :**
- `Invitation.invitedBy` pointe vers `User` (au lieu de Membership) — à reconsidérer au Lot 1.
- Couche offline mobile, PWA, RGPD complet = lots ultérieurs (8) — hors Lot 0.

**Audit sécurité du module auth (2026-06-24)** — risque résiduel faible après corrections :
- ✅ Corrigés : `trusted_proxies` (IP fiable pour le rate-limit), atomicité `ResetPasswordProcessor`
  (transaction), **jeton OTT chiffré en transit** dans Messenger, validation `locale`, forgot limité
  aux comptes actifs, flag cookie `Secure` forçable (`COOKIE_SECURE`).
- ⏸️ Conservés (décisions assumées) : login `403` si e-mail non vérifié = **imposé par D1/§3.2**
  (UX > anti-énumération stricte) ; secrets dans `.env` = **valeurs dev-only** (prod via vault, cdc §4.8) ;
  `lock_factory: null` des rate-limiters = OK mono-instance, **à passer en Redis pour la prod multi-instance**
  (à documenter dans INSTALL.md) ; rate-limit sur verify-email/reset non ajouté (jetons 256 bits non brute-forçables).

---

## 5. Comment reprendre

1. Lire ce fichier (§3 statuts, §4 état, §6 journal) puis `specs/lots/lot-0-fondations.md`.
2. Démarrer la base : `cd backend && docker compose up -d database` (+ `mercure mailer` au besoin).
3. Vérifier : `cd backend && symfony console doctrine:schema:validate && php bin/phpunit tests/Unit`.
4. Reprendre la 1ʳᵉ tâche non ✅ du §3. **Prochaine priorité : Tâche 6 (auth HTTP)** —
   c'est le livrable phare du Lot 0 ; elle débloque le test fonctionnel du scope (Tâche 7) et le front.
5. À chaque jalon : MAJ §3 (statut) + §6 (journal) + date en tête.

### Commandes réelles (validées)
```bash
# Base de données (port hôte auto-mappé, détecté par le binaire symfony)
cd backend && docker compose up -d database

# Console Symfony avec env Docker auto-câblé (DATABASE_URL, etc.)
symfony console <commande>            # ex: doctrine:migrations:migrate -n
symfony console doctrine:schema:validate

# Tests
symfony php bin/phpunit                # TOUT (passe par l'env Docker → bon port DB). 19 tests verts.
php bin/phpunit tests/Unit             # unitaires/RGPD seuls (sans DB)
# Base de test (une fois) :
symfony console --env=test doctrine:database:create --if-not-exists
symfony console --env=test doctrine:migrations:migrate -n

# Clés JWT (déjà générées dans config/jwt/, non committées)
php bin/console lexik:jwt:generate-keypair --overwrite -n
```
# Qualité (verts)
vendor/bin/phpstan analyse --memory-limit=1G          # niveau 8, 0 erreur
PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run --diff
php bin/console lint:container
```

### Mobile (`/mobile`) — commandes réelles (validées)
```bash
cd mobile && flutter pub get
# Regenerer le client depuis l'OpenAPI (apres un export backend) :
dart run swagger_parser                                   # OpenAPI -> sources retrofit/dio
dart run build_runner build --delete-conflicting-outputs --force-jit
#   ^ --force-jit OBLIGATOIRE : des deps (sqlite3, path_provider_foundation) ont un
#     hook/build.dart natif qui casse l'AOT du script build_runner sous Dart 3.10.
flutter gen-l10n                                          # ARB -> AppL10n (si chaines i18n modifiees)
# Qualite & tests :
dart analyze                                              # 0 issue (exclut lib/api/generated)
dart format --output=none --set-exit-if-changed lib/core lib/features lib/routing lib/app.dart lib/main.dart test
flutter test --coverage                                   # 105 tests ; couverture 84,3% (hors genere)
```
### E2E web (`/web-e2e`, Playwright) — commandes réelles (validées)
```bash
cd web-e2e && npm install && npx playwright install chromium
# Stack + tests en une commande (DB conteneur + Mailpit + symfony serve, async->sync://) :
web-e2e/scripts/e2e-local.sh                  # 4 tests verts (desktop + mobile)
web-e2e/scripts/e2e-local.sh --project=desktop  # seulement le parcours desktop
```

(Lancer l'app mobile sur un device : `flutter run` ; l'émulateur Android utilise
`API_BASE_URL=http://10.0.2.2:8000`, surchargeable par `--dart-define`.)

---

## 6. Journal (du plus récent au plus ancien)

### 2026-06-30 — Lot 1 : familles, membres, droits, invitations, liens (clos)
- **Modèle** : entité `FamilyLink` (+ `FamilyLinkStatus`, paire canonique unique, CHECK anti-auto-lien) ;
  `Invitation.emailHash` (blind index HMAC) + `proposedAdmin` ; CHECK `is_admin=false OR role='parent'` ;
  index unique partiel `pending` ; migration `Version20260630193850`.
- **Services** : `FamilyScope` (scope X-Family-Id + cohérence path/header §3.4, partagé providers/processors),
  `RightsNormalizer` (§3.1), `MembershipWriter` (invariant `is_admin⇒parent` §3.7), `LastAdminGuard`
  (FOR UPDATE §3.2), `BlindIndexHasher`. Exceptions RFC 7807 déclaratives (`ProblemException`).
- **API** : réglages famille (`PATCH /families/{id}`), membres (`GET /families/{id}/members`,
  `PATCH`/`DELETE /memberships/{id}`), invitations (`POST`/`GET /families/{id}/invitations`,
  `DELETE /invitations/{id}`, `POST /invitations/accept` durci : expiré 410, déjà-membre 409, mismatch 403,
  concurrence FOR UPDATE), liens (`GET`/`POST /families/{id}/links`, `.../activate`, `DELETE .../{linkId}`).
  E-mail d'invitation async (Messenger + Twig localisé), purge planifiée (Scheduler).
- **Web** : page `/settings` (Compte & Famille) — réglages, membres + matrice de droits, invitation +
  récap, invitations en attente, familles liées ; sélecteur de famille persisté (cookie) ;
  `/invitations/accept`. Stimulus `settings` + `family-select`. Correctif onboarding (`auth-form`).
- **Mobile** : clients Member/Invitation/FamilyLink, `MembersRepository`, écran Membres + formulaire
  d'invitation + bascule de famille active (→ X-Family-Id). **108 tests, 81 %**.
- **Contrat** : OpenAPI ré-exporté, client Dart régénéré (sans drift). Registre RGPD : `Invitation.emailHash`.
- **Portes vertes** : backend **101 tests / 748 assertions, couverture 88,4 %**, PHPStan niv.8, cs-fixer ;
  mobile `dart analyze`/`format` ; **E2E Playwright 5/5** (dont le parcours Lot 1 complet via Mailpit).

### 2026-06-25 — CI + E2E web Playwright + couverture backend (Tâche 13 → Lot 0 clos)
- **CI GitHub Actions** `.github/workflows/ci.yml`, 3 jobs :
  - `backend` : services PostgreSQL 16 ; composer, clés JWT, php-cs-fixer, PHPStan niv.8, base de
    test + migrations, PHPUnit + **couverture pcov** + garde-fou `bin/coverage-gate.php` (≥80 %),
    **drift OpenAPI** (`api:openapi:export` + `git diff`).
  - `web-e2e` : services PostgreSQL + **Mailpit** ; serveur PHP intégré (transport `async`→`sync://`
    pour envoyer l'e-mail sans worker), Playwright (`npm test`), upload du rapport si échec.
  - `mobile` : Flutter ; **régénération du client + drift** (`swagger_parser` + `build_runner --force-jit`
    + `git diff lib/api/generated`), `dart format`/`analyze`, `flutter test --coverage` + garde-fou
    `tool/check_coverage.py` (≥70 %).
- **E2E web Playwright** dans `/web-e2e` : parcours **inscription → vérification e-mail (Mailpit) →
  connexion** (lit le lien dans l'API Mailpit), + identifiants invalides non énumérant, + smoke
  **responsive desktop & mobile**. **4 tests verts en local** via `web-e2e/scripts/e2e-local.sh`
  (DB conteneur + Mailpit + `symfony serve`, `async`→`sync://`). Navigateur installé via `npx playwright install`.
- **Couverture backend remontée 67,3 % → 86,9 %** (≥ seuil D8 80 %) : tests unitaires entités
  (round-trips getters/setters, append-only AuditLog) + `SendAuthEmailHandler` (lien e-mail), et
  fonctionnels **forgot/reset/logout** (révocation des refresh) + `GET /api/families/{id}` (200/404).
  **Backend : 68 tests / 428 assertions, PHPStan niv.8, cs-fixer verts.**
- Garde-fous de couverture réutilisables : `backend/bin/coverage-gate.php`, `mobile/tool/check_coverage.py`.
- Docs : `PROGRESS.md`, `CLAUDE.md`, `infra/INSTALL.md`, `contracts/README.md` à jour.

### 2026-06-25 — App mobile Flutter : squelette + client OpenAPI + auth (Tâche 12)
- **Projet Flutter** `/mobile` (iOS + Android), package `coparentplanner`, Dart 3.10 / Flutter 3.38.9.
- **Client HTTP généré depuis l'OpenAPI** — `swagger_parser` (**pur Dart, pas de Java/openapi-generator**)
  → clients **retrofit sur dio** + modèles `json_serializable`, dans `lib/api/generated/` (commité, non édité).
  Régénération : `dart run swagger_parser && dart run build_runner build --force-jit`. **Idempotente, drift détectable**.
- **Correctif backend lié** : `Dto/Family/CreateFamilyRequest` (`name`/`type` rendus nullables + `NotBlank`)
  — un `default: ""` exposé dans l'OpenAPI cassait la génération de l'enum côté Dart. OpenAPI ré-exporté, PHPStan/cs-fixer/test familles verts.
- **Pile réseau dio** (intercepteurs, ordre) : `Accept-Language` → `X-Family-Id` (scope §3.6, fige le header outbox)
  → `Idempotency-Key` (outbox §3.11) → **Auth Bearer + refresh rotatif single-flight** (D3, `QueuedInterceptor` + détection de token déjà rafraîchi) → retry idempotent/backoff.
- **État Riverpod** (D6) : `authController`/`AuthState` (sealed : Unauthenticated/PendingVerification/Authenticated),
  `familyContext` (X-Family-Id actif, persisté), `localeController` (négociation §3.5), providers réseau/storage.
- **Offline (squelette §3.10)** : Drift (`outbox_entry` + DAO + machine d'état pending/sending/done/conflict/failed),
  `Idempotency-Key` UUID, rejeu FIFO au retour en ligne (`connectivity_plus`). Stockage tokens : `flutter_secure_storage`.
- **Routing** go_router : guard `computeRedirect` (auth/famille), shell `StatefulShellRoute` à 4 onglets ;
  **deep links** `app_links` (`coparentplanner://verify-email|reset-password?token=…`, D5) câblés natif (AndroidManifest + Info.plist).
- **Écrans** : auth (login, register, check-email, forgot, reset, verify), onboarding (créer/rejoindre/famille),
  shell (accueil états vides — encart garde masqué si famille unie, agenda/enfants placeholders, réglages profil/langue/logout).
- **Thème §8** : tokens couleurs/typo (Bricolage+Hanken **auto-hébergées** OFL), `ThemeData` explicite, helper « liquid glass » iOS.
- **i18n** : `flutter gen-l10n` (ARB `app_fr.arb` → `AppL10n`), fr-FR livrée, fallback sans changement de code.
- **Tests** : **105 verts** (intercepteurs single-flight, outbox, ApiException RFC7807, auth/onboarding/family
  repos+controllers, redirect guard, écrans widget, intégration shell). **Couverture 84,3 %** (≥ seuil D8 70 %).
- **Dépendances figées** (Dart 3.10 / éviter les `hook/build.dart` qui cassent build_runner) :
  `flutter_riverpod 2.6.1` (la 3.x tire `test` en runtime → conflit analyzer), `sqlite3 <3.2`, `flutter_secure_storage 9.x`,
  `drift_dev 2.34.0`, build_runner lancé en **`--force-jit`** (les hooks natifs cassent l'AOT du build script).

### 2026-06-24 — Front web : design system + shell + écrans d'auth (Tâche 11)
- **Design system §8** dans `assets/styles/app.css` : tokens (couleurs/typos Bricolage+Hanken/formes),
  composants (cartes, pastilles, boutons, formulaires, sidebar, sélecteur famille), responsive.
- **Shell** (`app/shell.html.twig`) : sidebar sombre 252px + zone principale, repliable sous breakpoint ;
  **accueil** (US-HOME-1) avec sélecteur de famille (Stimulus) + encarts en état vide (encart garde
  masqué si famille unie) ; **onboarding** (créer 1re famille → POST /api/families).
- **Écrans d'auth** (login, register, forgot, reset, verify — D5) : formulaires postant en JSON à
  l'API via le contrôleur Stimulus `auth-form` ; cookies HttpOnly posés par le serveur (D2).
- **Contrôleurs** `HomeController` (/, /onboarding) + `SecurityController` (pages auth + logout
  révoquant le refresh) ; firewall web `main` (JWT en cookie).
- **WebPagesTest** (4 tests) : rendu des pages, redirections login/onboarding, **accueil authentifié
  via cookie JWT** vérifié. Suite : **40 tests / 184 assertions**, PHPStan niv.8, cs-fixer verts.

### 2026-06-24 — Infra Docker complète + INSTALL.md + Scheduler (Tâche 3)
- **`/infra/docker-compose.yml`** : app FrankenPHP + worker Messenger + scheduler + PostgreSQL 16 +
  Redis + Mercure + Mailpit. `backend/Dockerfile` (FrankenPHP, extensions), `entrypoint.sh`
  (attente DB, clés JWT, migrations), `.dockerignore`.
- **Vérifié end-to-end** (image construite + lancée) : boot, migrations, `/api/docs.json` → 200,
  `POST /api/auth/register` → 202. (Sur cette machine, ports 5432/1025/8025 pris par d'autres stacks ⇒
  utiliser des ports libres ou arrêter les autres pour `docker compose up`.)
- **Scheduler** (cdc §0.2) : `symfony/scheduler` + `src/Schedule.php` (stateful) avec un job de
  santé `HealthPing` (horaire) ; consommé par `messenger:consume scheduler_default`.
- **`/infra/INSTALL.md`** : guide Docker **et** bare-metal pas à pas + table des variables d'env.
- `CLAUDE.md` § Outillage mis à jour avec les commandes réelles (DoD §8.11).
- `api_platform.yaml` : `docs_formats` (json/jsonld/openapi/html) pour servir `/api/docs.json` (200).
- Suite : **36 tests / 161 assertions**, PHPStan niv.8, cs-fixer verts.

### 2026-06-24 — Mercure /api/realtime/token + clôture du scope (Tâches 9 & 7)
- **`GET /api/realtime/token`** : émet un JWT Mercure (via la fabrique du hub) scopé par famille
  active (X-Family-Id optionnel) ; claims `subscribe`/`publish` **vides au Lot 0** (aucun topic
  métier) ; recalcul à la bascule ; serveur seule autorité. `RealtimeTokenTest` (3 tests : émission,
  subscribe vide, scopé famille, 401).
- **Scope X-Family-Id (Tâche 7) clos** : `FamilyContextResolverTest` (unit, stubs) couvre header
  requis / UUID invalide / non-membre (403 anti-énum) / membre actif → contexte résolu.
- **Surface API backend du Lot 0 complète.** Suite : **36 tests / 161 assertions**, PHPStan niv.8,
  cs-fixer verts. OpenAPI à jour (`/api/realtime/token`).

### 2026-06-24 — Acceptation d'invitation + endpoints 501 RGPD
- **`POST /api/invitations/accept`** (US-ONB-1 sc.3-5) : auth requise, e-mail du compte = invité
  (sinon 403), token haché vérifié (sinon 400), crée le Membership (rôle + droits proposés ;
  parent = edit forcé), idempotent si déjà membre, `AuditLog invitation.accepted`.
- **Endpoints 501** (décision D7) routés et dans l'OpenAPI : `POST /api/me/export`, `DELETE /api/me`,
  `GET /api/admin/rgpd/{register,operations,compliance-report}`, `POST /api/admin/rgpd/purge`
  (`NotImplementedProvider`/`Processor`, `deserialize:false` pour les POST sans corps).
- Acceptation d'invitation désormais **non publique** (auth requise) ; exceptions invitation mappées RFC 7807.
- Base de test partagée `ApiAuthTestCase` ; `InvitationAndStubsTest` (5 tests : accept, mismatch, invalide,
  401, 501). **Suite : 28 tests / 127 assertions**, PHPStan niv.8, cs-fixer verts. OpenAPI à jour.

### 2026-06-24 — /api/me + /api/families
- **`/api/me`** (GET/PATCH) : profil + locale + `full_name`/`phone` 🔒 (déchiffrés en lecture, persistés
  chiffrés) ; `email`/`status`/`avatar_url` en lecture seule ; scopé à l'utilisateur du token.
- **`/api/families`** : `GET` (mes familles via memberships), `POST` (US-ONB-1 → crée Family +
  Membership parent+admin actif, `AuditLog family.created`), `GET /{id}` (membre actif uniquement,
  sinon 404 anti-énumération).
- DTO + Providers/Processors API Platform ; `patch_formats` (merge-patch+json + json).
- `MeAndFamiliesTest` : 4 tests (GET/PATCH me, 401 sans auth, create+list familles, 422 type invalide).
  **Suite : 23 tests / 85 assertions**, PHPStan niv.8, cs-fixer verts. OpenAPI à jour.

### 2026-06-24 — Authentification HTTP
- **7 endpoints auth** (register, login, refresh, verify-email, password/forgot, password/reset,
  logout) via **API Platform DTO + State Processors** (`src/Dto/Auth`, `src/State/Auth`) → présents
  dans l'OpenAPI exporté (`contracts/openapi.json`).
- **Sécurité** : Argon2id (sodium) ; JWT court (lexik, extracteurs header **et** cookie) ;
  **refresh rotatif + détection de réutilisation** (famille de tokens, `RefreshTokenManager`) ;
  jetons usage-unique hachés (`OneTimeTokenManager`) ; **anti-énumération** (register/login/forgot) ;
  vérification en **temps quasi-constant** (hash factice) ; **rate-limit + lockout** (login/register/forgot) ;
  refus login si e-mail non vérifié (D1) ; reset ⇒ révocation de tous les refresh ; `AuditLog` partout.
- **Cookies HttpOnly/SameSite=Lax** (web, `AuthCookieSubscriber`) + Bearer (mobile).
- **RFC 7807** localisé (`ProblemExceptionSubscriber`) ; **e-mails async** localisés (Messenger + Twig).
- **Tests** : base `app_test` + isolation **DAMA** + transport Messenger in-memory en test ;
  `AuthFlowTest` (parcours complet + anti-énum + 401 + 422) → 4 verts. Suite : **19 tests, 52 assertions**.
- Config : `security.yaml` (firewall API stateless JWT), `lexik`, `rate_limiter.yaml`, `api_platform.yaml`
  (formats json+jsonld), `cache.yaml` (array en test), bundles DAMA/Fixtures/Foundry enregistrés.
- ✅ Revue de sécurité adversariale (agent security-auditor) : 0 P0, 2 P1, 3 P2, 4 P3. Corrigés
  (trusted_proxies, atomicité reset, jeton OTT chiffré en transit, locale, forgot/actifs, cookie Secure) ;
  items conservés documentés en §4 (login 403 = D1, secrets dev, lock prod). Suite reste verte (19 tests).

### 2026-06-23 (suite)
- **Registre RGPD** : attribut `#[PersonalData]` + scanner `PersonalDataRegistry` (réflexion) +
  `docs/registre-donnees.md` (6 champs) + **test de cohérence CI** (échoue si champ personnel sans
  entrée, ou champ chiffré non marqué) — 3 tests verts.
- **Portes qualité vertes** : PHPStan niveau 8 (0 erreur, `treatPhpDocTypesAsCertain:false`),
  php-cs-fixer (@Symfony+risky, strict_types), `lint:container`. 15 tests unitaires verts.
- Corrections : signature `Voter::voteOnAttribute` (4ᵉ param `?Vote` en SF 8.1), Kernel minimal,
  garde non-vide sur `User::getUserIdentifier`/`setEmail`.

### 2026-06-23
- **Socle sécurité vérifié** : Voters par domaine + `FamilyContext`/resolver (scope X-Family-Id) ;
  12 tests unitaires verts (chiffrement round-trip/illisibilité/altération + table de vérité RBAC).
- **Modèle de données** : 10 entités + 14 enums + migration ; `CREATE EXTENSION citext` ;
  migration exécutée, `doctrine:schema:validate` → en sync.
- **Chiffrement** : `FieldEncryptor` AES-256-GCM (openssl), types Doctrine `encrypted_string`/`citext`,
  clé via env base64, attribut `#[PersonalData]` posé sur les champs personnels.
- **Backend scaffoldé** : `symfony new --webapp` (Symfony 8.1.0) + API Platform 4.3, lexik/jwt 3.2,
  Mercure, rate-limiter, uid, CORS, + dev (fixtures, PHPStan+ext, php-cs-fixer, Foundry, dama). JWT keypair généré.
- **Monorepo** : `/backend /mobile(à venir) /contracts /infra`, README, .gitignore, PROGRESS.md.
- Lecture intégrale cdc + lot-0 ; inventaire outillage (tout présent, Flutter 3.38.9).
