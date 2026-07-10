# Lot 0 — Fondations

> Spécification détaillée du **Lot 0 — Fondations** du projet CoParentPlanner.
> Source de vérité : `specs/cdc.md` (cahier des charges) et `CLAUDE.md` (invariants verrouillés).
> Sections du cdc directement concernées : §0, §1.3, §1.4, §2.1, §2.2, §3.1, §3.2, §4.1–§4.9, §5.1–§5.4, §6.1, §6.2 (Auth & compte), §7, §7.1, §8, §9 (Lot 0), §10.
> Ce document est un document de conception ; il ne contredit aucune décision d'architecture verrouillée (§0).

---

## 0. Objectif & périmètre

### 0.1 Objectif

Poser **toutes les fondations techniques** du monorepo et livrer **l'authentification complète de bout en bout** (web + mobile), de sorte que les lots métier suivants (Lot 1 à Lot 11) puissent s'ajouter sans retoucher l'ossature : aucune décision structurante (sécurité, scope famille, i18n, chiffrement, RGPD, tests, CI, design system, génération du client mobile) ne devra être reprise après ce lot.

Le Lot 0 est **transversal** : il ne livre presque aucune fonctionnalité métier (pas de familles éditables, pas de garde, pas d'agenda), mais il pose les **rails** que tous les lots emprunteront. Sa réussite se mesure à la capacité d'un développeur à : cloner le dépôt, suivre `INSTALL.md` (avec ou sans Docker), démarrer la stack, s'inscrire, vérifier son e-mail, se connecter, rafraîchir son token, réinitialiser son mot de passe, et voir le shell applicatif thématisé — le tout sur **web** et sur **mobile**, avec une CI verte.

### 0.2 Dans le périmètre (in-scope)

**Monorepo & infra**
- Arborescence `/backend`, `/mobile`, `/contracts`, `/infra` (cdc §4.1).
- `docker-compose` dev complet : PHP-FPM/FrankenPHP, PostgreSQL 16, Redis, Mercure, MailHog, worker Messenger, scheduler (cdc §4.8).
- `/infra/INSTALL.md` : installation **bare-metal pas à pas** ET Docker, reproductible (cdc §4.8, §10).
- Pipeline **CI** : lint, analyse statique, tests (PHPUnit unitaire+fonctionnel, Playwright E2E web, flutter test), build assets web, build cibles Flutter, génération + validation de l'OpenAPI et du client Dart, seuil de couverture (cdc §4.8, §4.9).

**Backend Symfony (squelette opérationnel)**
- Symfony 8.1 + API Platform 4 + Doctrine ORM + PostgreSQL 16, migrations versionnées.
- Sécurité : JWT (lexik/jwt) court + **refresh rotatif**, hachage **Argon2id**, **rate-limiting** (login, register, forgot-password), verrouillage après N tentatives.
- **Squelette des Voters** : un Voter par domaine (`custody`, `agenda`, `children_notes`, `expenses`, `exchanges`, `family_settings`), niveaux `none|view|edit`, opérant sur (Membre, Ressource) — câblé et testé même si peu de ressources métier existent au Lot 0.
- Middleware de **scope famille** : validation du header `X-Family-Id` contre les memberships de l'utilisateur (mécanisme posé, listener/argument resolver, testé).
- i18n : composant Translation (ICU), négociation de locale (`Accept-Language` / préférence utilisateur), `fr-FR` livrée.
- Mailer : Symfony Mailer + templates Twig localisés (e-mail de vérification, e-mail de reset).
- Messenger : transport configuré (Doctrine/Redis), worker démarrable, e-mails envoyés en asynchrone.
- Mercure : binaire câblé et démarrable ; **JWT Mercure émis et scopé** par famille active (claims `subscribe` calculés côté serveur, **vides au Lot 0** faute de topic métier ; recalcul à la bascule de famille — mécanisme posé et testé, §3.12). Le JWT Mercure initial **ne porte aucun topic métier** tant que les memberships / la famille active ne sont pas résolus. Les topics métier viennent aux lots ultérieurs (§4.5).
- Scheduler : composant configuré, un job de démonstration/santé (les vraies tâches arrivent aux lots métier).
- Export OpenAPI servi (`/api/docs.json`).

**Entités créées au Lot 0 (sous-ensemble strictement nécessaire à l'auth + ossature)**
- `User` (complète), `Membership` (squelette — nécessaire pour valider `X-Family-Id` et US-ONB-1), `Family` (squelette minimal — nécessaire pour US-ONB-1 « créer ma première famille »), `Invitation` (squelette — table + champ `email` 🔒, l'acceptation détaillée arrive au Lot 1), `NotificationPref` (table créée, pilotée au Lot 7), `AuditLog` (append-only, branché sur les événements d'auth), `RgpdOperation` (table créée, alimentée au Lot 8).
- Les autres entités (`Child`, `CustodyPattern`, `CustodyDay`, etc.) **ne sont pas** créées au Lot 0.

**Front web Symfony UX (shell)**
- AssetMapper, Turbo (Drive/Frames/Streams), Stimulus.
- Design tokens §8 (couleurs, typographies, formes), thème appliqué.
- **Shell applicatif** : layout desktop (sidebar sombre 252px + zone principale, réf. 1320×840), responsive (sidebar repliable sous breakpoint), **sélecteur de famille** (composant câblé ; liste alimentée par les memberships, même si vide/à 1 famille au Lot 0).
- Écrans d'auth web : inscription, connexion, déconnexion, vérification e-mail, mot de passe oublié/reset, onboarding « créer ma première famille / rejoindre via lien ».
- Squelette PWA optionnel posé (manifest) — l'offline web dégradé complet est au Lot 8 ; le Lot 0 pose seulement l'amorce si elle ne coûte rien (non bloquant).

**App mobile Flutter (squelette)**
- Projet Flutter iOS + Android, navigation (onglets en bas), thème §8 (style iOS « liquid glass », Material adapté Android).
- **Client HTTP généré depuis l'OpenAPI** (dio) + intercepteurs (auth Bearer, refresh, `Accept-Language`, `X-Family-Id`, retry).
- Gestion d'état : **Riverpod** — **retenu et figé au Lot 0** (décision D6 §0.5), appliqué uniformément (Bloc non utilisé).
- **Couche offline (squelette)** : Drift (SQLite) initialisé, table outbox + machine d'état de synchro posée (le parcours offline riche complet est validé au Lot 8 ; ici on pose le schéma, l'`Idempotency-Key`, le statut « en attente de synchro », sans tous les cas métier).
- Écrans d'auth mobile : inscription, connexion, déconnexion, vérification e-mail (deep link), reset, onboarding première famille.

**Harnais de tests & qualité**
- PHPUnit (unitaire + fonctionnel/API) avec base de test isolée + fixtures de base.
- Playwright (E2E web) configuré en CI (au moins le parcours inscription→vérification→connexion).
- `flutter test` (unitaires + widget) + `dart analyze` + `dart format`.
- PHPStan (niveau élevé), `php-cs-fixer`.
- Seuil de couverture **défini** au Lot 0 et mesuré en CI.

**RGPD (squelette)**
- Module `RgpdManager` (namespace/squelette) + `/backend/docs/registre-donnees.md` **initialisé** avec les champs personnels introduits au Lot 0.
- Attribut déclaratif de registre sur les entités + **test de cohérence CI** (échoue si un champ personnel n'a pas d'entrée de registre) — posé dès maintenant car des champs 🔒 apparaissent au Lot 0.
- Le RGPD Manager **complet** (anonymiseur, purge, rapports, endpoints `/api/admin/rgpd/*`, export/suppression réels) est au Lot 8 ; ici on pose la structure, le registre et le test de cohérence.

**Auth complète (US-AUTH-1, US-AUTH-2, US-ONB-1)** sur web ET mobile.

### 0.3 Hors périmètre (out-of-scope, repoussé aux lots suivants)

- Gestion fine des familles/membres/droits/invitations (Lot 1) : ici, seulement le squelette `Family`/`Membership`/`Invitation` nécessaire à US-ONB-1 et au scope `X-Family-Id`. L'écran « Paramètres › Famille », l'invitation e-mail complète à token, la protection détaillée du dernier admin, les droits granulaires éditables = **Lot 1**.
- Enfants & notes (Lot 2). Garde & vacances (Lot 3). Agenda (Lot 4). Familles liées/overlay (Lot 5). Échanges temps réel (Lot 6). Notifications e-mail métier (Lot 7).
- Topics Mercure métier (exchanges/custody/agenda/notes) : posés aux lots concernés (§4.5). Au Lot 0 : la plomberie Mercure (binaire, JWT) seulement.
- RGPD Manager fonctionnel (anonymiseur, purge, rapports, export/suppression réels) = **Lot 8**.
- PWA offline web dégradé complet = **Lot 8**. Offline mobile riche de bout en bout = **Lot 8**.
- Dépenses (v2) = **Lot 10**. Push mobile (v2) = **Lot 11**.
- Adaptateur vacances scolaires `SchoolHolidaysProvider` = **Lot 3** (mais la note « vérifier l'endpoint réel » est rappelée plus bas).

### 0.4 Contexte v1 / v2

- Le Lot 0 est intégralement **v1**. Aucun module v2 n'est activé.
- Les **dépenses** (`Expense`, `ExpenseShare`) ne sont PAS créées au Lot 0 (elles arrivent au Lot 10, v2) ; toutefois l'invariant « onglet/API dépenses masqués en v1 » est respecté de fait (rien n'est exposé). Le domaine de droit `expenses` existe néanmoins comme énuméré dans les Voters (squelette), forcé `none` pour les enfants.
- Le **push mobile** (canal `push` de `NotificationPref`) n'est pas câblé (v2, Lot 11) ; seul le canal `email` est prévu, mais aucun envoi de notification métier n'est fait au Lot 0 (uniquement les e-mails transactionnels d'auth).

### 0.5 Décisions tranchées au Lot 0 (politiques verrouillées par ce lot)

Le Lot 0 est censé **verrouiller l'ossature** : plutôt que de renvoyer des choix structurants à « plus tard », ce document les **tranche** ici. Ces décisions sont la source de vérité pour l'implémentation ; elles peuvent être revues, mais seulement par demande explicite et avec mise à jour de ce document. Elles sont reprises et détaillées dans les sections concernées (renvoi indiqué).

| # | Sujet | Décision tranchée au Lot 0 | Renvoi |
|---|---|---|---|
| D1 | **Token avant vérification d'e-mail** | **Aucun token d'accès ni refresh n'est délivré avant vérification d'e-mail.** L'inscription répond « vérifiez votre e-mail » sans token. La connexion d'un compte `pending` est **refusée** (`403`) avec message « vérifiez votre e-mail » + action « renvoyer ». Pas de « token à scope restreint ». | §2 US-AUTH-1 sc.2, US-AUTH-2 sc.3 ; §3.1, §3.2 |
| D2 | **Stratégie session/cookie web** | Le **web consomme l'API en Bearer JWT** comme le mobile, mais les tokens sont posés dans des **cookies `HttpOnly`, `Secure`, `SameSite=Lax`** par le backend (le JS ne lit jamais le JWT). Le refresh rotatif est porté par un cookie `HttpOnly` dédié au chemin `/api/auth/refresh`. **Pas de session serveur PHP** pour l'état d'auth (stateless JWT). | §2 US-AUTH-2 sc.1 ; §3.2 ; §4.8 (CSRF) |
| D3 | **Tolérance de refresh concurrent** | **Strict, sécurité d'abord** : aucune fenêtre de grâce. Deux refresh concurrents avec le même token → un seul réussit (rotation atomique) ; l'autre est traité comme **réutilisation** → invalidation de la famille de tokens (§3.3). Une courte fenêtre de grâce n'est PAS implémentée au Lot 0. | §3.3 ; §4.2 |
| D4 | **FK `Membership.child_id`** | La colonne `child_id` est créée au Lot 0 **sans contrainte FK** (la table `Child` n'existe pas encore). La **contrainte FK est ajoutée au Lot 2** par migration, en même temps que `Child`. Au Lot 0, la colonne reste toujours `NULL` (aucun compte-enfant créé). | §5.4 |
| D5 | **Chemin exact de vérification d'e-mail** | Le lien e-mail pointe vers une **page web `GET /verify-email?token=…`** (rendu Twig, conviviale) qui appelle en interne **`POST /api/auth/verify-email`** (corps `{ token }`). Le mobile capte le **deep link `coparentplanner://verify-email?token=…`** et appelle le même `POST`. **Un seul endpoint API** : `POST /api/auth/verify-email`. Idem reset : page `GET /reset-password?token=…` → `POST /api/auth/password/reset`. | §6.2 ; §3.1 |
| D6 | **Gestion d'état mobile** | **Riverpod** (retenu, figé). Bloc n'est pas utilisé. Appliqué uniformément. | §0.2 (mobile) ; §3.10 |
| D7 | **Statut HTTP des endpoints non implémentés** | **Politique unique : `501 Not Implemented`** (RFC 7807) pour tout endpoint **présent dans l'OpenAPI mais non encore fonctionnel** (`/api/me/export`, `DELETE /api/me`, `/api/admin/rgpd/*`). On **route** ces endpoints (ils figurent dans l'OpenAPI, donc dans le client Dart généré) mais ils renvoient `501`. On n'utilise **jamais** `404` pour un endpoint « pas encore prêt » (réservé aux ressources réellement inexistantes), afin d'éviter toute incohérence OpenAPI / client généré. | §2 US-RGPD-0 sc.4 ; §6.3 |
| D8 | **Seuil de couverture** | **Backend ≥ 80 %** de couverture de lignes (mesuré par PHPUnit/Xdebug-PCOV) ; **Mobile ≥ 70 %** (`flutter test --coverage`). La CI échoue sous ces seuils. Valeurs révisables à la hausse aux lots suivants, jamais à la baisse sans décision explicite. | §3.8 ; §8.2 ; §8.10 |

> Conséquence : dans le reste du document, les formulations historiques « à figer au Lot 0 » sont **résolues** par le tableau ci-dessus ; elles sont conservées avec la mention de la décision retenue pour traçabilité.

---

## 1. Dépendances

### 1.1 Lots prérequis (avant)

**Aucun.** Le Lot 0 est le premier lot. Il part d'un dépôt greenfield non échafaudé (cf. CLAUDE.md « État du dépôt »).

### 1.2 Lots débloqués (après) et raisons

| Lot débloqué | Pourquoi il dépend du Lot 0 |
|---|---|
| **Lot 1 — Familles/membres/droits/invitations** | Réutilise le squelette `Family`/`Membership`/`Invitation`, le scope `X-Family-Id`, les Voters, le sélecteur de famille, l'auth. Sans les rails du Lot 0, rien de tout cela n'existe. |
| **Lot 2 — Enfants/notes** | Dépend des Voters (`children_notes`), du chiffrement 🔒 (`Note.text`), du registre de données, de l'auth/scope. |
| **Lot 3 — Garde** | Dépend du scope famille, du Scheduler (régénération d'horizon), de Messenger (import async), des Voters (`custody`). |
| **Lot 4 — Agenda** | Dépend du scope, des Voters (`agenda`), du temps réel Mercure (plomberie posée au Lot 0). |
| **Lot 5 — Familles liées/overlay** | Dépend du modèle de famille et du scope. |
| **Lot 6 — Échanges temps réel** | Dépend de la plomberie Mercure + de l'émission/scoping du JWT Mercure posée au Lot 0 (§3.12 : le Lot 6 n'aura qu'à **ajouter les topics `exchanges` aux claims**, sans retoucher le mécanisme), des Voters (`exchanges`), du chiffrement 🔒 (`Message.text`). |
| **Lot 7 — Notifications e-mail** | Dépend de Messenger + Mailer + templates i18n + `NotificationPref` posés au Lot 0. |
| **Lot 8 — PWA/offline/RGPD Manager** | Dépend du squelette RGPD (`RgpdManager`, `registre-donnees.md`, test de cohérence), de `AuditLog`, du chiffrement, et du squelette outbox/Drift mobile. |
| **Lot 10 — Dépenses (v2)** | Dépend des Voters (`expenses`) et du scope. |
| **Lot 11 — Push mobile (v2)** | Dépend de `NotificationPref` (canal `push`) et de la couche notif du Lot 7. |

> En clair : **tout** le projet dépend du Lot 0. C'est pourquoi sa DoD est stricte (cdc §9, CA Lot 0).

---

## 2. User stories complètes

> Convention Gherkin : **Étant donné** (contexte) / **Quand** (action) / **Alors** (résultat attendu). Plusieurs scénarios par US : nominal, erreur, autorisation refusée, temps réel/offline si applicable.
> Toutes les chaînes (UI + e-mail) sont localisées (ICU, `fr-FR`). Messages d'erreur d'auth **non énumérants** (ne révèlent pas si l'e-mail existe — cdc §3.1 CA).
> Toute date manipulée est en fuseau `Europe/Paris` par défaut ; aucune date n'est codée en dur (la date des maquettes, 23 juin 2026, ne doit jamais apparaître en dur — cdc §10).

---

### US-AUTH-1 — Créer un compte par e-mail / mot de passe

**En tant qu'**utilisateur non enregistré, **je veux** créer un compte avec mon e-mail et un mot de passe **afin d'**accéder à l'application.

**Critères d'acceptation**

**Scénario 1 — Inscription nominale (web)**
- **Étant donné** que je suis sur l'écran d'inscription web et que l'e-mail `alice@example.com` n'a pas de compte
- **Quand** je soumets un e-mail valide et un mot de passe respectant la politique (longueur min, complexité définie au Lot 0)
- **Alors** un `User` est créé avec `password_hash` en **Argon2id**, `status=pending` (e-mail non vérifié), `email_verified_at=null`, `locale=fr-FR` par défaut
- **Et** un e-mail de vérification localisé est envoyé **en asynchrone** (Messenger → MailHog en dev) contenant un lien à usage unique et expirant
- **Et** je suis redirigé vers un écran « vérifiez votre e-mail » sans accès complet à l'app
- **Et** un `AuditLog` `user.registered` est écrit (append-only)
- **Et** le champ `full_name` (si saisi) et `phone` (si saisi) sont **chiffrés au repos** (🔒 AES-256-GCM) : un dump SQL ne les révèle pas.

**Scénario 2 — Inscription nominale (mobile)**
- **Étant donné** l'app Flutter sur l'écran d'inscription
- **Quand** je soumets des identifiants valides via le client HTTP généré (`POST /api/auth/register`)
- **Alors** le compte est créé côté serveur identiquement au web
- **Et** l'app affiche l'écran « vérifiez votre e-mail » et stocke localement l'état `pending` (Drift)
- **Et** **aucun token (ni accès ni refresh) n'est délivré avant vérification** (décision D1 §0.5 : pas de token avant vérification, pas de token à scope restreint).

**Scénario 3 — E-mail déjà utilisé (anti-énumération)**
- **Étant donné** qu'un compte existe déjà pour `bob@example.com`
- **Quand** je tente de m'inscrire avec `bob@example.com`
- **Alors** la réponse est **identique** (en apparence) à une inscription réussie OU un message générique localisé non énumérant ; **aucune** indication explicite « cet e-mail existe déjà » n'est renvoyée
- **Et** aucun second compte n'est créé (unicité `email` citext)
- **Et** optionnellement, un e-mail « quelqu'un a tenté de créer un compte avec votre adresse » est envoyé au compte existant (anti-énumération conviviale).

**Scénario 4 — Mot de passe trop faible / e-mail invalide**
- **Étant donné** l'écran d'inscription
- **Quand** je soumets un e-mail malformé ou un mot de passe ne respectant pas la politique
- **Alors** la réponse est `422` (ou `400`) RFC 7807 `application/problem+json` avec messages de validation **localisés** par champ
- **Et** aucun `User` n'est créé.

**Scénario 5 — Vérification d'e-mail (lien à usage unique)**
- **Étant donné** que j'ai reçu l'e-mail de vérification avec un lien/token valide non expiré
- **Quand** je clique le lien (web) ou ouvre le deep link (mobile)
- **Alors** `email_verified_at` est renseigné, `status=active`, le token est **invalidé** (usage unique)
- **Et** un `AuditLog` `user.email_verified` est écrit
- **Et** je peux désormais accéder à l'app complète (et suis dirigé vers l'onboarding US-ONB-1 si je n'ai aucune famille).

**Scénario 6 — Token de vérification expiré / déjà utilisé**
- **Étant donné** un lien de vérification expiré ou déjà consommé
- **Quand** je l'utilise
- **Alors** un message localisé « lien invalide ou expiré » s'affiche avec une action « renvoyer l'e-mail de vérification »
- **Et** aucun changement d'état n'est appliqué.

---

### US-AUTH-2 — Se connecter / se déconnecter / réinitialiser le mot de passe

**En tant qu'**utilisateur enregistré, **je veux** me connecter, me déconnecter et réinitialiser mon mot de passe **afin de** gérer mon accès en sécurité.

**Critères d'acceptation**

**Scénario 1 — Connexion nominale (web & mobile)**
- **Étant donné** un compte `active` avec e-mail vérifié
- **Quand** je soumets les bons identifiants (`POST /api/auth/login`)
- **Alors** je reçois un **JWT d'accès court** + un **refresh token** (rotatif)
- **Et** `last_login_at` est mis à jour
- **Et** un `AuditLog` `user.login_success` est écrit
- **Et** (web) les tokens sont posés en **cookies `HttpOnly`, `Secure`, `SameSite=Lax`** (refresh sur cookie dédié au chemin `/api/auth/refresh`), sans session serveur PHP (décision D2 §0.5) ; (mobile) les tokens sont stockés dans le stockage sécurisé du device (Keychain/Keystore).

**Scénario 2 — Identifiants invalides (non énumérant)**
- **Étant donné** l'écran de connexion
- **Quand** je soumets un e-mail inconnu OU un mauvais mot de passe pour un e-mail connu
- **Alors** la réponse est `401` RFC 7807 avec un message **générique localisé** (« identifiants invalides ») **identique** dans les deux cas (ne révèle pas si l'e-mail existe)
- **Et** un `AuditLog` `user.login_failed` est écrit (sans révéler l'existence du compte côté utilisateur)
- **Et** le compteur d'échecs est incrémenté (voir Scénario 4).

**Scénario 3 — Connexion refusée car e-mail non vérifié**
- **Étant donné** un compte `pending` (e-mail non vérifié)
- **Quand** je tente de me connecter avec les bons identifiants
- **Alors** l'accès est **refusé** (`403` RFC 7807) avec un message localisé « vérifiez votre e-mail » + action « renvoyer l'e-mail de vérification » (décision D1 §0.5 : aucun état restreint, aucun token délivré)
- **Et** aucun token n'est émis et aucune ressource métier scopée n'est accessible.

**Scénario 4 — Verrouillage après N tentatives (rate-limit + lockout)**
- **Étant donné** N échecs consécutifs de connexion (N et fenêtre définis au Lot 0) pour un même e-mail et/ou une même IP
- **Quand** je tente une nouvelle connexion pendant la fenêtre de verrouillage
- **Alors** la réponse est `429 Too Many Requests` RFC 7807, message localisé « trop de tentatives, réessayez plus tard », avec en-tête `Retry-After`
- **Et** un `AuditLog` `user.login_locked` est écrit
- **Et** le verrouillage est **temporel** (se lève après expiration) — pas de verrouillage permanent silencieux.

**Scénario 5 — Refresh rotatif nominal**
- **Étant donné** un refresh token valide non révoqué
- **Quand** j'appelle `POST /api/auth/refresh`
- **Alors** je reçois un **nouveau** JWT d'accès **et** un **nouveau** refresh token ; **l'ancien refresh token est révoqué** (rotation)
- **Et** la famille/contexte n'est pas perdue côté client.

**Scénario 6 — Réutilisation d'un refresh token déjà tourné (détection de vol)**
- **Étant donné** qu'un refresh token a déjà été utilisé (donc révoqué par rotation)
- **Quand** un acteur tente de le réutiliser
- **Alors** la réponse est `401` et **toute la chaîne de refresh de cette session est révoquée** (invalidation de la famille de tokens) — défense contre le rejeu/vol
- **Et** un `AuditLog` `user.refresh_reuse_detected` est écrit.

**Scénario 7 — Déconnexion**
- **Étant donné** une session active
- **Quand** je me déconnecte (web : action de logout ; mobile : action de logout)
- **Alors** le refresh token courant est **révoqué** côté serveur
- **Et** (web) la session/cookie est détruite ; (mobile) les tokens sont effacés du stockage sécurisé et le cache local sensible est invalidé
- **Et** un `AuditLog` `user.logout` est écrit.

**Scénario 8 — Mot de passe oublié (anti-énumération)**
- **Étant donné** l'écran « mot de passe oublié »
- **Quand** je soumets un e-mail (`POST /api/auth/password/forgot`)
- **Alors** la réponse est **toujours** un succès générique localisé (« si un compte existe, un e-mail a été envoyé »), **que l'e-mail existe ou non**
- **Et** si le compte existe, un e-mail de reset localisé est envoyé en asynchrone avec un token **haché** à usage unique et expirant
- **Et** la route est **rate-limitée** (anti-abus).

**Scénario 9 — Réinitialisation effective**
- **Étant donné** un token de reset valide non expiré
- **Quand** je soumets un nouveau mot de passe conforme (`POST /api/auth/password/reset`)
- **Alors** `password_hash` est remplacé (Argon2id), le token de reset est **invalidé** (usage unique)
- **Et** **tous les refresh tokens existants de l'utilisateur sont révoqués** (déconnexion globale après reset)
- **Et** un `AuditLog` `user.password_reset` est écrit
- **Et** un e-mail de confirmation localisé « votre mot de passe a été changé » est envoyé.

**Scénario 10 — Token de reset expiré / déjà utilisé**
- **Étant donné** un token de reset expiré ou consommé
- **Quand** je soumets un nouveau mot de passe
- **Alors** la réponse est `400/410` RFC 7807, message localisé « lien invalide ou expiré »
- **Et** aucun mot de passe n'est changé.

**Scénario 11 — Reset rate-limité**
- **Étant donné** un grand nombre de demandes de reset pour un même e-mail/IP
- **Quand** je dépasse le seuil
- **Alors** `429` avec `Retry-After`, et un `AuditLog` est écrit.

---

### US-ONB-1 — Créer ma première famille ou rejoindre via invitation

**En tant qu'**utilisateur fraîchement vérifié sans famille, **je veux** créer ma première famille (nom + type) ou rejoindre une famille via un lien d'invitation **afin de** commencer à utiliser l'app dans un contexte familial.

> Au Lot 0, on livre le **chemin minimal viable** : créer une famille (qui crée le `Membership` `parent`+`admin` du créateur) et le **squelette** d'acceptation d'invitation (consommation du token → création du membership). La gestion fine des invitations (émission depuis l'écran Paramètres, droits granulaires détaillés, protection avancée du dernier admin) est approfondie au **Lot 1**.

**Critères d'acceptation**

**Scénario 1 — Créer ma première famille (nominal)**
- **Étant donné** un utilisateur `active` n'ayant aucun `Membership`
- **Quand** je crée une famille en saisissant un `name` et un `type` (`separated` ou `intact`) (`POST /api/families`)
- **Alors** une `Family` est créée (avec `timezone=Europe/Paris` par défaut, modules par défaut)
- **Et** un `Membership` est créé liant l'utilisateur à la famille avec `role=parent`, `is_admin=true`, `status=active`
- **Et** la famille devient la **famille active** par défaut (sélecteur de famille pointant dessus)
- **Et** un `AuditLog` `family.created` est écrit
- **Et** je suis redirigé vers l'écran d'accueil (shell), `X-Family-Id` désormais résolu.

**Scénario 2 — Type de famille invalide / nom vide**
- **Étant donné** l'écran de création de famille
- **Quand** je soumets un `type` hors `{separated, intact}` ou un `name` vide/trop long
- **Alors** réponse `422` RFC 7807 avec messages de validation localisés
- **Et** aucune `Family` ni `Membership` n'est créé.

**Scénario 3 — Rejoindre via un lien d'invitation valide (squelette Lot 0)**
- **Étant donné** un token d'invitation valide, non expiré, non révoqué, correspondant à mon e-mail
- **Quand** j'accepte l'invitation (`POST /api/invitations/accept` avec le token)
- **Alors** un `Membership` est créé avec le `role` et les `proposed_rights` portés par l'invitation, `status=active`
- **Et** l'invitation passe à `status=accepted` et son token est **invalidé** (usage unique)
- **Et** la famille rejointe devient sélectionnable dans le sélecteur de famille
- **Et** un `AuditLog` `invitation.accepted` est écrit.

**Scénario 4 — Invitation expirée / révoquée / déjà acceptée**
- **Étant donné** un token d'invitation expiré, révoqué ou déjà consommé
- **Quand** je tente de l'accepter
- **Alors** réponse `400/410` RFC 7807, message localisé « invitation invalide ou expirée »
- **Et** aucun `Membership` n'est créé.

**Scénario 5 — Invitation pour un autre e-mail que le mien**
- **Étant donné** une invitation émise pour `carol@example.com` et que je suis connecté en tant que `dan@example.com`
- **Quand** je tente d'accepter
- **Alors** l'acceptation est **refusée** (`403` RFC 7807, message localisé) : **l'e-mail de l'utilisateur connecté doit correspondre à l'e-mail invité** (politique tranchée au Lot 0 ; cohérente avec D5 §0.5 — l'invitation est nominative). Aucun membership n'est créé pour le mauvais utilisateur.

**Scénario 6 — Onboarding obligatoire avant accès métier**
- **Étant donné** un utilisateur vérifié **sans aucune famille**
- **Quand** il atteint le shell applicatif
- **Alors** il est dirigé vers l'onboarding (créer/rejoindre) ; le reste de l'app affiche un état vide cohérent jusqu'à ce qu'une famille existe
- **Et** aucune requête métier scopée ne peut aboutir tant qu'aucune famille active n'est résolue (`X-Family-Id` absent → `400/422`, voir §3).

---

### US-HOME-1 — Voir l'accueil (shell)

**En tant que** membre d'une famille, **je veux** voir un écran d'accueil donnant le contexte de la famille active **afin de** m'orienter rapidement.

> Au Lot 0, l'accueil est essentiellement **le shell** : layout, sidebar, sélecteur de famille, en-têtes thématisés, et **états vides** des encarts (garde de la semaine, à venir, à décider, dernières notes). Le contenu réel de ces encarts est rempli aux lots métier (3/4/6/2). On valide ici la structure, la perspective « famille active », le responsive, et le masquage de l'encart garde pour une famille unie.

**Critères d'acceptation**

**Scénario 1 — Shell affiché pour une famille active**
- **Étant donné** un utilisateur connecté ayant au moins une famille active
- **Quand** il ouvre l'accueil
- **Alors** le shell s'affiche avec la sidebar (252px sombre desktop), le sélecteur de famille (famille active mise en évidence), et les encarts en **état vide** localisé (« aucun événement à venir », etc.)
- **Et** tout est filtré sur la **famille active** et la **perspective** du membre connecté.

**Scénario 2 — Encart garde masqué pour une famille unie**
- **Étant donné** une famille active de type `intact`
- **Quand** j'ouvre l'accueil
- **Alors** l'encart « garde de la semaine » est **masqué** et remplacé par un message localisé « toute la famille réunie » (cdc §3.2 CA)
- **Et** pour une famille `separated`, l'encart garde est **présent** (en état vide au Lot 0, faute de planning).

**Scénario 3 — Bascule de famille active (sélecteur)**
- **Étant donné** un utilisateur membre de **deux** familles
- **Quand** il choisit l'autre famille dans le sélecteur
- **Alors** le contexte `X-Family-Id` bascule, l'accueil se recharge (Turbo) sur la perspective de la nouvelle famille
- **Et** aucune donnée de l'ancienne famille ne reste affichée (pas de fuite inter-familles).

**Scénario 4 — Responsive (sidebar repliable)**
- **Étant donné** un viewport sous le breakpoint défini
- **Quand** j'affiche le shell
- **Alors** la sidebar se replie en navigation compacte (conforme §8) et le contenu s'empile
- **Et** le rendu reste fidèle aux tokens du design system §8.

**Scénario 5 — Accès refusé sans authentification**
- **Étant donné** un visiteur non authentifié
- **Quand** il tente d'accéder au shell/accueil
- **Alors** il est redirigé vers l'écran de connexion (web) / l'app affiche l'écran d'auth (mobile)
- **Et** aucune donnée familiale n'est exposée.

---

### US-SET-1 (sous-ensemble Lot 0) — Éditer mon compte (profil & langue)

**En tant qu'**utilisateur connecté, **je veux** consulter et éditer les éléments de base de mon compte (profil, langue) **afin de** personnaliser mon expérience.

> Au Lot 0, on livre le **strict nécessaire** : lecture/écriture du profil (`GET/PATCH /api/me`) — notamment `locale`, `full_name` 🔒, `phone` 🔒. Les préférences de notification par catégorie et la sécurité avancée sont approfondies au Lot 7 / lots ultérieurs ; la table `NotificationPref` est créée mais l'écran de réglages détaillé n'est pas dans ce lot.

**Critères d'acceptation**

**Scénario 1 — Lire mon profil**
- **Étant donné** un utilisateur authentifié
- **Quand** il appelle `GET /api/me`
- **Alors** il reçoit son profil avec `email`, `locale`, et `full_name`/`phone` **déchiffrés** côté application (jamais exposés en clair en base)
- **Et** aucun champ d'un autre utilisateur n'est accessible.

**Scénario 2 — Changer ma langue (négociation de locale)**
- **Étant donné** un utilisateur dont `locale=fr-FR`
- **Quand** il met à jour sa `locale` via `PATCH /api/me`
- **Alors** la préférence est persistée et **prime** sur `Accept-Language` pour les réponses ultérieures et les e-mails
- **Et** comme seule `fr-FR` est livrée, toute autre locale demandée retombe sur `fr-FR` (fallback) sans erreur ni changement de code requis pour ajouter une langue (architecture i18n).

**Scénario 3 — Modifier mes champs chiffrés**
- **Étant donné** un utilisateur authentifié
- **Quand** il renseigne/modifie `full_name` et `phone`
- **Alors** ces champs sont **rechiffrés** au repos (🔒) ; un dump SQL ne révèle pas les valeurs
- **Et** une entrée de **registre de données** existe pour chacun (sinon le test CI de cohérence échoue).

**Scénario 4 — Accès refusé à un autre compte**
- **Étant donné** un utilisateur A authentifié
- **Quand** il tente d'accéder/modifier le profil d'un utilisateur B
- **Alors** l'API ne propose aucun moyen de cibler un autre compte (`/api/me` est implicitement scopé à l'utilisateur du token) ; toute tentative renvoie `403/404`.

---

### US-RGPD-0 — Squelette RGPD & registre des données (lot technique)

**En tant qu'**équipe responsable de la conformité, **je veux** un squelette du RGPD Manager et un registre des données personnelles initialisé et vérifié en CI **afin de** garantir qu'aucun champ personnel n'échappe au registre dès le premier lot.

> US technique propre au Lot 0 (cdc §9 « Squelette du RGPD Manager + registre-donnees.md initialisé »). Le RGPD Manager fonctionnel est au Lot 8.

**Critères d'acceptation**

**Scénario 1 — Registre initialisé**
- **Étant donné** les entités du Lot 0 (`User`, `Membership`, `Family`, `Invitation`, …)
- **Quand** la CI s'exécute
- **Alors** `/backend/docs/registre-donnees.md` existe et contient une entrée pour **chaque** champ personnel introduit (`full_name`, `phone`, `Invitation.email`, `User.email`, etc.) avec catégorie, sensibilité (🔒 si chiffré), base légale, finalité, durée de conservation, destinataires.

**Scénario 2 — Test de cohérence CI échoue si un champ personnel n'a pas d'entrée**
- **Étant donné** un développeur ajoutant un champ personnel sur une entité **sans** entrée de registre
- **Quand** la CI s'exécute
- **Alors** le **test de cohérence** échoue avec un message clair indiquant le champ manquant
- **Et** la CI passe seulement une fois l'entrée de registre ajoutée (attribut déclaratif + ligne dans `registre-donnees.md`).

**Scénario 3 — AuditLog branché sur l'auth**
- **Étant donné** les événements d'auth (register, login, logout, reset, verify, lock)
- **Quand** ils surviennent
- **Alors** un `AuditLog` **append-only** est écrit (aucune modification/suppression possible), avec acteur, action, IP, horodatage `Europe/Paris`.

**Scénario 4 — Endpoints RGPD non encore exposés**
- **Étant donné** que le RGPD Manager complet est au Lot 8
- **Quand** on appelle `/api/admin/rgpd/*` ou `POST /api/me/export` / `DELETE /api/me` au Lot 0
- **Alors** ils renvoient **`501 Not Implemented`** (RFC 7807), **politique unique** (décision D7 §0.5) : ces endpoints **sont routés et figurent dans l'OpenAPI** (donc dans le client Dart généré) mais ne font rien ; **jamais de `404`** pour un endpoint « pas encore prêt » (le `404` reste réservé aux ressources réellement inexistantes), afin d'éviter toute incohérence OpenAPI / client généré
- **Et** aucune donnée n'est exportée/supprimée prématurément.

---

### US-INFRA-0 — Démarrer la stack et la CI (lot technique)

**En tant que** développeur, **je veux** démarrer toute la stack (Docker ou bare-metal) et obtenir une CI verte **afin de** contribuer sans friction.

**Critères d'acceptation**

**Scénario 1 — Démarrage Docker en une commande**
- **Étant donné** un poste avec Docker
- **Quand** j'exécute `docker compose up`
- **Alors** PostgreSQL 16, Redis, Mercure, MailHog, PHP (FrankenPHP/PHP-FPM), worker Messenger et scheduler démarrent
- **Et** l'app web répond, `/api/docs.json` sert l'OpenAPI, MailHog reçoit les e-mails.

**Scénario 2 — Installation bare-metal reproductible**
- **Étant donné** un poste sans Docker mais avec les prérequis listés dans `INSTALL.md`
- **Quand** je suis le guide pas à pas (`/infra/INSTALL.md`)
- **Alors** j'obtiens une installation fonctionnelle (DB + migrations + clés JWT + Mercure + workers + assets web + SDK Flutter) sans étape manquante
- **Et** la vérification finale du guide (santé) passe.

**Scénario 3 — Génération et consommation de l'OpenAPI par Flutter**
- **Étant donné** le backend démarré
- **Quand** j'exporte l'OpenAPI dans `/contracts` et génère le client Dart
- **Alors** le client Flutter compile et appelle l'auth (register/login/refresh) avec succès
- **Et** la CI échoue si le client généré n'est plus aligné avec l'OpenAPI (drift détecté).

**Scénario 4 — CI verte de référence**
- **Étant donné** une PR
- **Quand** la CI s'exécute
- **Alors** lint + PHPStan + `dart analyze` + PHPUnit (≥1 test API vert) + Playwright (≥1 test E2E vert) + flutter test passent
- **Et** la couverture atteint le seuil **tranché au Lot 0 (décision D8 §0.5) : backend ≥ 80 % de lignes, mobile ≥ 70 %** ; sinon la CI échoue.

---

## 3. Algorithme(s)

> Notations : « TX » = transaction. Les opérations critiques sont **idempotentes** quand indiqué. Toutes les dates en `Europe/Paris` (stockage en UTC, présentation en `Europe/Paris`).

### 3.1 Flux d'inscription + vérification e-mail

```
ENTRÉE : email, password (+ full_name/phone optionnels), locale (négociée)
1. Valider l'entrée (format e-mail, politique mot de passe, locale connue → sinon fr-FR).
   - Échec → 422 RFC 7807 (messages localisés). FIN.
2. Normaliser l'e-mail (citext, trim, lowercase).
3. Rate-limit register (par IP) : si dépassé → 429 Retry-After. FIN.
4. TX :
   a. Rechercher User par email.
      - SI existe :
          - NE PAS créer de doublon.
          - Réponse non énumérante (succès apparent). 
          - (option) Émettre e-mail "tentative de création" au compte existant.
          - COMMIT (no-op sur le compte). FIN.
      - SINON :
          - Hacher password en Argon2id.
          - Chiffrer full_name/phone (AES-256-GCM) si fournis. 🔒
          - Créer User { status=pending, email_verified_at=null, locale }.
          - Générer un token de vérification : valeur aléatoire forte ; STOCKER LE HACHÉ (jamais le clair) avec expires_at.
          - AuditLog: user.registered (append-only).
   b. COMMIT.
5. Dispatch asynchrone (Messenger) : e-mail de vérification localisé.
   - Lien e-mail = page web GET /verify-email?token=<token clair> (décision D5 §0.5).
   - Deep link mobile = coparentplanner://verify-email?token=<token clair>.
   - Token clair à usage unique, expirant.
6. Réponse 201/200 → écran "vérifiez votre e-mail". AUCUN token d'accès/refresh n'est délivré (décision D1 §0.5).

VÉRIFICATION :
0. La page GET /verify-email (web) / le deep link (mobile) extrait le token clair et appelle POST /api/auth/verify-email { token } (endpoint API unique, décision D5 §0.5).
1. Recevoir le token clair.
2. Hacher le token et rechercher l'enregistrement correspondant.
   - introuvable / expiré / déjà consommé → message localisé "lien invalide ou expiré" + action renvoyer. FIN.
3. TX :
   - email_verified_at = now (Europe/Paris en présentation, UTC en base).
   - status = active.
   - Invalider le token (usage unique).
   - AuditLog: user.email_verified.
4. Rediriger : si l'utilisateur n'a aucune famille → onboarding US-ONB-1 ; sinon → accueil.
```
Idempotence : une seconde vérification avec un token déjà consommé n'a aucun effet (étape 2 → FIN).

### 3.2 Flux de connexion + verrouillage après N tentatives

```
ENTRÉE : email, password
1. Rate-limit login (clé = IP + email normalisé) :
   - SI compteur d'échecs ≥ N sur la fenêtre glissante W → 429 Retry-After ;
     AuditLog: user.login_locked ; FIN. (Verrouillage temporel, se lève à expiration de W.)
2. Rechercher User par email normalisé.
   - Calcul en TEMPS CONSTANT autant que possible (comparer un hash factice si user absent) pour éviter l'oracle temporel.
3. SI user absent OU password invalide (Argon2id verify) OU status interdit :
   - Incrémenter compteur d'échecs.
   - AuditLog: user.login_failed.
   - Réponse 401 message GÉNÉRIQUE localisé identique dans tous les cas (non énumérant). FIN.
4. SI status=pending (e-mail non vérifié) :
   - REFUSER (403) : message "vérifiez votre e-mail" + action "renvoyer". AUCUN token émis (décision D1 §0.5 ; pas d'accès restreint).
   - AuditLog: user.login_failed (motif: email_not_verified, non révélé à l'utilisateur). FIN.
5. SUCCÈS :
   - Réinitialiser le compteur d'échecs.
   - Émettre JWT d'accès (court, ex. 15 min) + refresh token (rotatif) appartenant à une "famille de tokens" (token family id).
   - last_login_at = now.
   - AuditLog: user.login_success.
   - Réponse 200 { accessToken, refreshToken, expiresIn }.
```
Complexité : O(1) par requête ; le coût dominant est le hachage Argon2id (volontairement coûteux). Le rate-limit borne le nombre d'appels.

### 3.3 Refresh rotatif + détection de réutilisation

```
ENTRÉE : refreshToken
1. Valider la signature/format + recherche du token (par identifiant + hash stocké).
   - introuvable → 401. FIN.
2. SI token déjà RÉVOQUÉ (donc déjà tourné) :
   - => suspicion de vol/rejeu.
   - Révoquer TOUTE la famille de tokens (token family id) → invalidation globale de la session.
   - AuditLog: user.refresh_reuse_detected.
   - 401. FIN.
3. SI token EXPIRÉ → 401 (forcer re-login). FIN.
4. TX :
   - Émettre un NOUVEAU refresh token (même token family id, génération +1).
   - RÉVOQUER l'ancien refresh token (rotation).
   - Émettre un nouveau JWT d'accès.
5. Réponse 200 { accessToken, refreshToken, expiresIn }.
```
Invariant : un refresh token ne sert **qu'une fois**. La rotation + révocation d'ancien token est faite dans la même TX (atomique). Toute réutilisation déclenche l'invalidation de la famille (défense en profondeur).

### 3.4 Réinitialisation de mot de passe

```
DEMANDE (forgot) :
1. Rate-limit forgot (IP + email). Dépassé → 429.
2. Rechercher User par email (sans révéler le résultat).
3. SI existe :
   - Générer token de reset aléatoire fort ; STOCKER LE HACHÉ + expires_at ; invalider d'éventuels tokens de reset précédents.
   - Dispatch asynchrone : e-mail de reset localisé.
     - Lien e-mail = page web GET /reset-password?token=<token clair> ; deep link mobile = coparentplanner://reset-password?token=<token clair> (décision D5 §0.5).
   - AuditLog: user.password_reset_requested.
4. Réponse TOUJOURS un succès générique localisé (anti-énumération), que le compte existe ou non.

RESET :
0. La page GET /reset-password (web) / le deep link (mobile) recueille le nouveau mot de passe et appelle POST /api/auth/password/reset { token, password } (endpoint API unique, décision D5 §0.5).
1. Recevoir token clair + nouveau mot de passe.
2. Valider la politique du nouveau mot de passe. Échec → 422.
3. Hacher le token, rechercher l'enregistrement.
   - introuvable / expiré / consommé → 400/410 "lien invalide ou expiré". FIN.
4. TX :
   - password_hash = Argon2id(nouveau).
   - Invalider le token de reset (usage unique).
   - RÉVOQUER tous les refresh tokens de l'utilisateur (déconnexion globale).
   - AuditLog: user.password_reset.
5. Dispatch asynchrone : e-mail "mot de passe modifié".
6. Réponse 200.
```

### 3.5 Négociation de locale

```
ORDRE DE PRIORITÉ (du plus fort au plus faible) :
1. Préférence utilisateur (User.locale) si authentifié.
2. Header Accept-Language de la requête.
3. Locale par défaut applicative = fr-FR.

ALGO :
1. candidate = User.locale (si auth) sinon parse(Accept-Language).
2. SI candidate ∈ locales livrées { fr-FR } → utiliser candidate.
   SINON → fr-FR (fallback).
3. Toutes les chaînes (UI/erreurs/e-mail) sont résolues via le catalogue ICU de la locale retenue.
```
Invariant i18n : ajouter une langue = ajouter un catalogue de ressources, **sans** changer ce code (le set « locales livrées » est une config). Format ICU pour pluriels/genre. Dates/nombres formatés selon la locale et le fuseau `Europe/Paris`.

### 3.6 Validation du header `X-Family-Id` (scope famille)

```
S'applique à TOUTE ressource métier scopée (pas aux endpoints d'auth/compte non scopés).
ENTRÉE : utilisateur authentifié (du JWT), header X-Family-Id.
1. SI la ressource est scopée ET X-Family-Id ABSENT :
   - 400/422 RFC 7807 "X-Family-Id requis". FIN.
2. SI X-Family-Id présent mais mal formé (UUID invalide) :
   - 400 RFC 7807. FIN.
3. Charger le Membership (user, familyId) :
   - INEXISTANT ou status != active → 403 RFC 7807 "famille non autorisée"
     (ne pas distinguer "famille inexistante" de "pas membre" pour éviter l'énumération de familles). FIN.
4. Injecter le contexte { family, membership, role, droits } dans la requête (argument resolver / contexte).
5. La suite (Voters) s'appuie sur ce contexte pour autoriser l'action sur la ressource.
```
Au Lot 0, peu de ressources sont scopées (essentiellement la création/listing de familles et l'accueil), mais le **mécanisme est posé et testé**. Toute requête métier non scopée est interdite (invariant CLAUDE.md).

### 3.7 Chaîne d'autorisation par Voters (squelette)

```
Pour chaque action sur une ressource métier :
1. Résoudre le contexte famille (§3.6) → membership + droits.
2. Déterminer (domaine, niveau requis) de l'action :
   - domaine ∈ { custody, agenda, children_notes, expenses, exchanges, family_settings }
   - niveau ∈ { view, edit }
3. Voter du domaine décide sur (membership, ressource, niveau requis) :
   - role=parent → FORCE edit (accès accordé pour view/edit).
   - role=child  → JAMAIS expenses ; JAMAIS edit de structure (custody/membres/réglages) ; au mieux view sur domaines autorisés.
   - role=step_parent → selon Right.level du domaine (none|view|edit).
   - is_admin requis pour family_settings=edit.
4. SI refusé → 403 RFC 7807 (ex. { type:"/errors/forbidden", detail:"Droit 'agenda:edit' requis." }).
```
Au Lot 0, les Voters sont **implémentés et testés unitairement** (table de vérité §2.2) même si peu de ressources métier les invoquent encore. Le rôle `parent` forçant `edit` et le `child` privé d'`expenses`/édition de structure sont vérifiés par test.

### 3.8 Pipeline CI

```
DÉCLENCHEUR : push / PR.
JOBS (parallélisables quand indépendants) :
A. Backend :
   1. composer install.
   2. php-cs-fixer --dry-run (échec si non formaté).
   3. PHPStan niveau élevé (échec si erreurs).
   4. Préparer DB de test (PostgreSQL service CI), exécuter migrations.
   5. Charger fixtures de base.
   6. php bin/phpunit (unitaire + fonctionnel/API). ≥1 test API vert (auth).
   7. Mesurer la couverture ; échec si < seuil.
   8. Test de cohérence du registre RGPD (échec si champ personnel sans entrée).
   9. Exporter l'OpenAPI → /contracts ; vérifier qu'il est à jour (diff = échec).
B. Web E2E :
   1. Build assets (AssetMapper).
   2. Démarrer l'app + services (DB, Mercure, MailHog) en CI.
   3. Playwright : ≥1 parcours (inscription → vérif e-mail via MailHog → connexion). Vert obligatoire.
C. Mobile :
   1. Régénérer le client Dart depuis l'OpenAPI de /contracts ; échec si drift vs client commité.
   2. dart format --set-exit-if-changed.
   3. dart analyze (échec si warnings selon config).
   4. flutter test (unitaires + widget). 
   5. (option CI) build iOS/Android (au moins compilation).
RÉSULTAT : tous verts → mergeable. Un seul rouge → bloquant.
```
Ordre : A, B, C peuvent tourner en parallèle ; C dépend de l'OpenAPI produit par A (artefact partagé ou régénération). Idempotence : la CI est reproductible (mêmes entrées → même verdict).

### 3.9 Génération + consommation de l'OpenAPI (alignement des types)

```
1. API Platform expose nativement l'OpenAPI : GET /api/docs.json (et UI /api/docs).
2. Étape build : commande backend exporte le schéma figé → /contracts/openapi.json (versionné).
3. Étape mobile : un générateur (ex. openapi-generator / swagger-dart-code-generator) produit le client Dart (dio) dans /mobile à partir de /contracts/openapi.json.
4. Le client généré est commité ET vérifié en CI : régénérer puis comparer ; si différence → échec (le contrat a changé sans régénération).
5. Intercepteurs ajoutés au client (non générés) : 
   - Authorization: Bearer <accessToken> (+ refresh auto sur 401 via /api/auth/refresh, avec rotation).
   - Accept-Language (locale).
   - X-Family-Id (famille active courante).
   - Retry idempotent (avec Idempotency-Key pour les POST issus de l'outbox).
```
Invariant : la **source de vérité des types** est l'OpenAPI exporté par le backend ; le mobile ne définit jamais ses DTO à la main pour les ressources couvertes.

### 3.10 Bootstrap offline outbox (squelette mobile)

```
OBJECTIF Lot 0 : poser le schéma et la machine d'état, pas tous les cas métier (complet au Lot 8).
SCHÉMA Drift :
  - Table outbox_entry { id, method, path, body(json), headers, idempotency_key, created_at,
                         status (pending|sending|done|conflict|failed), attempts, last_error }.
  - Tables de cache lecture (par ressource) — minimal au Lot 0.

ENVOI D'UNE MUTATION (squelette) :
1. Construire la requête ; générer Idempotency-Key (UUID) ; INSERT outbox_entry(status=pending).
2. SI en ligne : tenter l'envoi immédiat (status=sending).
   - 2xx → status=done ; appliquer la réponse canonique au cache local.
   - 409/conflit (cas garde/décisions au Lot 8) → status=conflict ; signaler à l'utilisateur ; conserver l'état serveur canonique.
   - 5xx/réseau → status=failed/pending ; replanifier (backoff).
3. SI hors ligne : rester status=pending ; afficher "en attente de synchro".

REJOUE À LA RECONNEXION :
1. Détecter le retour en ligne (connectivité).
2. Itérer les outbox_entry pending/failed dans l'ORDRE de création.
3. Renvoyer avec le MÊME Idempotency-Key (le serveur dé-duplique : même clé → même résultat, pas d'effet double).
4. Mettre à jour le statut local selon la réponse.
RÉSOLUTION DE CONFLITS (politique, détaillée Lot 8) :
   - Autorité serveur ; last-write-wins sur champs simples ; refus possible signalé pour garde/décisions.
```
Au Lot 0 : on **prouve** l'aller-retour register/login en ligne, et on **pose** la table outbox + l'`Idempotency-Key` + le statut « en attente de synchro » ; les mutations métier offline sont validées au Lot 8.

### 3.11 Idempotence côté serveur (clé d'idempotence)

```
Sur les POST acceptant Idempotency-Key (issus de l'outbox) :
1. SI header Idempotency-Key présent :
   - Rechercher une réponse déjà enregistrée pour (clé, utilisateur, endpoint).
     - TROUVÉE → renvoyer la réponse mémorisée (même status/body), SANS ré-exécuter l'effet.
     - ABSENTE → exécuter la requête en TX ; enregistrer (clé → réponse) ; renvoyer.
2. La clé a une fenêtre de rétention (purge ultérieure, Lot 8).
```
Garantit qu'un rejeu réseau (mobile) ne crée pas de doublon. Au Lot 0, peu d'endpoints en POST métier, mais le mécanisme est posé.

### 3.12 JWT Mercure : négociation et scoping des claims (mécanisme posé)

> cdc §4.5 : « Les abonnements sont autorisés par un **JWT Mercure** dont les *claims* listent les topics correspondant aux familles du membre. » Au Lot 0, **aucun topic métier** n'est encore poussé (exchanges/custody/agenda/notes arrivent aux lots 2/4/6) ; on **pose le mécanisme** d'émission et de scoping du JWT Mercure de façon à ce qu'il évolue sans changement structurel.

```
PRINCIPE : le JWT Mercure est un jeton DISTINCT du JWT d'accès API. Il est émis par
le backend (clé Mercure côté serveur), jamais forgé par le client. Ses claims `subscribe`
listent UNIQUEMENT les topics que le membre a le droit d'écouter.

ÉMISSION (endpoint dédié, ex. GET /api/realtime/token, auth requise) :
1. Exiger un JWT d'accès API valide (utilisateur authentifié et vérifié).
2. Résoudre les memberships ACTIFS de l'utilisateur (status=active).
3. SI une famille active est fournie (X-Family-Id) ET que le membership (user, famille) est actif :
   - claims.subscribe = liste des topics de CETTE famille pour les domaines autorisés
     par les Voters du membre (lecture). Au Lot 0 : LISTE VIDE — aucun topic métier
     n'existe encore. Le mécanisme calcule donc un ensemble vide, sans erreur.
4. SINON (pas de famille active résolue, ou aucun membership) :
   - claims.subscribe = [] (VIDE). Le JWT Mercure initial NE PORTE AUCUN TOPIC MÉTIER
     tant que les memberships ne sont pas résolus / tant qu'aucune famille active n'est choisie.
5. Le JWT Mercure a sa propre expiration courte ; il est ré-émis (re-fetch) à expiration
   et à chaque changement de famille active (voir ci-dessous).

ÉVOLUTION AU CHANGEMENT DE FAMILLE ACTIVE :
1. Quand l'utilisateur bascule de famille (sélecteur web / mobile, nouveau X-Family-Id) :
   - Le client RÉ-OBTIENT un JWT Mercure pour la nouvelle famille active
     (les claims `subscribe` sont recalculés pour les topics de la nouvelle famille).
   - L'abonnement Mercure précédent est fermé ; un nouveau est ouvert avec le nouveau jeton.
2. Aucun topic d'une autre famille ne figure jamais dans les claims (pas de fuite inter-familles).

INVARIANTS :
 - Le serveur est SEULE autorité sur le contenu de `subscribe` (jamais le client).
 - Un membership RETIRÉ ou rétrogradé (Lot 1) cesse d'apparaître dans les claims dès la
   PROCHAINE émission du JWT Mercure ; les abonnements en cours expirent avec le jeton court
   (voir §4.11 pour la révocation des sessions).
 - Au Lot 0, l'ensemble des topics est volontairement vide : on prouve l'émission, le scoping
   par famille active et le recalcul à la bascule, SANS aucun topic métier réel.
```
Au Lot 0 : la plomberie Mercure (binaire démarrable) + l'**émission d'un JWT Mercure scopé (claims vides)** + le **recalcul à la bascule de famille** sont posés et testés ; les topics métier sont ajoutés à leurs lots (§4.5).

---

## 4. Cas limites (edge cases)

> Organisés par thème. Pour chacun : situation → comportement attendu.

### 4.1 Dates, fuseaux, heure d'été/hiver, années bissextiles
- **Fuseau par défaut** : tout calcul/affichage de date se fait en `Europe/Paris` ; stockage en UTC. Aucune date métier n'est codée en dur (la date des maquettes 23/06/2026 ne doit jamais apparaître en dur — cdc §10).
- **Changement d'heure été→hiver / hiver→été** : les horodatages d'audit, d'expiration de token (vérification/reset/JWT/refresh) et de rate-limit doivent rester corrects autour des transitions DST. Une expiration calculée en UTC ne « saute » pas d'une heure ; l'affichage en `Europe/Paris` reflète le bon mur-horloge. Test : token émis avant la transition, validé après.
- **Jour inexistant / ambigu (DST)** : éviter d'instancier des dates locales sur l'heure « sautée » (2h→3h) pour des deadlines ; raisonner en instants UTC.
- **Année bissextile** : calculs d'expiration et de fenêtres traversant le 29 février ne plantent pas (peu de durées longues au Lot 0, mais les utilitaires de date doivent en tenir compte pour les lots suivants).
- **Horloge serveur** : toute la logique d'expiration s'appuie sur l'horloge serveur (UTC) ; le client ne décide pas de l'expiration.

### 4.2 Concurrence & idempotence
- **Double soumission d'inscription** (clic rapide / double tap mobile) : l'unicité `email` (citext) + la réponse non énumérante garantissent un seul compte ; pas d'erreur 500.
- **Double clic sur le lien de vérification** : seconde vérification = no-op (token déjà consommé) ; message neutre.
- **Refresh concurrent** (deux requêtes avec le même refresh token) : la rotation atomique fait qu'une seule réussit ; l'autre voit un token révoqué → **traité comme réutilisation (invalidation de la famille de tokens)**. **Politique stricte, sans fenêtre de grâce** (décision D3 §0.5 : sécurité d'abord ; aucune tolérance concurrente n'est implémentée au Lot 0). Côté client, le refresh est **sérialisé** (mutex/single-flight) pour éviter de déclencher l'auto-invalidation lors d'un double 401 simultané.
- **Création de famille en double** (US-ONB-1) : protégée par `Idempotency-Key` si issue de l'outbox ; sinon, double POST crée deux familles (acceptable au Lot 0, l'utilisateur voit deux familles et peut en supprimer une au Lot 1) — à mitiger par idempotence côté client.
- **Acceptation concurrente d'une invitation** : la consommation du token est atomique ; un seul membership créé ; la seconde tentative voit le token consommé.
- **Outbox rejoué deux fois** : l'`Idempotency-Key` côté serveur dé-duplique (§3.11) — pas d'effet double.

### 4.3 Autorisations (parent / beau-parent / enfant / admin / dernier admin)
- **Parent** : Voters forcent `edit` partout ; un test vérifie qu'un parent passe tous les domaines en `edit`.
- **Beau-parent** : accès selon `Right.level` du domaine ; un test vérifie `none` → refus, `view` → lecture seule, `edit` → écriture.
- **Enfant** : `expenses` **toujours none** (forcé) ; jamais d'édition de structure (custody/membres/réglages) ; un test vérifie le refus systématique. `children_notes` par défaut `none`.
- **Admin** : requis pour `family_settings=edit`. Un parent non admin ne peut pas éditer les réglages famille.
- **Dernier admin protégé** : bien que la gestion des memberships soit approfondie au Lot 1, l'invariant est **posé** : on ne peut ni retirer ni rétrograder le dernier admin d'une famille. Au Lot 0, US-ONB-1 crée un parent+admin ; toute future opération de rétrogradation devra refuser si c'est le dernier admin (test de garde-fou posé tôt si l'endpoint existe, sinon documenté pour le Lot 1).
- **Token JWT d'une famille A utilisé avec X-Family-Id de famille B** : refusé (`403`) car aucun membership actif (user, B).

### 4.4 Scope famille (`X-Family-Id`)
- **Header absent sur ressource scopée** → `400/422` « X-Family-Id requis ».
- **UUID malformé** → `400`.
- **Famille inexistante OU utilisateur non membre** → `403` indistinct (anti-énumération de familles).
- **Membership `invited`/inactif** → `403` (pas encore actif).
- **Bascule de famille** : aucune donnée de la famille précédente ne doit fuiter après changement de `X-Family-Id` (test E2E web sur le sélecteur).
- **Utilisateur sans aucune famille** : tout endpoint scopé échoue proprement ; l'UI redirige vers l'onboarding.

### 4.5 Valeurs vides / bornes / limites
- **Mot de passe** : trop court / trop long (borne haute raisonnable pour éviter le DoS Argon2id) → `422` localisé.
- **E-mail** : trop long, sans `@`, espaces → `422` ; normalisation (trim/lowercase/citext).
- **`full_name`/`phone`** : vides autorisés (optionnels) ; valeurs très longues bornées ; caractères Unicode acceptés ; chiffrés même si courts.
- **Nom de famille (Family.name)** : vide refusé ; longueur bornée.
- **`type` de famille** hors énum → `422`.
- **Locale** inconnue → fallback `fr-FR` (pas d'erreur).
- **`User.avatar_url` non alimenté au Lot 0** : la colonne `avatar_url` existe (cdc §5.2) mais **aucun upload d'avatar n'est implémenté au Lot 0**. Le stockage objet **S3-compatible UE** (cdc §4.8) destiné aux **avatars et exports** est **posé en configuration/infra mais non câblé** (pas de bucket consommé en dev). Conséquences attendues : `GET /api/me` renvoie `avatar_url=null` ; tout `PATCH /api/me` sur `avatar_url` est **ignoré ou refusé** (`422` si une valeur est soumise) ; aucune URL externe non-UE n'est acceptée. L'upload réel d'avatar et la génération d'exports signés (liens temporaires) arrivent avec leurs lots (avatar : Lot 1/2 selon UI ; exports : Lot 8 RGPD). Rappel RGPD : le bucket d'avatars/exports devra être **UE** (cdc §4.8) et chiffré au repos.
- **Payload JSON malformé** → `400` RFC 7807, pas de stacktrace exposée.

### 4.6 Échecs externes (e-mail, API vacances, Mercure hors-ligne)
- **Échec d'envoi d'e-mail** (SMTP/MailHog indisponible) : l'e-mail étant **asynchrone** (Messenger), l'inscription/forgot **ne bloque pas** ; le message est réessayé (retry/backoff Messenger) ; en cas d'échec persistant, il part en file d'échec (dead-letter) et un `AuditLog`/log est écrit. L'utilisateur voit toujours « vérifiez votre e-mail » (UX non bloquante).
- **API vacances scolaires** : **hors périmètre Lot 0** (Lot 3). Rappel d'invariant : l'adaptateur `SchoolHolidaysProvider` devra être isolé, caché, et **l'endpoint/schéma réel vérifié** avant codage (ne pas coder en dur). Aucun appel à cette API au Lot 0.
- **Mercure hors-ligne** : au Lot 0, aucun topic métier n'est encore poussé (claims `subscribe` vides, §3.12) ; si Mercure est indisponible, l'app reste fonctionnelle (auth/shell ne dépendent pas du temps réel). La plomberie (émission/scoping du JWT Mercure) est posée mais sa panne ne bloque pas l'auth. Le web dégrade proprement (pas d'erreur visible bloquante).
- **Redis/Messenger indisponible** : si le transport asynchrone est down, l'app doit dégrader (les e-mails ne partent pas immédiatement) sans erreur 500 sur les parcours d'auth ; documenter le comportement attendu (file persistée si transport Doctrine).

### 4.7 Conflits offline (mobile)
- **Mutation créée hors-ligne puis rejouée** : `Idempotency-Key` empêche le doublon (§3.11). Au Lot 0, on valide le mécanisme sur un cas simple ; les conflits métier (garde/décisions) sont au Lot 8.
- **Token expiré pendant que la file outbox est en attente** : au rejoue, un `401` déclenche le refresh (rotatif) puis la requête est retentée ; si le refresh échoue (révoqué), l'utilisateur est invité à se reconnecter et l'outbox reste en attente.
- **Changement de famille active pendant la file** : chaque outbox_entry porte le `X-Family-Id` figé au moment de la création (pas de re-scoping silencieux).
- **Conflit serveur (409)** : statut `conflict` affiché, état serveur canonique conservé (politique détaillée Lot 8).

### 4.8 Sécurité / RGPD
- **Anti-énumération** : register, login, forgot renvoient des réponses non énumérantes (cf. US). Test dédié vérifiant l'indistinction des réponses e-mail existant/inexistant.
- **Tokens** (vérification, reset, invitation) : stockés **hachés** ; jamais en clair en base ; à usage unique ; expirants ; rate-limités.
- **Refresh** : rotatif ; réutilisation détectée → invalidation famille (§3.3).
- **Champs 🔒** (`full_name`, `phone`, `Invitation.email`, et plus tard `Message.text`, `Note.text`) : chiffrés AES-256-GCM ; **un dump SQL ne révèle ni identités ni contenus** (test : insérer puis lire le brut DB → valeurs illisibles). Clés jamais en base (coffre/KMS UE).
- **AuditLog** append-only : aucune route/ORM ne permet update/delete ; test vérifiant l'immuabilité.
- **Pas de données de santé** : aucun champ typé santé n'est introduit au Lot 0 (et nulle part). Aucune US n'invente de donnée médicale. (Le bandeau anti-santé concerne l'écran enfant, Lot 2 — pas ce lot.)
- **Registre de données** : tout champ personnel ajouté au Lot 0 a une entrée ; test de cohérence CI bloquant.
- **Hébergement UE** : rappel d'invariant (config infra) ; aucune dépendance hors-UE pour les données (MailHog/dev local ; en prod, SMTP/objets UE).
- **Fuite via messages d'erreur** : RFC 7807 sans stacktrace en prod ; pas d'info technique sensible dans `detail`.
- **CSRF** : l'API consommée en **Bearer** (mobile) n'est pas concernée. Côté **web**, les tokens étant posés en **cookies `HttpOnly` `SameSite=Lax`** (décision D2 §0.5), une **protection CSRF est requise** : token CSRF Symfony sur tous les formulaires d'auth web server-rendered (login, register, forgot, reset) **et** double-submit/`SameSite=Lax` sur les requêtes mutantes pilotées par Turbo/Stimulus. Décision tranchée (plus de « à figer »).

### 4.9 i18n / localisation
- **Locale par défaut** `fr-FR` ; **fallback** systématique si locale demandée non livrée (pas d'erreur, pas de chaîne brute affichée).
- **E-mails localisés** : vérification, reset, confirmation de changement — tous via templates Twig + catalogue ICU ; testés (MailHog) en `fr-FR`.
- **Aucune chaîne en dur** : UI web (Twig), UI mobile (ARB), erreurs API → toutes externalisées. Test/lint repérant des chaînes en dur dans les écrans d'auth (au minimum revue).
- **Pluriels/genre ICU** : utiliser le format ICU même si `fr-FR` seule (ex. « 1 tentative restante » / « 3 tentatives restantes »).
- **Formats date/nombre** : selon la locale et le fuseau `Europe/Paris`.
- **Ajout d'une langue** : doit se faire par ajout de catalogue **sans modifier le code** (vérifié conceptuellement : le set de locales livrées est une configuration).

### 4.10 Divers / robustesse
- **Méthode HTTP non supportée** → `405`.
- **Content-Type non JSON sur endpoint JSON** → `415`/`400`.
- **OpenAPI désaligné** (client Dart non régénéré) → CI échoue (drift).
- **Migration manquante** (entité modifiée sans migration) → CI/contrôle Doctrine échoue.
- **Variables d'environnement manquantes** (clé JWT, clé de chiffrement, DSN) → l'app refuse de démarrer avec un message explicite (fail-fast), jamais de démarrage en mode non sécurisé.

### 4.11 Révocation / expiration de session (retrait de membership, changement de rôle)

> Le **retrait d'un membership** et le **changement de rôle/droits** sont fonctionnellement traités au **Lot 1** (PATCH/DELETE `/api/memberships/{id}`). Mais leur **impact sur les JWT/refresh déjà émis** doit être anticipé dès le Lot 0, car la stratégie d'auth (JWT stateless court + refresh rotatif révocable) est posée ici et ne doit pas être reprise.

- **Principe (posé au Lot 0)** : l'**autorisation est revérifiée à chaque requête** côté serveur (scope `X-Family-Id` §3.6 + Voters §3.7) à partir de l'**état courant** des memberships en base — **jamais** à partir de claims figés dans le JWT d'accès. Conséquence : le JWT d'accès **ne porte pas** la liste des familles ni les droits ; il ne porte que l'identité (`sub`). Un membership retiré ou rétrogradé prend donc effet **immédiatement** pour toute requête métier scopée, **sans** attendre l'expiration du JWT.
- **JWT d'accès court** : sa **durée de vie courte** (≈ 15 min, §3.2) borne la fenêtre où des claims éventuels seraient périmés ; mais comme l'autorisation métier est revérifiée en base à chaque appel (ci-dessus), un retrait/rétrogradation n'est **pas** masqué par un JWT encore valide.
- **Refresh token** : reste valide jusqu'à expiration/rotation. Le retrait d'un **membership** (d'**une** famille) ne révoque **pas** le refresh (l'utilisateur garde son compte et ses autres familles). En revanche, les opérations **comptes** (reset mot de passe, logout, suppression de compte au Lot 8) révoquent les refresh (§3.4, §3 US-AUTH-2). La **désactivation/suppression du compte utilisateur** (Lot 8) doit révoquer **toute** la famille de tokens.
- **JWT Mercure** : un membership retiré/rétrogradé cesse d'apparaître dans les claims `subscribe` à la **prochaine émission** du jeton Mercure (jeton court, §3.12) ; les abonnements en cours expirent avec lui (pas de révocation forcée d'un flux SSE déjà ouvert au Lot 0).
- **À faire au Lot 1 (note d'anticipation, non implémenté ici)** : exposer un mécanisme de **révocation explicite** (invalidation de la famille de tokens d'un utilisateur, ou liste de révocation par `token_family_id`) déclenchable lors d'une **désactivation de compte** ou d'un changement de rôle sensible, pour ne pas dépendre uniquement de l'expiration courte. Le schéma `RefreshToken` (avec `token_family_id` et `revoked_at`, §5.1) **permet déjà** cette révocation : aucune migration structurelle ne sera nécessaire au Lot 1.

---

## 5. Modèle de données touché

> Référence : cdc §5.2. Tous : `id` (UUID), `created_at`, `updated_at`. 🔒 = chiffré au repos (AES-256-GCM). Le Lot 0 crée le **sous-ensemble** strictement nécessaire à l'auth, à l'onboarding, au scope famille, à l'audit et au squelette RGPD. Les autres entités viennent aux lots dédiés.

### 5.1 Entités créées (complètes ou squelette) au Lot 0

| Entité | Champs (Lot 0) | Notes / contraintes |
|---|---|---|
| **User** (complète) | `email` (unique, **citext**), `email_verified_at` (nullable), `password_hash` (Argon2id), `full_name` 🔒 (nullable), `phone` 🔒 (nullable), `locale` (défaut `fr-FR`), `avatar_url` (nullable), `last_login_at` (nullable), `status` (`pending`\|`active`\|… ) | Unicité `email`. Champs 🔒 chiffrés. Entrées de registre obligatoires (`email`, `full_name`, `phone`). |
| **RefreshToken** (support auth) | `user_id`, `token_hash`, `token_family_id`, `expires_at`, `revoked_at` (nullable), `replaced_by` (nullable) | Stocké **haché**. Rotation + détection de réutilisation (§3.3). (Entité technique liée à lexik/refresh — nom indicatif.) |
| **OneTimeToken** (support auth, unifié) | `user_id`, `purpose` (`email_verification`\|`password_reset`), `token_hash`, `expires_at`, `consumed_at` (nullable) | **Entité unique typée par `purpose`** (décision tranchée au Lot 0 : une seule table plutôt que deux). Tokens **hachés**, usage unique, expirants. |
| **Family** (squelette minimal) | `name`, `type` (`separated`\|`intact`), `timezone` (défaut `Europe/Paris`), `school_zone` (nullable au Lot 0), `academy` (nullable), `expenses_enabled` (bool, défaut false, v2), `settings` (jsonb) | Créée pour US-ONB-1. Réglages détaillés au Lot 1. |
| **Membership** (squelette) | `user_id`, `family_id`, `role` (`parent`\|`step_parent`\|`child`), `is_admin` (bool), `child_id` (nullable), `display_color` (nullable), `status` (`active`\|`invited`) | **Unicité (`user_id`, `family_id`)**. Sert au scope `X-Family-Id` et aux Voters. Gestion fine au Lot 1. |
| **Right** (squelette) | `membership_id`, `domain` (`custody`\|`agenda`\|`children_notes`\|`expenses`\|`exchanges`\|`family_settings`), `level` (`none`\|`view`\|`edit`) | Posé pour le squelette Voters. Pour `parent`, ignoré/forcé `edit`. Détail au Lot 1. |
| **Invitation** (squelette) | `family_id`, `email` 🔒, `role`, `proposed_rights` (jsonb), `token_hash`, `expires_at`, `status` (`pending`\|`accepted`\|`expired`\|`revoked`), `invited_by` | `email` chiffré 🔒 (entrée de registre obligatoire). Acceptation minimale (US-ONB-1) ; émission complète au Lot 1. Token **haché**. |
| **NotificationPref** (table créée) | `user_id`, `category` (`custody`\|`agenda`\|`expenses`\|`exchanges`\|`invitation`), `channel` (`email`\|`push`), `enabled` | Table posée ; pilotée au Lot 7 (e-mail) / Lot 11 (push). |
| **AuditLog** (créée, append-only) | `actor_user_id`, `family_id` (nullable au Lot 0 pour les events d'auth), `action`, `entity_type`, `entity_id`, `ip`, `metadata` (jsonb), `occurred_at` | **Immuable (append-only)** — aucune mise à jour/suppression. Branché sur les événements d'auth. |
| **RgpdOperation** (table créée) | `type` (`export`\|`erasure`\|`purge`\|`anonymize`\|`report`), `subject_user_id` (nullable), `requested_by`, `status`, `mode` (`live`\|`dry_run`), `result_ref`, `occurred_at` | Table posée pour le squelette RGPD ; alimentée au Lot 8. |

### 5.2 Entités NON créées au Lot 0 (rappel)
`Child`, `CustodyPattern`, `CustodyDay`, `CustodyException`, `SchoolHoliday`, `HolidayRule`, `Event` (+ `event_child`), `Exchange`, `ExchangeOption`, `Message`, `Note`, `Expense`, `ExpenseShare`, `FamilyLink` → créées à leurs lots respectifs (1–9). `RetentionRule` peut être en configuration versionnée (cdc §7.1) plutôt qu'en base.

### 5.3 Champs chiffrés 🔒 introduits au Lot 0
- `User.full_name`, `User.phone`, `Invitation.email`.
- (À venir aux autres lots : `Message.text` Lot 6, `Note.text` Lot 2.)
- Chiffrement applicatif AES-256-GCM ; clés via coffre/KMS UE, jamais en base. Un dump SQL brut ne révèle ni identités ni `email` d'invitation.

### 5.4 Contraintes d'unicité & relations (Lot 0)
- `User.email` **unique** (citext).
- `Membership` **unique (`user_id`, `family_id`)**.
- `Membership.user_id → User`, `Membership.family_id → Family`. La colonne `Membership.child_id` est créée **nullable et SANS contrainte FK** au Lot 0 ; la **FK vers `Child` est ajoutée par migration au Lot 2** quand `Child` est créé (décision D4 §0.5). Au Lot 0, `child_id` reste toujours `NULL`.
- `Right.membership_id → Membership`.
- `Invitation.family_id → Family`, `Invitation.invited_by → Membership/User`.
- `RefreshToken.user_id → User` ; `OneTimeToken.user_id → User` (entité unifiée, `purpose` ∈ {`email_verification`, `password_reset`}).
- `AuditLog.actor_user_id → User` (nullable pour events système), `AuditLog.family_id → Family` (nullable).
- Tous les enums (`type`, `role`, `level`, `domain`, `status`, `channel`, `category`) implémentés comme énumérations PHP backed + contraintes DB (CHECK ou type natif) cohérentes.

---

## 6. Endpoints API concernés

> Référence : cdc §6.1 (conventions) et §6.2 (Auth & compte + amorces familles/invitations). Au Lot 0, on **implémente** les endpoints d'auth/compte et l'amorce d'onboarding ; les endpoints purement métier (garde/agenda/échanges/dépenses) **ne sont pas** implémentés ici.

### 6.1 Conventions transverses (rappel, appliquées dès le Lot 0)
- Base REST/JSON sous `/api`. OpenAPI servi : `GET /api/docs.json` (source de vérité du client Dart).
- **Auth** : `Authorization: Bearer <jwt>` ; refresh via `POST /api/auth/refresh`.
- **Contexte famille** : header `X-Family-Id` (validé contre les memberships) sur les ressources scopées (§3.6).
- **Locale** : `Accept-Language`, sinon préférence utilisateur (§3.5).
- **Pagination** : `?page=&itemsPerPage=` (API Platform), réponses Hydra/JSON.
- **Erreurs** : **RFC 7807** `application/problem+json`, messages **localisés**, sans stacktrace en prod.
- **Idempotence** : header `Idempotency-Key` accepté sur les POST issus de l'outbox (§3.11).

### 6.2 Endpoints implémentés au Lot 0

| Méthode | Chemin | Droit / Accès | Scopé `X-Family-Id` ? | Description (Lot 0) |
|---|---|---|---|---|
| POST | `/api/auth/register` | public | non | Créer un compte (US-AUTH-1). Rate-limité, anti-énumération, e-mail de vérification async. |
| POST | `/api/auth/login` | public | non | Obtenir JWT + refresh (US-AUTH-2). Rate-limit + lockout. |
| POST | `/api/auth/refresh` | public (refresh token) | non | Rafraîchir le token (rotatif, détection de réutilisation). |
| POST | `/api/auth/password/forgot` | public | non | Demander un reset (anti-énumération, rate-limité, async). |
| POST | `/api/auth/password/reset` | public (token) | non | Réinitialiser le mot de passe (token haché usage unique ; révoque tous les refresh). |
| POST | `/api/auth/verify-email` | public (token) | non | Vérifier l'e-mail (token usage unique). **Endpoint API unique** ; appelé par la page web `GET /verify-email?token=…` et par le deep link mobile `coparentplanner://verify-email?token=…` (décision D5 §0.5). |
| POST | `/api/auth/logout` | auth | non | Révoquer le refresh token courant côté serveur ; (web) effacer les cookies `HttpOnly` d'auth (décision D2 §0.5). |
| GET | `/api/me` | auth | non | Lire mon profil (incl. `locale`, `full_name`/`phone` déchiffrés). |
| PATCH | `/api/me` | auth | non | Mettre à jour profil/locale. |
| GET/PUT | `/api/me/notification-prefs` | auth | non | **Table créée** ; endpoint posé en lecture (squelette) ; pilotage complet au Lot 7. |
| GET | `/api/realtime/token` | auth | oui (famille active, optionnelle) | Émettre un **JWT Mercure scopé** par famille active (§3.12). Claims `subscribe` **vides au Lot 0** (aucun topic métier) ; recalculés à la bascule de famille. Le serveur est seule autorité sur le contenu des claims. |
| GET | `/api/families` | auth | non (listing de mes familles) | Lister **mes** familles (via memberships). |
| POST | `/api/families` | auth | non (création) | Créer ma première famille (US-ONB-1) → crée le Membership parent+admin. Idempotency-Key possible. |
| POST | `/api/invitations/accept` | public + token | non | Accepter une invitation (squelette US-ONB-1) → crée le Membership. |

### 6.3 Endpoints posés en squelette mais NON fonctionnels au Lot 0

> **Politique unique (décision D7 §0.5)** : ces endpoints **sont routés et présents dans l'OpenAPI** (donc dans le client Dart généré) mais renvoient **`501 Not Implemented`** (RFC 7807). On n'utilise **jamais** `404` pour un endpoint « pas encore prêt » — cela garantit la cohérence entre l'OpenAPI exporté et le client généré, et évite tout drift.

| Méthode | Chemin | Statut Lot 0 |
|---|---|---|
| POST | `/api/me/export` | `501 Not Implemented`. RGPD complet au Lot 8. |
| DELETE | `/api/me` | `501 Not Implemented`. Suppression/anonymisation au Lot 8. |
| GET | `/api/admin/rgpd/register` | `501 Not Implemented`. Lot 8. Auth renforcée + rôle admin technique. |
| GET | `/api/admin/rgpd/operations` | `501 Not Implemented`. Lot 8. |
| POST | `/api/admin/rgpd/purge?dryRun=true` | `501 Not Implemented`. Lot 8. |
| GET | `/api/admin/rgpd/compliance-report` | `501 Not Implemented`. Lot 8. |

### 6.4 Endpoints hors périmètre Lot 0 (rappel, autres lots)
Familles (PATCH réglages), memberships (PATCH/DELETE), invitations (émission), liens (Lot 1) ; enfants/notes (Lot 2) ; garde (Lot 3) ; agenda (Lot 4) ; échanges (Lot 6) ; dépenses (Lot 10). Tous absents/non routés au Lot 0.

### 6.5 Exemple de réponse d'erreur (RFC 7807) — droit insuffisant
```http
403  application/problem+json
{ "type":"/errors/forbidden", "title":"Action non autorisée",
  "detail":"Droit 'family_settings:edit' requis." }
```
Exemple — scope famille manquant :
```http
400  application/problem+json
{ "type":"/errors/family-scope-required", "title":"Contexte famille requis",
  "detail":"Le header X-Family-Id est requis pour cette ressource." }
```

---

## 7. Invariants & règles métier (pertinents pour ce lot)

> Tirés du cdc et de CLAUDE.md. Seuls ceux qui s'appliquent au Lot 0 sont listés.

1. **Scope famille obligatoire** : toute ressource métier est scopée via `X-Family-Id`, validé contre les memberships actifs (§3.6). Jamais de requête métier non scopée. *(Mécanisme posé et testé dès le Lot 0.)*
2. **Autorisation par Voters** : un Voter par domaine (`custody`, `agenda`, `children_notes`, `expenses`, `exchanges`, `family_settings`), niveaux `none|view|edit`, sur (Membre, Ressource). `parent` → `edit` forcé partout ; `child` → jamais `expenses`, jamais d'édition de structure. *(Squelette implémenté + testé unitairement.)*
3. **Dernier admin protégé** : invariant **posé** dès le Lot 0 (US-ONB-1 crée un parent+admin). Aucune opération ne doit retirer/rétrograder le dernier admin (appliqué pleinement au Lot 1, mais le garde-fou ne doit jamais être contourné).
4. **Chiffrement au repos des champs 🔒** : `full_name`, `phone`, `Invitation.email` chiffrés AES-256-GCM ; clés hors base ; un dump SQL ne révèle ni identités ni e-mails d'invitation.
5. **Registre des données personnelles** : tout champ personnel introduit au Lot 0 a une entrée (attribut déclaratif + `/backend/docs/registre-donnees.md`) ; **test CI de cohérence** bloquant. Fait partie de la DoD.
6. **Pas de données de santé** : aucun champ typé santé n'est créé (au Lot 0 ni ailleurs). (Le bandeau anti-santé concerne l'écran enfant, Lot 2.)
7. **i18n** : toutes les chaînes (UI web/mobile + e-mail) externalisées, format ICU, `fr-FR` livrée ; ajouter une langue ne demande aucun changement de code ; fuseau par défaut `Europe/Paris`.
8. **Dates non codées en dur** : la date des maquettes (23 juin 2026) n'apparaît jamais en dur ; tout calcul en `Europe/Paris` (stockage UTC).
9. **Sécurité applicative** : Argon2id ; JWT court + refresh **rotatif** ; rate-limiting (login/register/forgot) ; verrouillage après N tentatives ; tokens (vérif/reset/invitation) **hachés**, à usage unique, expirants ; anti-énumération ; autorisation par Voters sur chaque ressource ; RFC 7807 sans fuite technique.
10. **AuditLog append-only** : journalisation immuable des événements sensibles d'auth (login, échecs, lockout, reset, verify, logout, register) et d'onboarding (family.created, invitation.accepted).
11. **Hébergement UE** : aucune dépendance traitant des données hors UE (rappel infra/RGPD).
12. **Dépenses = v2** : aucune entité dépense créée au Lot 0 ; domaine de droit `expenses` présent dans les Voters (forcé `none` pour enfant) mais aucune API/onglet exposé.
13. **OpenAPI = source de vérité du client mobile** : le client Dart est généré et vérifié en CI (pas de DTO manuels divergents).
14. **`/infra/INSTALL.md` maintenu** : à jour avec chaque dépendance ajoutée au Lot 0 (Docker **et** bare-metal). Critère de DoD.
15. **Génération de garde matérialisée** : invariant d'architecture rappelé pour mémoire (Lot 3) ; non implémenté au Lot 0 mais ne doit pas être contredit par l'ossature.

---

## 8. Definition of Done (DoD)

> Reprend la DoD générale (cdc §9) en la spécialisant au Lot 0, complétée par les CA explicites du Lot 0 : *« inscription/connexion/refresh/reset fonctionnels (web + mobile) ; thème appliqué ; OpenAPI consommé par Flutter ; e-mails localisés (MailHog) ; un test Playwright et un test API verts en CI ; INSTALL.md permet une installation sans Docker reproductible. »*

### 8.1 Fonctionnel
- [ ] **US-AUTH-1** (inscription + vérification e-mail) opérationnelle **web ET mobile**.
- [ ] **US-AUTH-2** (login / logout / refresh rotatif / reset) opérationnelle **web ET mobile**, avec rate-limit + lockout.
- [ ] **US-ONB-1** (créer première famille ; squelette acceptation d'invitation) opérationnelle **web ET mobile**.
- [ ] **US-HOME-1** : shell applicatif affiché (sidebar, sélecteur de famille, encarts en état vide, encart garde masqué pour famille `intact`), responsive.
- [ ] **US-SET-1** (sous-ensemble) : `GET/PATCH /api/me` (profil, locale, champs 🔒).
- [ ] E-mails (vérification, reset, confirmation) **localisés** et reçus dans **MailHog** en dev.

### 8.2 Tests (verts en CI)
- [ ] **PHPUnit unitaire** : Voters (table de vérité §2.2 : parent→edit, child→pas d'expenses/structure, step_parent selon Right), négociation de locale, utilitaires de date/fuseau, chiffrement 🔒 (round-trip + dump illisible), rotation/réutilisation de refresh.
- [ ] **PHPUnit fonctionnel/API** : ≥1 test API vert (register→verify→login→refresh→reset), scope `X-Family-Id` (absent/invalide/non membre → erreurs attendues), anti-énumération.
- [ ] **Playwright E2E web** : ≥1 parcours vert (inscription → vérification via MailHog → connexion), responsive vérifié (desktop + mobile viewport), accessibilité de base.
- [ ] **Flutter** : `flutter test` (unitaires + widget des écrans d'auth), parcours register/login via client généré.
- [ ] **Couverture** mesurée et **≥ seuils tranchés au Lot 0 (décision D8 §0.5) : backend ≥ 80 % de lignes, mobile ≥ 70 %** (sinon CI rouge).
- [ ] **Test de cohérence du registre RGPD** vert (échoue si champ personnel sans entrée).
- [ ] **Test d'alignement OpenAPI ↔ client Dart** (drift = rouge).
- [ ] **Test d'immuabilité d'AuditLog** (append-only).
- [ ] **Test JWT Mercure** : `GET /api/realtime/token` émet un jeton dont les claims `subscribe` sont **vides au Lot 0**, scopé à la famille active, recalculé à la bascule de famille (jamais de topic d'une autre famille) — §3.12.

### 8.3 Qualité / analyse statique
- [ ] **PHPStan** niveau élevé : 0 erreur.
- [ ] **php-cs-fixer** : code formaté (CI en `--dry-run` bloquant).
- [ ] **`dart analyze`** : propre (selon config).
- [ ] **`dart format`** : appliqué (CI `--set-exit-if-changed`).

### 8.4 Migrations & données
- [ ] Migrations Doctrine versionnées pour toutes les entités du Lot 0 (§5.1).
- [ ] Fixtures de base (au moins un compte de test, une famille séparée et une famille unie) chargeables en environnement de test.
- [ ] Schéma cohérent (pas de modification d'entité sans migration).

### 8.5 OpenAPI & contrats
- [ ] `GET /api/docs.json` sert l'OpenAPI à jour, exporté dans `/contracts/openapi.json` (versionné).
- [ ] Client Dart **généré** depuis l'OpenAPI, commité et **consommé** par Flutter (auth fonctionnelle).
- [ ] Intercepteurs Dart en place (Bearer + refresh auto, `Accept-Language`, `X-Family-Id`, retry/Idempotency-Key).

### 8.6 i18n
- [ ] Toutes les chaînes d'auth (UI web Twig, UI mobile ARB, erreurs API, e-mails) externalisées en `fr-FR`, format ICU.
- [ ] Négociation de locale en place (préférence utilisateur > `Accept-Language` > `fr-FR`), fallback sans erreur.
- [ ] Aucune date codée en dur ; fuseau `Europe/Paris` par défaut.

### 8.7 RGPD & registre
- [ ] Squelette `RgpdManager` (namespace/structure) posé.
- [ ] `/backend/docs/registre-donnees.md` **initialisé** avec les champs personnels du Lot 0 (`User.email`, `full_name` 🔒, `phone` 🔒, `Invitation.email` 🔒, …) : catégorie, sensibilité, base légale, finalité, durée de conservation, destinataires.
- [ ] Attribut déclaratif de registre sur les entités + test de cohérence CI bloquant.
- [ ] `AuditLog` branché sur tous les événements d'auth/onboarding ; immuable.
- [ ] Endpoints `/api/admin/rgpd/*`, `POST /api/me/export`, `DELETE /api/me` **routés et présents dans l'OpenAPI** mais renvoyant **`501 Not Implemented`** (politique unique, décision D7 §0.5) — pas d'export/suppression prématurés, pas de `404`.

### 8.8 Infra & installation
- [ ] `docker-compose` dev complet démarre la stack en une commande (PostgreSQL 16, Redis, Mercure, MailHog, PHP, worker Messenger, scheduler).
- [ ] `/infra/INSTALL.md` : guide **bare-metal pas à pas** ET Docker, à jour avec les dépendances du Lot 0 (PHP 8.3+ et extensions, Composer, PostgreSQL, Redis, binaire Mercure, Node optionnel AssetMapper, SDK Flutter, clés JWT, clé de chiffrement, lancement Mercure/workers/scheduler, build assets, vérification de bon fonctionnement).
- [ ] Installation bare-metal **reproductible** vérifiée (un nouveau poste suit le guide sans étape manquante).
- [ ] Fail-fast si variables d'environnement critiques manquantes (clés JWT/chiffrement, DSN).

### 8.9 Design system (conformité §8)
- [ ] Tokens couleurs §8 appliqués (`--app-bg #E5E0D6`, `--surface #F4F1EA`, `--ink #292621`, `--sidebar #2B2722`, `--accent #C2643D`, `--positive #2E7D71`, overlay famille liée `#9C6FB0`, etc.).
- [ ] Typographies `Bricolage Grotesque` (titres) + `Hanken Grotesk` (corps).
- [ ] Formes (rayons cartes 14–20px, nav 11px, pastilles 999px, modales 22px), ombres douces.
- [ ] Web desktop : sidebar sombre 252px + zone principale (réf. 1320×840), sidebar repliable sous breakpoint.
- [ ] Mobile : style iOS « liquid glass » (en-têtes, listes inset, navigation par onglets en bas), Material adapté à la palette sur Android.
- [ ] Écrans d'auth + shell conformes aux maquettes (qui font foi en cas d'ambiguïté).

### 8.10 CI (référence)
- [ ] Pipeline CI exécutant : lint + PHPStan + `dart analyze`/`dart format` + PHPUnit (≥1 API vert) + Playwright (≥1 E2E vert) + flutter test + build assets web + génération/validation OpenAPI & client Dart + test registre RGPD + seuil de couverture.
- [ ] Toute PR doit avoir une CI **verte** pour être mergeable.

### 8.11 Mise à jour de CLAUDE.md
- [ ] Section « Outillage prévu (à mettre en place au Lot 0) » de `CLAUDE.md` **mise à jour avec les commandes réelles** une fois le Lot 0 échafaudé (consigne explicite de CLAUDE.md).
