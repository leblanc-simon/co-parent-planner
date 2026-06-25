# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## État du dépôt

**Projet greenfield, non encore échafaudé.** Le seul contenu actuel est la spécification `specs/cdc.md` (cahier des charges, en français) — c'est le **contrat d'implémentation** et la source de vérité. Aucun code, aucun monorepo, aucun outillage build/test n'existe pour l'instant. Il n'y a pas non plus de dépôt git initialisé.

Avant toute implémentation, lire `specs/cdc.md` en entier. En cas d'ambiguïté de rendu visuel, les maquettes HTML du bundle Claude Design (`specs/design/CoParentPlanner Desktop.dc.html` + variante mobile, `ios-frame.jsx`) font foi.

## Décisions d'architecture verrouillées (cdc §0)

Ne pas remettre en cause ces choix sans demande explicite de l'utilisateur :

- **Backend** : Symfony 8.1 + API Platform 4 + Doctrine ORM + Mercure + Messenger, PostgreSQL 16. Héberge **à la fois** l'API REST (pour le mobile) et le front web.
- **Front web** : Symfony UX server-rendered — **Twig + Turbo + Stimulus + AssetMapper**. **Aucun framework JS** (pas de React/Vue). Le temps réel web passe par **Turbo Streams over Mercure** (fragments HTML poussés, pas de JS dédié).
- **App mobile** : Flutter (Dart), iOS + Android. Client HTTP **généré depuis l'OpenAPI** exporté par le backend. Offline riche (Drift + outbox pattern) côté mobile uniquement.
- **Génération de garde** : **matérialisation en base** (`CustodyDay`, un par jour) par un moteur idempotent, sur horizon glissant ~18 mois — pas de calcul à la volée.

## Structure cible du monorepo (cdc §4.1)

```
/backend     Symfony : API REST + front web Twig/UX + Mercure + Messenger + workers
/mobile      Flutter (iOS, Android), client généré depuis l'OpenAPI
/contracts   Schéma OpenAPI exporté par le backend → génération du client Dart
/infra       Docker compose, INSTALL.md (bare-metal), CI/CD
```

## Invariants à respecter dans tout le code

Ces règles traversent plusieurs modules — les violer casse le modèle de sécurité ou de conformité du produit :

- **Scope famille obligatoire** : toute ressource métier est scopée à la famille active via le header `X-Family-Id`, validé contre les memberships de l'utilisateur. Jamais de requête métier non scopée.
- **Autorisation par Voters** : un Voter Symfony par domaine de droit (`custody`, `agenda`, `children_notes`, `expenses`, `exchanges`, `family_settings`), niveaux `none|view|edit`, opérant sur (Membre, Ressource). L'autorisation se vérifie sur **chaque** ressource. Le rôle `parent` force `edit` partout ; le rôle `child` n'a jamais d'accès `expenses` ni d'édition de structure.
- **Dernier admin protégé** : un dernier admin d'une famille ne peut être retiré ni rétrogradé.
- **Priorité de la garde** : exception manuelle > règle de vacances > base générée. Modifier le `CustodyPattern` régénère les `CustodyDay` **futurs sans écraser** exceptions ni règles vacances. Recalculer les stats à partir des jours effectifs.
- **Pas de données de santé** : aucun champ typé santé n'est créé. Les infos enfant sont libres (paires label/valeur). Un bandeau d'avertissement persistant doit figurer sur l'écran d'édition enfant. (Divergence assumée vs maquette.)
- **Champs chiffrés au repos** (🔒 dans cdc §5.2 : `full_name`, `phone`, `Message.text`, `Note.text`, `Invitation.email`) : chiffrement applicatif AES-256-GCM. Un dump SQL brut ne doit révéler ni identités ni contenus.
- **Registre des données personnelles** : à chaque ajout d'un champ personnel, ajouter une entrée de registre (attribut déclaratif sur l'entité + `/backend/docs/registre-donnees.md`). Un test CI de cohérence échoue si un champ personnel n'a pas d'entrée. Fait partie de la DoD de chaque lot.
- **Dépenses = v2, masquées en v1** : le modèle de données réserve la place (`Expense`, `ExpenseShare`) mais l'onglet et l'API restent inactifs en v1.
- **i18n** : toutes les chaînes (UI + e-mail) externalisées dès le départ, format ICU. Seule la locale `fr-FR` est livrée, mais ajouter une langue ne doit demander aucun changement de code. Fuseau par défaut `Europe/Paris`.
- **Date des maquettes** (23 juin 2026) : ne jamais coder en dur.
- **`/infra/INSTALL.md`** : guide d'installation bare-metal (sans Docker) maintenu à jour à chaque ajout de dépendance — critère de DoD.

## Intégration à vérifier avant codage

