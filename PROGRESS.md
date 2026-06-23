# Avancement d'implémentation — CoParentPlanner

> **Document de reprise.** Mis à jour régulièrement pour reprendre le travail facilement entre
> sessions. Source de vérité fonctionnelle : `specs/cdc.md` + `specs/lots/`. Invariants : `CLAUDE.md`.
> Maquettes (rendu visuel fait foi) : `specs/design/` (`CoParentPlanner Desktop.dc.html`, variante
> mobile, `ios-frame.jsx`, `screenshots/`).

Dernière mise à jour : **2026-06-23** — Lot 0 en cours (socle backend + sécurité posés et testés).

---

## 0. Où en est-on ?

**Lot courant : Lot 0 — Fondations** (`specs/lots/lot-0-fondations.md`).
Statut : 🟡 socle technique backend posé et **vérifié** (entités+migration+chiffrement+RBAC).
Reste : auth HTTP, i18n/mailer, Mercure token, RGPD registre, front web, mobile, CI/INSTALL.

Backend démarre, schéma migré et en sync, 12 tests unitaires verts.

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
| 7 | Voters par domaine + scope X-Family-Id | 🟡 | RBAC unit-testé ; test fonctionnel HTTP du resolver à faire (besoin auth) |
| 3 | Infra docker-compose dev (full : +Redis, worker, scheduler, app) | ⬜ | compose Symfony de base présent (db/mercure/mailpit) |
| 6 | Auth complète (register/login/refresh/reset/verify/logout) | ⬜ | |
| 8 | i18n fr-FR ICU + Mailer localisé + Messenger async | ⬜ | |
| 9 | Mercure + /api/realtime/token (claims vides) | ⬜ | |
| 10 | Squelette RGPD + registre-donnees + test cohérence CI | 🟡 | **registre + test cohérence faits & verts** ; reste : namespace RgpdManager + endpoints 501 (avec API/auth) |
| 11 | Front web : shell + design system + écrans auth | ⬜ | maquettes dans `specs/design/` |
| 12 | App mobile Flutter : squelette + client généré + auth | ⬜ | |
| 13 | Harnais tests + CI + INSTALL.md | 🟡 | **PHPUnit unit (15 tests), PHPStan niv.8, php-cs-fixer : verts** ; reste : fonctionnel/API, Playwright, flutter test, couverture, CI, INSTALL |

**Portes qualité actuellement vertes** : `php bin/phpunit tests/Unit tests/Rgpd` (15 tests), `vendor/bin/phpstan` (niveau 8, 0 erreur), `php-cs-fixer --dry-run` (0), `doctrine:schema:validate`, `lint:container`.

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
- Le resolver `FamilyContext` lève des `HttpException` standard ; le **formatage RFC 7807 exact**
  (champ `type` de §6.5, messages localisés) reste à câbler via un exception subscriber (avec Tâche 8).
- `Invitation.invitedBy` pointe vers `User` (au lieu de Membership) — à reconsidérer au Lot 1.
- Couche offline mobile, PWA, RGPD complet = lots ultérieurs (8) — hors Lot 0.

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
php bin/phpunit                       # tout
php bin/phpunit tests/Unit            # unitaires (sans DB)

# Clés JWT (déjà générées dans config/jwt/, non committées)
php bin/console lexik:jwt:generate-keypair --overwrite -n
```
# Qualité (verts)
vendor/bin/phpstan analyse --memory-limit=1G          # niveau 8, 0 erreur
PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run --diff
php bin/console lint:container
```
À venir : tests fonctionnels/API (DB de test), Playwright, `flutter test`, génération client Dart, CI.

---

## 6. Journal (du plus récent au plus ancien)

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