Vacances scolaires : dataset officiel `fr-en-calendrier-scolaire` sur data.education.gouv.fr (API Opendatasoft, par zone A/B/C / académie / année scolaire). À encapsuler dans un adaptateur isolé `SchoolHolidaysProvider` avec cache. **Vérifier le schéma et l'endpoint réels en vigueur** — ne pas coder en dur une structure supposée.

## Découpage en lots (cdc §9)

Implémentation séquentielle, chaque lot livré en web ET mobile, conditionnant les suivants : Lot 0 Fondations (monorepo, CI, auth complète, harnais de tests) → 1 Familles/membres/droits/invitations → 2 Enfants/notes → 3 Garde (moteur + vacances) → 4 Agenda → 5 Familles liées/overlay → 6 Échanges temps réel → 7 Notifications e-mail → 8 PWA/offline/RGPD Manager → 9 Dépenses (v2) → 10 Push mobile (v2).

**DoD générale par lot** : tests unitaires + fonctionnels (API) + E2E web (Playwright) verts (+ tests Flutter si écran mobile), migrations fournies, OpenAPI à jour, i18n câblée, registre données à jour si nouveaux champs personnels, INSTALL.md à jour si dépendance change, analyse statique propre, écrans conformes au design system §8.

## Outillage (Lot 0 échafaudé — commandes réelles)

> Avancement détaillé et point de reprise : **`PROGRESS.md`** à la racine.

Backend Symfony 8.1 dans `/backend`. La base de dev tourne via Docker ; le binaire `symfony`
injecte l'env (port DB auto). **Le conteneur DB doit tourner pour les tests fonctionnels.**

```bash
# Base de dev (rapide) :
cd backend && docker compose up -d database      # PostgreSQL (port hôte auto)
# Tests (DB requise) — via symfony pour injecter l'env Docker :
symfony php bin/phpunit                            # unitaire + fonctionnel/API
php bin/phpunit tests/Unit tests/Rgpd              # sous-ensemble sans DB
# Analyse statique & style :
vendor/bin/phpstan analyse --memory-limit=1G       # niveau 8, 0 erreur
PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run --diff
# Contrat OpenAPI (source du client mobile) :
php bin/console api:openapi:export --output=../contracts/openapi.json
# Base de test (une fois) :
symfony console --env=test doctrine:database:create --if-not-exists
symfony console --env=test doctrine:migrations:migrate -n

# Stack complète (app FrankenPHP + worker + scheduler + PG + Redis + Mercure + Mailpit) :
cd infra && docker compose up --build              # app: http://localhost:8000
```

App mobile Flutter dans `/mobile` (Dart 3.10 / Flutter 3.38.9). Le client HTTP est **généré
depuis l'OpenAPI** par `swagger_parser` (pur Dart, **aucune JVM/openapi-generator**) → clients
retrofit/dio + modèles json_serializable dans `lib/api/generated/` (**commité, jamais édité à la main**).

```bash
cd mobile && flutter pub get
# Régénérer le client après un export OpenAPI backend (idempotent ; drift = CI rouge) :
dart run swagger_parser
dart run build_runner build --delete-conflicting-outputs --force-jit   # --force-jit IMPÉRATIF
#   ^ des deps natives (sqlite3, path_provider_foundation) ont un hook/build.dart qui casse
#     l'AOT du script build_runner sous Dart 3.10 ; le mode JIT le contourne.
flutter gen-l10n                                       # ARB -> AppL10n (i18n)
dart analyze                                           # 0 issue (exclut lib/api/generated)
flutter test --coverage                                # tests ; couverture ≥ 70% (D8), 84,3% actuel
```

E2E web (Playwright) dans `/web-e2e` (parcours inscription → vérif e-mail Mailpit → connexion) :

```bash
cd web-e2e && npm install && npx playwright install chromium
web-e2e/scripts/e2e-local.sh            # démarre la stack (DB + Mailpit + serve) et lance les tests
```

CI : `.github/workflows/ci.yml` (jobs `backend` + `web-e2e` + `mobile`) encode toutes les portes
qualité ci-dessus + les garde-fous de couverture (`backend/bin/coverage-gate.php` ≥80 %,
`mobile/tool/check_coverage.py` ≥70 %) et la détection de drift (OpenAPI + client Dart).

Installation détaillée (Docker **et** bare-metal) : `infra/INSTALL.md`.

## Design system (cdc §8 — fait foi pour le rendu)

Polices : `Bricolage Grotesque` (titres), `Hanken Grotesk` (corps). Palette terracotta/teal sur fonds crème — tokens clés : `--app-bg #E5E0D6`, `--surface #F4F1EA`, `--ink #292621`, `--sidebar #2B2722`, `--accent #C2643D` (terracotta), `--positive #2E7D71` (teal / parent B). Famille liée (overlay) : `#9C6FB0`. Catégories agenda et dépenses : voir §8 pour les codes exacts. Desktop : sidebar sombre 252px + zone principale (réf. 1320×840), sidebar repliable sous breakpoint. Mobile : style iOS « liquid glass », navigation par onglets en bas.
