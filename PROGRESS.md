# Avancement d'implémentation — CoParentPlanner

> **Document de reprise.** Mis à jour régulièrement pour reprendre le travail facilement entre
> sessions. Source de vérité fonctionnelle : `specs/cdc.md` + `specs/lots/`. Invariants : `CLAUDE.md`.
> Maquettes (rendu visuel fait foi) : `specs/design/` (`CoParentPlanner Desktop.dc.html`, variante
> mobile, `ios-frame.jsx`, `screenshots/`).

Dernière mise à jour : **2026-08-27** — **Revue de code de l'application mobile Flutter : revue conduite, plan de correction établi, aucune correction encore livrée.** Revue statique exhaustive des 13 251 lignes de Dart écrit à la main (hors `lib/api/generated/`) + 4 005 lignes de tests + ARB + configuration Android/iOS + job CI `mobile`, croisée avec `contracts/openapi.json` et le backend PHP concerné. Workflow multi-agents : **9 axes en parallèle** (sécurité/auth/stockage · offline/outbox/cache · réseau/intercepteurs/dépôts · état Riverpod/routage/cycle de vie async · garde · agenda+overlay · écrans métier · design system/i18n/a11y · tests et conformité projet), puis **vérification adversariale** par lots de 4 avec consigne de réfuter par défaut — 29 agents, 857 appels d'outils, 3,07 M tokens. **81 constats explorés, 73 après dédoublonnage, 68 confirmés** (1 P0, 14 P1, 40 P2, 13 P3), **5 réfutés**. Plan ordonné en 11 étapes : **`specs/revue-mobile-flutter-2026-08.md`** ; détail intégral (preuve, panne, correction, verdict) : **`specs/revue-mobile-flutter-2026-08-annexes.md`**. **Constat structurant : l'app mobile ne fonctionne aujourd'hui ni en ligne ni en release**, pour trois causes indépendantes, chacune suffisante — (1) **P0 `C17`** : `ApiRoutes.isFamilyScoped()` classe par `contains` et dé-scope donc `/api/families/{familyId}/…`, c'est-à-dire **toutes** les ressources métier, que le serveur refuse en 400 `/errors/family-scope-required` (`FamilyScope.php:59`) → agenda, garde, enfants, échanges, membres, invitations, liens, overlay : aucun écran métier en ligne ne répond ; (2) **P1 `C4`/`C63`** : la permission `INTERNET` n'est déclarée que dans les manifestes `debug` et `profile` → un APK/AAB **release** n'émet aucune requête ; (3) **P1 `C1`** : `restoreSession()` purge les jetons sur une simple erreur réseau → démarrer hors-ligne déconnecte et ferme l'accès au cache du Lot 8. Ces deux premiers ont été **re-vérifiés à la main hors chaîne d'agents** avant rédaction. **Cause structurelle** : il n'existe **aucun test de bout en bout mobile contre un backend réel** — les 180 tests montent des écrans sur dépôts mockés ou `HttpClientAdapter` scripté, le client n'a jamais parlé au serveur ; le seul test du scope famille interroge `/api/custody`, **chemin absent du contrat**. S'y ajoutent **deux tests verts couvrant du code mort** (`retry()` et `purgeSynced()` n'ont aucun appelant de production), une CI qui **n'applique aucun lint** (`dart analyze` sans `--fatal-infos`) et un **dénominateur de couverture faux** (un fichier jamais importé par un test est absent de `lcov`). ⚠️ Ce chantier nuance fortement le « Lot 8 TERMINÉ / v1 complète » ci-dessous **côté mobile**. Portes inchangées à ce stade (aucune modification de code) : `dart analyze` 0 issue, **180 tests verts**, couverture **75,8 %**. **Reprise : étape 1 (`ApiRoutes`) puis étape 2 (manifeste Android)** — une trentaine de lignes qui débloquent l'application entière et doivent précéder tout le reste. — Antérieur : **2026-08-03** — **Revue web desktop : étape 5 livrée (accessibilité de la palette, web + mobile).** Arbitrage produit rendu sur les trois questions ouvertes : **(a) ne pas assombrir** la palette livrée, **(b)** marqueur = **initiale du parent**, **(c) amender** le §8 — d'où le nouveau **`cdc §8.1`**. **5.5 corrigé** (le constat central) : les fonds A/B ne se distinguaient que par la teinte — **1,12:1 de luminance en vision normale**, **1,08:1 en protanopie simulée** sur les bordures, fonds écrasés en `forced-colors` — donc la couleur était le **seul** canal portant *chez quel parent est l'enfant*. Marqueur textuel `initiale du prénom` (capitale, accent conservé ; **deux lettres** en collision `Sophie`/`Simon` → `So`/`Si` ; repli `A`/`B`), livré sur **`/garde` + mini-semaine de l'accueil** (web) **et `CustodyScreen`** (Flutter), règle dupliquée PHP/Dart à **jeux de tests jumeaux** (22 cas identiques). Plafond à 2 lettres = défaut trouvé en cours de route (sans lui, deux parents sans nom produisaient « Web »/« Web2 » dans une case de 53px). Légende refondue : elle **porte le marqueur** et reproduit l'aspect de la case, le blanc sur `--accent` ne valant que 4,05:1. **5.1 à 5.4 conservés en divergence assumée** et documentés au §8.1 ratios à l'appui (dont `.form-error` à 3,61:1, tous les messages d'erreur), avec pour contrepartie un **`@media (prefers-contrast: more)`** en opt-in système, conforme AA sur les **quatre** fonds possibles — `--muted`/`--muted-2` y convergent, la hiérarchie à trois gris tombant à deux dans ce mode seulement. **Garde-fous** (la revue notait « aucun test de contraste ») : `ContrastTokensTest` recalcule les ratios **depuis le CSS** et a été **vérifié négativement** ; `WebPagesTest::testGardeCarriesNonColourParentMarker` ; test widget Flutter ; E2E sous `forcedColors: 'active'`. Portes : backend **338 tests / 2740 assertions** (313 avant) / couverture **85,6 %** / PHPStan 0 / cs-fixer 0 / Twig 30 ; mobile **180 tests** (157 avant) / **75,9 %** / analyze 0 ; **E2E 21/21**. **Aucun changement d'API, d'OpenAPI, de client Dart, de migration, de registre RGPD ni de dépendance.** **Reste l'étape 6** (12 écarts de périmètre) + la liste « Reste à traiter ». — Antérieur : **2026-07-28** — **Revue de l'application web desktop : étapes 1 à 4 du plan de correction livrées.** Revue conduite dans Chrome sur la stack de dev (6 écrans, 1320 px = gabarit §8 **et** 1920 px, points de rupture mesurés par iframes) + revue croisée multi-agents (6 dimensions, 42 constats explorés, **39 confirmés** par vérification adversariale, 3 réfutés). Plan ordonné et journal détaillé : **`specs/revue-web-desktop-2026-07.md`**. **Étape 1** — socle `assets/api_base.js` (`ApiController`) dont héritent les **6** contrôleurs Stimulus : les 6 `_send()` copiés-collés disparaissent, avec eux la double soumission (bouton désactivé + `aria-busy`), le 401 silencieux au bout de 15 min (refresh + rejeu à clé d'idempotence constante, saisie conservée), l'absence de confirmation de succès (cible `notice` via `sessionStorage`), le `scrollTo(0)` qui masquait l'erreur, et l'affichage brut du `detail` RFC 7807. **Étape 2** — CSS : `width:100%` ne s'applique plus aux cases à cocher ni au sélecteur de couleur ; `minmax(0,1fr)` généralisé ; **débordement horizontal éliminé sur 6 écrans × 9 largeurs (390→1440 px)**, contre 135 px à 1320 px et 481 px à 415 px avant. Deux causes racines plus larges que le constat initial : `[hidden]` était défait par tout `display` d'auteur (d'où les champs de série visibles pour un événement ponctuel), et **le bloc `@media (max-width:900px)` était déclaré avant les composants qu'il replie** — le repli de `/garde`, `/settings` et `/echanges` n'avait donc **jamais** fonctionné. **Étape 3** — i18n : `default_locale` valait `en` (redéclaration dans `translation.yaml`, chargé après `framework.yaml`) → le **bandeau anti-données-de-santé**, invariant `CLAUDE.md`, affichait `child.health_warning` ; nouveau catalogue `validators+intl-icu.fr.yaml` (le Validator traduit dans `validators`, pas `messages`) → fin des clés brutes dans les violations, **web et mobile** ; dictionnaires FR dans `shell.html.twig` (plus aucune valeur d'enum ni `snake_case` à l'écran) ; fuseau Europe/Paris rétabli sur l'heure des messages (02:21 s'affichait « 00:21 ») ; dates de notes en `d/m/Y` ; périodes spéciales filtrées sur le mois affiché, avec année et parent bénéficiaire. **Étape 4** — garde-fous destructifs : confirmation de suppression d'événement **libellée selon la portée** (série = occurrence pivot + toutes les suivantes), confirmation nommée de l'échange de jour + `aria-label` des cases, parent de départ des règles de vacances **préselectionné** (« Appliquer » le remettait sur A et régénérait plusieurs semaines de garde), variante `.btn--danger` sur 7 boutons, et `User::setFullName()` normalisant la chaîne vide en `null` (un membre sans nom était signé « Membre supprimé »). **Dette de test fermée** : `responsive.spec.ts` ne couvrait que `/login` ; il vérifie désormais les **6 écrans authentifiés** sur les deux projets Playwright (Desktop Chrome et Pixel 7). Portes : backend **313 tests / 2585 assertions** (303 avant) / PHPStan 0 / cs-fixer 0 / Twig 30 OK ; **E2E Playwright 21/21** (19 avant) ; aucun changement d'API, d'OpenAPI, de migration ni de registre RGPD. **Restent les étapes 5 (contrastes — arbitrage produit requis, le §8 prescrit des paires qui échouent AA) et 6 (écarts de périmètre : fiche de jour + activités sur `/garde`, champ Commentaire, écran RGPD web, `/settings` en onglets, UUID des familles liées).** — Antérieur : **2026-07-13** — **Flux d'invitation simplifié (web + mobile via OpenAPI).** Le parcours d'un·e invité·e passe de ~8 écrans / 2 e-mails à **2 écrans / 1 e-mail** et **arrive connecté·e**. Le token d'invitation (opaque 256 bits, usage unique, expirant, livré dans la seule boîte de l'invité·e) **prouve la possession de la boîte** : sur ce chemin uniquement, on **supprime la vérification d'e-mail redondante** et la **reconnexion** — création de compte / connexion + acceptation + ouverture de session en **un POST**. Deux endpoints publics (`POST /api/auth/register-and-accept-invitation` compte neuf, `POST /api/auth/login-and-accept-invitation` compte existant — **mot de passe exigé**, frontière de sécurité contre un lien transféré) ; cœur d'acceptation mutualisé (`InvitationAccepter`, usage unique/expiry/verrou FOR UPDATE conservés). `HomeController` route selon l'état (join / login_to_join / accept / **mismatch** « se déconnecter et continuer ») ; **GET = aperçu non consommant** + `Referrer-Policy: no-referrer` ; compte `Pending` **activé** via preuve du token sans jamais écraser le mot de passe (décision PO). **Sécurité : zéro régression** (compte existant protégé par mot de passe, single-use/expiry/anti-énumération/chiffrement/cookies HttpOnly préservés) ; seul recul assumé (lien transféré → création d'un compte pour un e-mail **sans** compte préexistant, déjà vrai de toute vérif par lien, mitigé TTL 7 j + usage unique + révocation). Conçu via **workflow adversarial** (3 approches indépendantes + critique sécurité/UX). Portes : backend **303 tests** (dont +9 API onboarding) / PHPStan 0 / cs-fixer 0 / Twig OK / registre RGPD inchangé (aucun nouveau champ perso) ; **OpenAPI ré-exporté** (+2 endpoints) → **client Dart régénéré** (`swagger_parser`) / `dart analyze` 0 / **157 tests Flutter** ; **E2E Playwright 19/19** (parcours anonyme « 1 lien → 1 formulaire → bienvenue connecté » de bout en bout). — Antérieur : **2026-07-10** — **Correctif UX (web) : parcours d'invitation « register-first ».** Le lien d'invitation reçu par e-mail, ouvert **sans être connecté**, ne renvoie plus vers `/login` (où l'invité·e ne savait pas quoi faire) : il présente désormais un **formulaire de création de compte contextualisé** (`app/join.html.twig` — nom de la famille, e-mail nominatif pré-rempli en lecture seule) avec un lien « déjà un compte ? se connecter ». Le token est mémorisé **en session** (seulement s'il est valide) et **survit** au parcours register → vérif e-mail → login ; après connexion, l'invité·e est ramené·e sur un **écran de confirmation explicite** (« Rejoindre {famille} », plus d'acceptation silencieuse) qui consomme l'invitation, le token n'étant purgé qu'au **succès confirmé** (route `app_invitation_accepted`). **Revue adversariale** (workflow 7 dimensions, 19 agents, 12 constats confirmés) → tous traités : P1 token invalide mémorisé avant validation (corrigé : stockage session conditionné à la validité) ; P2 acceptation silencieuse + purge prématurée du token (corrigé : confirmation explicite + purge au retour de succès) ; P2 non-énumération prouvée par test ; couverture des branches session/expiré/non-pending/défaut login ajoutée. **i18n** : « vérification des traductions de la création de compte » → l'UI web est **100 % FR codée en dur** (divergence *assumée et documentée* au Lot 5 ; l'i18n ICU vit sur l'API + le mobile + les e-mails) — copie FR de l'écran vérifiée correcte, aucune migration i18n (décision verrouillée). Portes : backend **170 tests fonctionnels** (+10) / PHPStan 0 / cs-fixer 0 / Twig OK ; **E2E Playwright 2/2** (`lot1-family`, dont le nouveau parcours register-first de bout en bout via Mailpit) ; **aucun changement d'API/OpenAPI ni de mobile** (routes web pures). — Antérieur : **Lot 9 (CI/CD & build iOS sans Mac) — OUTILLAGE LIVRÉ.** Deux pipelines CI **interchangeables** produisant un `.ipa` iOS signé sans poste Mac : GitHub Actions (`.github/workflows/build-ios.yml`, `macos-latest`, principal) + Codemagic (`codemagic.yaml`, secours), tous deux appelant la **même** logique — un `Fastfile` unique (`mobile/ios/fastlane/Fastfile`, lanes `build`/`release`/`certificates`) et le **même** dépôt de certificats **Fastlane match**. Signature via match (dépôt Git privé chiffré, secrets CI, **jamais** de `.p12`/`.p8`/passphrase en clair), auth ASC par **clé API** (`.p8` base64), CI **toujours `readonly`** sur les certificats, déclenchement **manuel** (`workflow_dispatch`/dashboard) ou tag `v*`, **jamais d'upload TestFlight sur simple commit**, job `mobile-tests` (Linux) **gating** le build signé (§3.4). Livrables : `Fastfile`/`Matchfile`/`Appfile`/`Gemfile`/`ExportOptions.plist` + les deux fichiers de CI + `infra/INSTALL.md §E` (bandeau « À VENIR » retiré, secrets + rotation synchronisée documentés) + `CLAUDE.md` (Outillage). YAML (build-ios + codemagic) et plist **validés**. **Non exécutable de bout en bout depuis ce dépôt** : les runs CI réels exigent un compte Apple Developer, une clé API ASC et le dépôt `match` initialisés hors dépôt (action mainteneur, §E.1) — sans eux chaque pipeline échoue tôt et proprement (secrets manquants listés). **Aucun code produit, aucune migration, aucun endpoint, registre RGPD inchangé** (lot purement infra). CI existante (backend/web-e2e/mobile) **inchangée** (job additif). — Antérieur : **Lot 8** — chantiers **RGPD Manager**, **idempotence serveur** et **PWA web** TERMINÉS. (1) **RGPD** : registre, `RetentionPolicy`, Anonymiseur (irréversible, fils préservés, dernier admin, scrub invitations), Purger (dry-run/live, isolation d'erreur, bornes UTC), export async (ZIP + lien signé HMAC), `DELETE /me`, admin technique + step-up rate-limité, endpoints `/admin/rgpd/*`, test chiffrement au repos. (2) **Idempotence serveur** (§3.1/§6.3) : middleware `IdempotencySubscriber` sur POST `/api` avec `Idempotency-Key`, scope (user, endpoint, clé) + empreinte payload, **corps de réponse chiffré au repos**, store `IdempotencyKey` (TTL/purge). (3) **PWA** : manifest, service worker (précache shell + écrans RO cloisonnés famille, blocage mutations, page offline), contrôleur Stimulus `pwa` (install + bannière online/offline), **polices OFL auto-hébergées**, icônes. (4) **Offline mobile riche** : outbox statuts `pending/inFlight/synced/rejected` (autorité serveur : 2xx→synced, 409/403/422/404/410→rejected, 5xx/réseau/401→pending), cache de lecture générique cloisonné famille (agenda + enfants + échanges), rejeu auto, bannière hors-ligne + compteur, migration Drift v2. (5) **E2E Playwright PWA** : installabilité + hors-ligne RO + blocage mutation (503) + reconnexion. (6) **UI mobile RGPD** : Paramètres › Confidentialité (export async + lien signé ; suppression de compte, refus dernier admin). **Revues adversariales** : RGPD 8 corrigés / idempotence 2 P1/P2 corrigés. Portes : backend **280 tests** / PHPStan 0 / cs-fixer 0 / schéma sync ; mobile **157 tests** / couverture 75,5 % / analyze 0 / client canonique ; **E2E Playwright 18/18** ; OpenAPI inchangé. **Lot 8 TERMINÉ — Lots 0→8 TERMINÉS (v1 complète). Prochains : Lot 9 CI/CD & build iOS sans Mac (outillage infra), Lot 10 Dépenses (v2), Lot 11 Push mobile (v2).**

Antérieur : **Lot 6 (Échanges temps réel) TERMINÉ** : backend (entités Exchange/ExchangeOption/Message🔒/OptionVote, 6 endpoints, moteur de décision par consensus + verrou pessimiste, Mercure post-commit, RGPD, i18n) + contrat OpenAPI + client Dart + UI web (écran /echanges maître-détail + badge nav + temps réel) + UI mobile (écran Échanges + badge accueil) + E2E + revue ciblée. Portes : backend **241 tests** / PHPStan 0 / cs-fixer 0 / Twig 22 ; **E2E Playwright 14/14** ; mobile **140 tests** / `dart analyze` 0 / couverture 75,2 % ; client généré intact. Avant : **Revue graphique (conformité maquettes §8) APPLIQUÉE** (113 écarts, 3 P2 différés justifiés). **Lots 0→6 TERMINÉS. Prochain lot : Lot 7 — Notifications e-mail.**

---

## 0. Où en est-on ?

**Deux chantiers de revue sont ouverts en parallèle.**

**A. Revue de code de l'application mobile Flutter** (`specs/revue-mobile-flutter-2026-08.md`,
détail : `...-annexes.md`) — **ouverte le 2026-08-27, 68 constats confirmés, 0 corrigé**. Plan en
11 étapes. L'ordre est imposé par le constat structurant : l'app mobile ne fonctionne aujourd'hui
**ni en ligne** (P0 `C17`, scope famille jamais posé sur `/api/families/{familyId}/…`) **ni en
release** (`C4`/`C63`, permission `INTERNET` absente du manifeste `main`), et son démarrage
hors-ligne déconnecte l'utilisateur (`C1`).

| Étape | Objet | Constats |
|---|---|---|
| **1** | `ApiRoutes` : classification par `contains` → scope famille cassé | C17 (**P0**), C18 |
| **2** | Build Android : release inerte, `allowBackup`, clé de debug | C4·C63, C6, C73 |
| **3** | Cloisonnement des données locales entre comptes (rien n'est purgé) | C2·C9, C3·C8, C22, C5, C25 |
| **4** | Démarrage & session (réseau traité comme jeton invalide) | C1, C21, C24 |
| **5** | Machine d'état de l'outbox (inFlight orphelin, `rejected` sans UI) | C7, C10·C64, C13, C12, C14, C11, C16·C65 |
| **6** | Agenda : fenêtre de lecture bornée au mois, dates en UTC | C37, C38·C45, C20·C36, C39, C41, C43, C44 |
| **7** | Garde : écran, droits `custody:edit`, §8.1 (nom du parent) | C26, C29, C33, C27, C28, C55, C30, C32, C34 |
| **8** | i18n & formatage (`intl` jamais utilisé, ARB sans diacritiques) | C57, C19, C40, C51, C31·C56·C58·C53, C62 |
| **9** | Écrans métier : garde-fous d'action, droits, `mounted` | C47, C46, C48, C50, C52, C23, C59, C60, C61 |
| **10** | Typographie : polices variables sans axe de graisse | C54 |
| **11** | Portes qualité : lint non appliqué, tests manquants, couverture | C66, C71, C72, C67, C68, C69 |

**B. Revue de l'application web desktop** (`specs/revue-web-desktop-2026-07.md`) — chantier en cours,
39 constats confirmés : **étapes 1 à 5 livrées** (socle Stimulus · CSS/débordements · i18n ·
garde-fous destructifs · accessibilité de la palette + marqueur non chromatique A/B web & mobile,
`cdc §8.1`). **Étape 6 (12 écarts de périmètre) en cours — vague 1 livrée** : 6.7 famille à un seul
parent (boucle `/settings` ↔ `/garde` fermée), 6.9 réglage d'échange inerte supprimé, 6.11 barre
latérale collante, 6.12 UI alignée sur les droits (extension Twig `can()`), 6.2 champ Commentaire.
**Restent** : vague 2 — 6.8 liens e-mail sans cible + page d'erreur brute, 6.10 seconde famille
impossible, 6.6 familles liées inutilisables ; vague 3 — 6.1 fiche de jour `/garde`, 6.5 phrase de
garde sur l'accueil, 6.4 `/settings` en trois onglets, 6.3 écran RGPD web ; **plus** la liste
« Reste à traiter » (P2/P3 de finition). ⚠️ L'étape 6 nuance le « Lots 0→8 TERMINÉS » ci-dessous.

### Point de reprise immédiat (au 2026-08-27)

Dépôt **propre**, branche `mobile-review`. Portes vertes à l'état initial (`dart analyze` 0 issue,
**180 tests Flutter**, couverture **75,8 %** ; backend et E2E non touchés).

**La priorité est le chantier A (mobile)** : rien d'autre ne mérite d'être corrigé sur mobile avant
les étapes 1 et 2, puisque l'application ne répond aujourd'hui sur aucun écran métier en ligne et
n'émet aucune requête en build release. Ordre de reprise :

1. **Étape 1 — `ApiRoutes`** (`mobile/lib/core/network/interceptors/api_routes.dart`) : remplacer la
   classification par `contains` par une comparaison par segment complet. Puis remplacer le test
   `test/core/network/interceptors_test.dart:31` (qui interroge `/api/custody`, chemin **absent du
   contrat**) par une **table de cas dérivée de `contracts/openapi.json`**.
2. **Étape 2 — manifeste Android** : ajouter `<uses-permission android:name="android.permission.INTERNET"/>`
   dans `src/main/AndroidManifest.xml`, désactiver `allowBackup`, retirer la signature par clé de debug
   du `buildTypes.release`.
3. Puis étapes 3 → 11 dans l'ordre du plan (`specs/revue-mobile-flutter-2026-08.md`).

**Dette de test à fermer en même temps** (elle est la cause du P0) : aucun test mobile ne parle au
contrat, et deux tests verts (`retry()`, `purgeSynced()`) couvrent du code sans appelant de
production. Le job CI `mobile` n'applique par ailleurs **aucun** lint (`dart analyze` sans
`--fatal-infos`) et son dénominateur de couverture exclut les fichiers jamais importés par un test.

### Point de reprise du chantier web (au 2026-08-03)

Dépôt **propre**, dernier commit `86887f9`. Toutes les portes vertes (voir §5.3 pour les relancer).
**7 constats restants**, dans cet ordre :

| # | Constat | Nature |
|---|---|---|
| **6.8** | Liens des e-mails de notification perdant leur cible (redirection `/login` sans `next`, redirection en dur vers l'accueil) **et** page d'erreur brute de Symfony sur toute URL web inconnue (`ProblemExceptionSubscriber` ne traite que `/api`, `templates/bundles/` absent) | parcours cassé |
| **6.10** | Impossible de créer ou rejoindre une **seconde famille** depuis l'UI (`/onboarding` répond 200 mais aucun template ne le lie ; le menu du sélecteur n'offre aucune action) | parcours cassé |
| **6.6** | **Familles liées** inutilisables : le formulaire réclame un UUID que l'UI n'affiche nulle part | parcours cassé |
| **6.1** | `/garde` sans **fiche de jour ni activités** — contractualisé au **Lot 3** (`cdc.md:158` et `:511`, maquette `:234-242` et `:828-868`) ; un membre en lecture seule n'a aucune interaction | développement |
| **6.5** | Accueil sans **phrase de garde** ni prochain échange (maquette : phrase 25 px, sous-ligne horloge « Retour chez X dimanche 28 juin, 19h00 », avatars enfants ; `custodyWeek()` ne calcule pas l'information) | développement |
| **6.4** | `/settings` en **master-détail à trois onglets** au lieu d'une page unique de 4 094 px à 9 ancres dont aucune active | développement |
| **6.3** | Aucun **écran RGPD ni gestion de compte** web, alors que l'API expose `POST /api/me/export`, `DELETE /api/me`, `PATCH /api/me` et que le mobile a l'écran — **Lot 8** (`cdc.md:531`) | développement |

**⚠️ Question ouverte à trancher avec le PO avant de coder 6.4** : la maquette impose *trois* onglets
exclusifs, mais `/settings` porte aujourd'hui **neuf** sections (Compte, Notifications, Famille,
Rythme de garde, Exceptions, Vacances, Membres, Invitations, Familles liées). Le regroupement en trois
n'est pas déductible du constat — lire la maquette aux lignes `:581/630/720` et **faire valider le
découpage** plutôt que d'inventer une taxonomie. Noter que 6.3 (écran RGPD/compte) ajoutera du contenu
à l'onglet « Compte » : traiter 6.3 **avant** 6.4 évite de refondre `/settings` deux fois.

**Lot 9 — CI/CD & build iOS sans Mac** (`specs/lots/lot-9-cicd-build-ios-sans-mac.md`) : ✅ **OUTILLAGE LIVRÉ** (lot purement infra, aucun code produit). Deux pipelines CI interchangeables (GitHub Actions principal + Codemagic secours) appelant un `Fastfile` unique et le même dépôt `match` ; signature sans secret en clair ; job `mobile-tests` gating ; docs INSTALL.md/CLAUDE.md à jour ; YAML/plist validés. **Exécution de bout en bout = action mainteneur** (compte Apple + clé API ASC + dépôt `match` + secrets, §E.1) — non réalisable depuis ce dépôt (voir journal §6, 2026-07-10). CI existante inchangée.

**Lot 0 — Fondations** : ✅ **TERMINÉ**.
**Lot 1 — Familles/membres/droits/invitations/liens** : ✅ **TERMINÉ** (+ revue de code).
**Lot 2 — Enfants & notes** : ✅ **TERMINÉ** (+ revue de code).

**Lot 8 — PWA / offline mobile / RGPD Manager** (`specs/lots/lot-8-*.md`) : ✅ **TERMINÉ** — RGPD Manager complet (registre, rétention config, anonymiseur irréversible, purge dry-run/live, export lien signé, `DELETE /me`, admin technique + step-up), idempotence serveur générique (§6.3), PWA web (manifest + service worker + offline dégradé), offline mobile riche (outbox + cache cloisonné famille + résolution de conflits autorité serveur), UI mobile RGPD. **DoD vérifiée** (voir journal §6, 2026-07-10) : 2 revues adversariales (RGPD 8 corrigés, idempotence 2 P1/P2 corrigés) ; backend 280 / mobile 157 / E2E 18 ; test chiffrement au repos + cohérence registre verts. **v1 COMPLÈTE (Lots 0→8).**

**Lot 3 — Garde (moteur + vacances)** (`specs/lots/lot-3-*.md`) : ✅ **TERMINÉ** (backend + moteur + adaptateur vacances + async/scheduler + endpoints + contrat OpenAPI + client Dart + UI web + E2E + temps réel + UI mobile + **revue de code + corrections**).

**Lot 4 — Agenda** (`specs/lots/lot-4-agenda.md`) : ✅ **TERMINÉ** — backend + moteur + endpoints + UI web + UI mobile + E2E + temps réel + revue de code adversariale.

**Lot 7 — Notifications e-mail** (`specs/lots/lot-7-notifications-email.md`) : ✅ **TERMINÉ** (préférences + pipeline async + déclencheurs garde/agenda/échanges + rappels planifiés + templates + UI web/mobile + E2E) — détail dans le journal §6 (2026-07-09). Simplifications assumées documentées : invitation conserve son handler dédié (déjà async+localisé) ; coalescence fine des rafales et rappels d'agenda récurrents non implémentés (anti-spam de masse fait à l'émission).

**Lot 6 — Échanges (temps réel)** (`specs/lots/lot-6-echanges-temps-reel.md`) : ✅ **TERMINÉ** (backend + contrat + UI web + UI mobile + E2E + revue ciblée) — détail dans le journal §6 (2026-07-05).

**Lot 5 — Familles liées & overlay** (`specs/lots/lot-5-familles-liees-overlay.md`) : ✅ **TERMINÉ** (backend + contrat + UI web + UI mobile + E2E).
- **UI web** : `/agenda` — toggle « Overlay famille liée » par lien actif (Stimulus `toggleOverlay` → PUT préférence + reload), événements liés en **lecture seule** (badge « Famille liée », couleur `#9C6FB0`), fusion par jour, état vide localisé. `/garde` — pastilles d'activité `#9C6FB0` par jour (dérivées des événements projetés, garde jamais modifiée) + toggle custody. CSS `--linked`. **Note** : libellés overlay hardcodés en FR dans les Twig (cohérent avec l'UI web existante, 100 % hardcodée ; l'API et le mobile utilisent l'i18n). WebPagesTest : rendu overlay (événement lié + badge + toggle). Backend **218 tests**, couverture 85,6 %.
- **UI mobile** : `OverlayRepository` (liens actifs + préférences via clients générés ; événements projetés via appel dio brut car `?overlay=` non exposé par le client généré) + providers (`overlayEventsProvider` agenda, `overlayGardeDatesProvider` garde). Écran agenda : section toggles par lien + événements liés **RO** (badge « Famille liée », `#9C6FB0`). Écran garde : toggles vue garde + **pastilles `#9C6FB0`** par jour (multi-jours spannés, garde jamais modifiée) + légende. i18n ARB `overlay*`. Lecture overlay **best-effort** (un échec n'impacte pas l'agenda/garde propres). Mobile **124 tests**, couverture 77,9 %, `dart analyze` propre ; contrat inchangé (pas de régénération).
- **E2E web** (`web-e2e/tests/lot5-overlay.spec.ts`) : deux familles A/B, événement chez B, B propose le lien / A l'active (setup via API `page.request` — cookie JWT partagé + `X-Family-Id`), puis toggle overlay via l'UI : activation → « Judo du second foyer » visible en **lecture seule** (badge « Famille liée », ni formulaire ni bouton d'édition) ; désactivation → masqué. **1 test vert.**
- **DoD Lot 5 satisfaite** : tests unit/fonctionnels + E2E web + tests Flutter verts, migration `OverlayPreference` fournie, OpenAPI à jour + client Dart régénéré, i18n câblée (API/mobile) , registre RGPD à jour (destinataire « famille liée » + `OverlayPreference`), analyse statique propre (PHPStan 8 / `dart analyze` / cs-fixer). Aucune nouvelle dépendance → INSTALL.md inchangé.
- **Modèle** : entité `OverlayPreference` (opt-in par membre/lien/vue, unicité `(membership,link,view)`) ; migration `Version20260701213830`.
- **Habilitation & projection** : `FamilyLinkRepository::findActiveBetween` (bidirectionnel, refuse soi/pending) ; `OverlayReader` (résolution multi-liens, 422 uniforme si tous invalides, dédup par identité de lien) ; **projection anti-fuite `OverlayEventResource`** (uniquement horaires/catégorie/titre + source=linked/#9C6FB0/editable=false ; **aucun** enfant/responsable/description/lieu/série/id). Activation du `?overlay=` dans `EventsProvider` (fusion + tri), expansion en fuseau B → affichage fuseau A.
- **Endpoints** : `GET/PUT /families/{id}/overlay-preferences` (scopé membre courant, upsert idempotent sérialisé par advisory lock = last-write-wins §4.2, valide lien actif). RO strict : aucune route d'écriture overlay ; mutation cross-famille refusée (scope). Garde jamais modifiée (pastilles dérivées des événements projetés).
- **Revue adversariale sécurité** (5 dimensions : anti-fuite, habilitation/scope, RO, préférences, modèle/fuseau/RGPD) : **0 P0/P1/P2**, **2 P3 corrigés** (dédup lien par identité ; upsert préférence en last-write-wins). Registre RGPD à jour (destinataire « famille liée » + `OverlayPreference` technique), i18n `overlay.*`.
- **Portes (backend, étape A)** : backend **217 tests / 1761 assertions, couverture 86,0 %**, PHPStan niv.8, cs-fixer ; OpenAPI exporté + client Dart régénéré (`overlay_preference_client`), mobile **120 tests** sans drift.
- **UI mobile** : feature `lib/features/agenda` — `AgendaRepository` (+ providers scopés famille), `AgendaScreen` (occurrences du mois groupées par jour, pastilles catégorie §8, `CategoryPill`), formulaire de création (ponctuel : titre, catégorie, responsable, cible enfant(s)/famille, all-day, date/heure → ISO 8601 avec offset appareil, lieu), bloc « À venir » (4 prochains) sur `HomeTab`. Onglet Agenda déjà dans le shell. Client `events` ajouté à `ApiClients`. **Contrainte assumée** : création limitée aux ponctuels (le modèle généré `recurrence` = `Map<String,String?>` perd le typage int/bool ; séries et édition par portée = web ; offline riche = Lot 8, §0.3). **120 tests mobiles** verts, couverture **78,5 %** (≥70 %), `dart analyze`/`format` propres, client généré sans drift. i18n ARB `agenda*`/`cat*`.
- **UI web** : écran `/agenda` (`AgendaController` + `app/agenda.html.twig`) — occurrences groupées par jour, filtres catégorie (pills §8), formulaire création (ponctuel/série, all-day, récurrence until|count, exclusion vacances), édition par portée (occurrence/série) et suppression via Stimulus `agenda` (datetime-local → ISO 8601 avec offset), `turbo-stream-source` + cookie Mercure agenda. Onglet « Agenda » dans la sidebar (tous types de famille). Bloc « À venir » (4 prochains) sur l'Accueil (`HomeController` + `UpcomingEventsFinder`, masqué si agenda=none). Backend **209 tests** (WebPagesTest : `/agenda` vide + avec événement + accueil « À venir »), couverture 85,7 %, PHPStan/cs-fixer/lint Twig verts.
- **Modèle** : entité `Event` (ponctuel/série, overrides+EXDATE rattachés par `series_id`, `whole_family`, verrou optimiste, `idempotency_key`), table de liaison `event_child` (N-N, 1er ManyToMany du projet — cf. §5.2), enums `EventCategory`/`RecurrenceFreq` ; migration `Version20260701192550` (FK famille/enfant CASCADE, responsable/auteur SET NULL, index uniques partiels override/EXDATE/idempotency). Schéma en sync (dev+test).
- **Moteur** (`Service/Agenda/`) : `OccurrenceExpander` **pur** (expansion depuis l'ancre, clamp mensuel, DST gap→forward/overlap→première, until inclusif, count, borne MAX), `RecurrenceRule`/`OccurrenceInstant`/`Occurrence` (VO), `HolidayExcluder` (exclusion vacances Lot 3, préserve les overrides explicites), `AgendaReader` (assemblage + tri §3.6), `EventWriter` (validation création), `OccurrenceEditor` (override/EXDATE §3.4), `SeriesSplitter` (this-and-future + scission idempotente §3.5), `UpcomingEventsFinder` (§3.7), `AgendaPublisher`.
- **Endpoints** : `GET/POST /families/{id}/events`, `PATCH/DELETE /events/{id}?scope=occurrence|series` ; Voter `agenda` (existant) via `AgendaAccess`, scope `X-Family-Id`, RFC 7807 (`AgendaInputException`/`NotFound`/`Conflict`), `Idempotency-Key` (durable en base), verrou optimiste `expectedVersion`→409. Topic Mercure `/families/{id}/agenda` ajouté aux claims (`agenda ≥ view`).
- **Tests** : unitaires expansion (13), intégration reader (3), matrice Voter agenda (RBAC), fonctionnels API (32 : CRUD, override/EXDATE, scission, validations 422, idempotence, autz, scope, régressions de revue). **Backend 207 tests / 1609 assertions, couverture 85,3 %**, PHPStan niv.8 (0), cs-fixer. OpenAPI exporté + client Dart régénéré (event_client), mobile 116 tests verts. Registre RGPD à jour (Event/event_child documentés, aucun champ `#[PersonalData]`/🔒 introduit — décision §5/§7). i18n `agenda.*` ICU.
- **Revue de code adversariale (backend)** : workflow 7 dimensions → vérification adversariale. **6 constats confirmés, tous corrigés** : **P1** idempotence concurrente (Idempotency-Key : 500 → 201 rejoué, sérialisée par `pg_advisory_xact_lock`) ; **P2** overrides orphelins après changement de `freq` (ignorés au rendu §4.11), scission `freq=none` (le « tail » dégénère proprement en ponctuel), verrou optimiste TOCTOU (`em->lock(OPTIMISTIC)` + capture `OptimisticLockException` → 409), titre en espaces (422 après trim), dates non-ISO8601/sans offset (422). 2 P3 réfutés (timestamptz vs timestamp = précédent Lot 3 accepté ; `findBySeriesId` non scopé = tous les appelants passent déjà un maître scopé).
- **E2E web** : `tests/lot4-agenda.spec.ts` (création événement, série hebdo, filtre catégorie, édition, suppression, « À venir » Accueil). **Playwright 13/13 verts.** Temps réel non testé en E2E (hub Mercure absent de la stack `e2e-local.sh`, comme au Lot 3) — couvert côté backend (`RealtimeTokenTest` topic agenda + `AgendaPublisher`).
- **Correctif i18n** trouvé par l'E2E : une chaîne YAML mal échappée (`\'` en apostrophes simples au lieu de `''`/double-quotes) cassait le parsing du catalogue fr → échec de l'e-mail d'inscription (async→sync en E2E). Corrigé + garde-fou `TranslationCatalogTest` (les tests fonctionnels ne parsaient pas le catalogue).
- **Fixtures démo** : différées (cohérent avec Lots 2-3, `AppFixtures` stub ; seeding manuel dans les tests).

- **Backend** : **158 tests / 1137 assertions**, couverture **85,6 %** (≥80 %), PHPStan niv.8, cs-fixer, schéma en sync.
- **Mobile** : feature `custody` (repo + providers scopés + écran calendrier + écran Paramètres › Garde). **116 tests** verts, couverture **78,3 %** (≥70 %), `dart analyze`/`format` propres, client sans drift.
- **Revue de code (workflow adversarial, 7 dimensions)** : 15 constats confirmés (0 P0). **Corrigés** : 2 P1 (règles vacances calculées sur période complète et non la fenêtre clampée → idempotence ; `end_date` Opendatasoft = jour de rentrée exclusif, −1 jour), 5 P2 (ancre future refusée ; balayage idempotent de l'import ; normalisation fuseau calendaire Europe/Paris dans le moteur ; itération calendrier mobile insensible au DST ; exception refusant un titulaire non-parent), 5 P3 (accessibilité clavier du calendrier web ; `?month` invalide rejeté ; retours d'erreur mobile sur échange/enregistrement impossibles ; ancre mobile par défaut = aujourd'hui). **Reportés (justifiés)** : nichage de la route `PUT /holidays/{id}/rule` (maintenabilité, accès déjà re-vérifié), noms de mois en dur mobile (cohérent avec `frenchMonth` web, unique locale fr-FR livrée).
- **UI web Garde** : écran `GET /garde` (calendrier mensuel coloré A/B, jours exceptionnels en bordure pointillée, marqueur vacances, stats du mois, « échanger ce jour » au clic, builder de rythme dont `custom` 14 jours, exceptions par plage, import + règles vacances), onglet masqué si famille unie, Voter `custody`. **Temps réel** câblé : `CustodyPublisher` (Turbo Stream `refresh` sur `/families/{id}/custody`) sur les 3 chemins de mutation + `<turbo-stream-source>` + cookie Mercure scopé. **E2E Playwright : 7/7** (dont parcours Garde complet).
- **UI mobile Garde** : feature `lib/features/custody` — `CustodyScreen` (calendrier mensuel coloré A/B déterministe à partir des jours du mois courant, jours exceptionnels/vacances distingués, légende, stats, liste d'exceptions + suppression, section vacances + import, détail du jour + « Échanger ce jour ») et `CustodySettingsScreen` (builder de rythme `alternating`/`2-2-3`/`custom` 14 nuits, ancrage, jour/heure d'échange, parents A/B). Entrée via la carte « Garde » de l'accueil (masquée pour famille unie). Pull-to-refresh (SSE mobile reportée). 4 nouveaux clients dans `ApiClients`.
- **Reste Lot 3** : revue de code (comme Lots 1 & 2).

### Surface backend Lot 3 livrée
- **Moteur** (`Service/Custody/`) : `BasePatternResolver` (4 modèles, motif 2_2_3 figé), `HolidayRuleResolver` (5 genres dont `eve_alternating` ancré année civile), `CustodyDateMath` (dates calendaires — **bug de fuseau évité** : comparaison Y-m-d, pas d'instants), `CustodyGenerator` (pipeline base→vacances→exceptions, **upsert idempotent** `(family,date)`, advisory lock, **passé figé**, priorité exception>holiday>base), `CustodyStatsCalculator`, `PatternWriter`/`ExceptionWriter`/`RuleValidator`.
- **Adaptateur vacances isolé** (`Holidays/`) : interface `SchoolHolidaysProvider` + impl Opendatasoft v2.1 (UTC→Paris, filtrage population, cache, pagination) + `SchoolHolidayImporter` (upsert idempotent) + double de test.
- **Async/Scheduler** : messages `RegenerateCustody`/`ImportSchoolHolidays`/`ExtendCustodyHorizon`/`RefreshSchoolHolidays` + handlers, routés async ; Scheduler (horizon quotidien, vacances mensuel).
- **Endpoints** : `GET/PUT /custody/pattern`, `GET /custody/days`, `GET/POST/DELETE /custody/exceptions`, `GET /custody/stats`, `GET/POST /holidays`, `POST /holidays/import` (202), `PUT /holidays/{id}/rule`. Scope + Voter `custody` ; famille `intact` → **404 lecture / 409 mutation** ; topic Mercure custody dans les claims.
- **Modèle** : `CustodyPattern`/`CustodyDay`/`CustodyException`/`SchoolHoliday`/`HolidayRule` (+ enums) ; migration `Version20260630222450` (unicité `(family,date)`, clé naturelle vacances, FK membres en `SET NULL`).

### Surface Lot 2 livrée
- Entités **Child** (first_name au registre non chiffré §5.4#4, info jsonb paires libres, verrou optimiste) et **Note** (`text` 🔒 chiffré) ; migration `Version20260630205954`.
- Politique `ChildNotesPolicy` 4 actions (CHILD_VIEW/CHILD_MANAGE/NOTE_VIEW/NOTE_EDIT, garde-fou child≠structure §3.3) + `canMutateNote` (auteur/parent §3.7) ; services `AgeCalculator` (horloge injectable), `InfoNormalizer`, `NoteDateValidator`, `AccessRightsBuilder`, `ChildAccess` (résolution scopée 404), `ChildWriter`, `NoteWriter`, `NotePublisher` (Mercure best-effort).
- Endpoints : `GET/POST /families/{id}/children`, `GET/PATCH/DELETE /children/{id}`, `GET /children/{id}/access`, `GET/POST /children/{id}/notes`, `PATCH/DELETE /notes/{id}` (auteur/parent).
- Temps réel : push Turbo Stream sur `/families/{id}/notes` après commit ; topic ajouté aux claims Mercure si `children_notes ≥ view` (`RealtimeTokenProvider`).
- Web : écran Enfants master-détail (`/children`) — fiche, infos pratiques, **bandeau anti-données-de-santé**, notes (+ `turbo-stream-source` Mercure + cookie d'abonnement), bloc Accès & droits. Stimulus `children`.
- Mobile : écran Enfants + fiche (notes, bandeau anti-santé) via `ChildrenRepository`, branchés dans Réglages.
- Registre RGPD : `Child.firstName`, `Child.info`, `Note.text` 🔒. OpenAPI exporté + client Dart régénéré.
- E2E harness : `e2e-local.sh` vide désormais `cache.app` (compteurs rate-limit) avant les tests.

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

> Le §3 ci-dessus est le **suivi du Lot 0**, entièrement ✅ ; il est conservé comme archive. Le
> chantier courant n'est plus un lot mais la **revue web desktop** (§0), dont le plan et le journal
> détaillé vivent dans `specs/revue-web-desktop-2026-07.md`.

1. Lire le §0 (« Où en est-on ? ») puis le **journal de `specs/revue-web-desktop-2026-07.md`**, de la
   fin vers le début : la dernière entrée porte le point de reprise exact.
2. Démarrer la base : `cd backend && docker compose up -d database` (+ `mercure mailer` au besoin).
   **Sans le conteneur, `symfony` retombe sur un PostgreSQL local et l'auth échoue.**
3. Vérifier l'état sain avant de toucher au code (toutes ces portes doivent être vertes) :
   ```bash
   cd backend && symfony php bin/phpunit                 # 341 tests / 2778 assertions
   vendor/bin/phpstan analyse --memory-limit=1G          # niveau 8, 0
   PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run
   symfony console lint:twig templates                   # 30 fichiers
   cd ../web-e2e && ./scripts/e2e-local.sh               # 21/21
   cd ../mobile && flutter test && dart analyze          # 180 tests, 0 issue
   ```
4. **Point de reprise : étape 6, vague 2.** Voir « Point de reprise immédiat » au §0.
5. **Re-vérifier chaque constat dans le code avant de le corriger.** Les constats datent du
   2026-07-27 et les étapes 1 à 5 ont beaucoup remanié ces fichiers ; certains ont évolué, et la
   vague 1 a montré que des tests existants pouvaient *valider* le défaut décrit.
6. À chaque jalon : MAJ du §0 + de la date en tête + une entrée dans le journal de la revue.

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

### 2026-08-27 — Revue de code de l'application mobile Flutter : revue conduite, plan établi

**Périmètre.** Totalité du code mobile écrit à la main : 13 251 lignes de Dart (`mobile/lib/**`, hors
`lib/api/generated/` — généré depuis l'OpenAPI, non recevable comme constat), 4 005 lignes de tests,
catalogue ARB (268 clés), `pubspec.yaml`, `analysis_options.yaml`, `tool/check_coverage.py`,
configuration Android/iOS (dont Fastlane), job CI `mobile`. Croisé avec `contracts/openapi.json` et,
pour chaque comportement dépendant du serveur, le code PHP de `backend/src/`.

**Méthode.** Workflow multi-agents : 9 axes lus **intégralement** en parallèle (sécurité/auth/stockage
· offline riche outbox+cache · réseau/intercepteurs/dépôts · état Riverpod/routage/cycle de vie async
· garde · agenda + overlay familles liées · écrans métier restants · design system §8/i18n/a11y ·
qualité des tests et conformité projet), puis **vérification adversariale** par lots de 4 constats,
chaque vérificateur mandaté pour **réfuter** (« dans le doute, réfute ») et pour **réévaluer la
gravité**. 29 agents, 857 appels d'outils, 3,07 M tokens, 16 min.

**Résultat : 81 constats explorés, 73 après dédoublonnage, 68 confirmés** (1 P0, 14 P1, 40 P2, 13 P3),
**5 réfutés**. Plan ordonné en 11 étapes : `specs/revue-mobile-flutter-2026-08.md`. Détail intégral
(preuve, scénario de panne, correction proposée, verdict et rectifications du vérificateur) :
`specs/revue-mobile-flutter-2026-08-annexes.md`.

**Constat structurant — l'app mobile ne fonctionne aujourd'hui ni en ligne ni en release.** Trois
défauts indépendants, chacun suffisant :

- **P0 `C17`** — `ApiRoutes.isFamilyScoped()` (`api_routes.dart:14`) classe les chemins par
  `contains` : `path.contains('/api/families')` dé-scope donc **toutes** les ressources métier, qui
  vivent précisément sous `/api/families/{familyId}/…` (members, children, events, exchanges,
  custody/*, holidays, links, invitations, overlay-preferences). `X-Family-Id` n'est jamais posé, et
  `FamilyScope::resolve()` (`backend/src/Security/FamilyScope.php:59-62`) répond **400
  `/errors/family-scope-required`**. Aucun dépôt ne compense (tous documentent « posé par
  l'intercepteur »). Corollaire `C18` : `/api/me` est une **sous-chaîne** de `/api/memberships`, donc
  modifier un droit ou retirer un membre part aussi sans scope.
- **P1 `C4`/`C63`** — la permission `INTERNET` n'est déclarée que dans les manifestes `debug` et
  `profile` (valeurs par défaut du template Flutter) : un APK/AAB **release** n'émet aucune requête.
  Contrôlé aussi côté fusion de manifeste — aucun plugin ne l'apporte.
- **P1 `C1`** — `restoreSession()` capture `ApiException`, qui englobe les erreurs **réseau**, et
  purge les jetons : démarrer hors-ligne **déconnecte** l'utilisateur et lui ferme l'accès au cache
  du Lot 8, annulant l'offline riche au cold start.

Les deux premiers ont été **re-vérifiés à la main, hors chaîne d'agents**, avant rédaction (lecture
des trois manifestes, des chemins réellement émis par le client généré, et de `FamilyScope.php`).

**Cause structurelle.** Il n'existe **aucun test de bout en bout mobile contre un backend réel** :
les 180 tests montent des écrans sur dépôts mockés ou `HttpClientAdapter` scripté. Le seul test du
scope famille (`test/core/network/interceptors_test.dart:31`) interroge `/api/custody`, **chemin
absent du contrat** — d'où l'aveuglement complet. S'y ajoutent **deux tests verts couvrant du code
mort** (`retry()` et `purgeSynced()` n'ont aucun appelant de production : le statut `rejected` de
l'outbox n'est branché sur aucune UI et la base locale n'est jamais purgée), une CI qui **n'applique
aucun lint** (`dart analyze` sans `--fatal-infos`, donc `analysis_options.yaml` est décoratif), et un
**dénominateur de couverture faux** (un fichier jamais importé par un test est absent de `lcov`, donc
la couverture réelle est inférieure aux 75,8 % affichés).

**Familles de défauts au-delà du structurant** : aucune donnée locale n'est purgée au changement de
compte (outbox rejouée sous l'identité suivante, cache Drift cloisonné famille seulement,
préférences de notification servies au compte suivant, `allowBackup` Android actif) ; machine d'état
de l'outbox incomplète (entrée `inFlight` orpheline jamais reprise, `Idempotency-Key` régénérée à
l'enfilement → doublon après timeout, `flush()` sans verrou) ; agenda borné au mois courant avec
dates calculées en UTC ; écran Garde sans contrôle du droit `custody:edit`, navigation de mois inerte,
et §8.1 non respecté (le nom du parent en clair n'est exposé ni visuellement ni aux lecteurs d'écran,
aucun `Semantics` dans toute l'app) ; `intl`/`DateFormat` jamais utilisé (dates ISO brutes affichées,
mois en dur) et une quarantaine de libellés ARB écrits **sans diacritiques**.

**Écartés (5)** : connectivité prétendument non initialisée (faux pour `connectivity_plus` 7.1.1) ;
divergence PHP/Dart sur les espaces Unicode (réfutée en exécutant PHP : `\s` avec le modificateur `u`
active `PCRE2_UCP`) ; récurrence agenda et outbox notes/messages (limitations explicitement assumées
et documentées) ; viewport des tests widget (aucun défaut observable démontré).

**Aucune modification de code à ce stade.** Portes inchangées : `dart analyze` 0 issue, **180 tests
verts**, couverture **75,8 %**. ⚠️ Ce chantier nuance fortement le « Lot 8 TERMINÉ / v1 complète »
**côté mobile**.

### 2026-07-10 — Correctif UX (web) : parcours d'invitation « register-first » + confirmation explicite
- **Problème** : le lien d'invitation e-mail (`GET /invitations/accept?token=T`) ouvert par un invité
  **non connecté** redirigeait vers `/login`, **en perdant le token** et sans indiquer qu'il fallait
  créer un compte. L'invité·e était bloqué·e.
- **Correctif** (`HomeController::acceptInvitation` + `SecurityController::login`) :
  - **Anonyme** → nouveau template `app/join.html.twig` : formulaire de **création de compte
    contextualisé** (titre « Rejoindre {famille} », e-mail nominatif **pré-rempli en lecture seule**,
    aide « 12 caractères min. ») + lien persistant « déjà un compte ? se connecter pour rejoindre ».
    Le token est mémorisé en **session** (`HomeController::PENDING_INVITATION`) **uniquement s'il est
    valide** (sinon un lien mort réarmerait à tort la redirection post-connexion).
  - Le token **survit** à register → vérif e-mail → login (même navigateur). `SecurityController::login`
    calcule un `redirectAfter` = `app_accept_invitation` + affiche une bannière quand une invitation
    est en attente en session.
  - **Connecté** → `app/accept_invitation.html.twig` devient un **écran de confirmation explicite**
    (bouton « Rejoindre {famille} », **plus d'auto-POST silencieux**) avec état d'erreur/invalide
    propre. Le token n'est **purgé** qu'au **succès confirmé**, via la nouvelle route
    `app_invitation_accepted` (`GET /invitations/accepted`) → purge session → `app_settings`. Un échec
    (mauvaise adresse, réseau) laisse donc une **reprise** possible.
- **i18n / « vérifier les traductions création de compte »** : constat = l'UI web est **100 % FR
  codée en dur** (divergence **assumée et documentée au Lot 5** ; `|trans` côté Twig rendait la clé
  brute en test → i18n ICU portée par API + mobile + e-mails). Copie FR de l'écran register relue
  (accents/clarté OK). **Aucune migration i18n** (décision verrouillée, hors demande).
- **Revue adversariale** (workflow 7 dimensions → vérification skeptique par constat, **19 agents**) :
  16 constats bruts, **12 confirmés → tous traités**. **P1** : token invalide écrit en session avant
  validation → stockage conditionné à `preview.valid`. **P2** : acceptation silencieuse (auto-POST) +
  purge prématurée du token → confirmation explicite + purge au retour de succès. **P2** : dead-end
  « compte déjà existant » → lien de connexion persistant hors formulaire (visible après soumission).
  **P2/P3 tests** : ajout de la couverture manquante (branche session-only authentifiée de bout en
  bout ; non-énumération **prouvée** — token erroné ne fuit ni famille ni e-mail ; invitations
  expirée/acceptée/révoquée ; défaut login sans invitation ; route de succès).
- **Portes** : backend **170 tests fonctionnels** (+10, dont 10 nouveaux sur ce flux) / PHPStan 8 : 0 /
  cs-fixer : 0 / `lint:twig` OK ; **E2E Playwright `lot1-family` 2/2** (existant adapté au clic de
  confirmation + **nouveau parcours register-first** complet via Mailpit). **Aucun changement d'API,
  OpenAPI ou mobile** (routes/templates web purs) ; **registre RGPD inchangé** (aucun champ personnel
  nouveau — l'e-mail affiché provient d'une invitation existante, réaffiché via le token secret).

### 2026-07-10 — Lot 9 : CI/CD & build iOS sans Mac (outillage infra)
- **Principe directeur (§0.1/§2.4/§6.5)** : la logique de build/signature ne vit **jamais** dans la
  config d'une CI. Elle vit dans un **`Fastfile` unique** (`mobile/ios/fastlane/Fastfile`). GitHub
  Actions et Codemagic ne sont que deux **déclencheurs** appelant les mêmes lanes ; changer de CI =
  changer un fichier de déclenchement, jamais la logique métier ni le `Matchfile`.
- **Fastlane** (`mobile/ios/`) : `Fastfile` (lanes `build` = `.ipa` App Store signé readonly ;
  `release` = build + `upload_to_testflight` interne, jamais auto ; `certificates` = génération
  MANUELLE readonly:false, mainteneur habilité), `Matchfile` (storage git, type appstore, URL/passphrase/
  auth via env), `Appfile` (bundle id + team_id via env, **pas** d'Apple ID perso — auth ASC par clé
  API), `Gemfile` (fastlane + cocoapods), `ExportOptions.plist` (référence : app-store, signature
  **manuelle**, profil `match AppStore net.coparentplanner.coparentplanner`). Build : `setup_ci`
  (keychain temporaire éphémère) → `match(readonly:true)` → `update_code_signing_settings` (manuel) →
  `flutter build ios --no-codesign` → `build_app` (gym, teamID depuis secret, export vers
  `mobile/build/ios/ipa`). Clé API ASC mémoïsée (`is_key_content_base64:true`).
- **GitHub Actions** (`.github/workflows/build-ios.yml`, **principal**, `macos-latest`) : trigger
  `workflow_dispatch` (choix lane `build`/`release`) **ou** tag `v*` (→ `release`). Job **`mobile-tests`
  (Linux)** en préalable (§3.4 : `flutter test` + `dart analyze` + drift OpenAPI) → `build-ios`
  `needs: mobile-tests`. Étapes : vérif secrets (échec listant les manquants), dérivation
  `MATCH_GIT_BASIC_AUTHORIZATION` depuis `MATCH_GIT_TOKEN`, setup Flutter/Ruby(bundler-cache)/CocoaPods,
  **appel de la lane partagée** (seul point touchant la logique de build), upload artefact
  `ios-ipa-<ref>-<sha>` (traçabilité §6.7, `if-no-files-found: error`). `permissions: contents:read`.
- **Codemagic** (`codemagic.yaml`, **secours**) : workflows `ios-build` + `ios-release`, `triggering.events: []`
  (**manuel uniquement**), groupe de secrets `ios_signing`, Flutter figé `3.38.9`, mêmes étapes (ancres
  YAML `definitions` pour éviter la duplication), **même lane Fastlane**, artefacts `mobile/build/ios/ipa/*.ipa`.
- **Secrets (§4)** : mêmes noms des deux côtés — `MATCH_GIT_URL`/`MATCH_PASSWORD`/`MATCH_GIT_TOKEN`,
  `ASC_KEY_ID`/`ASC_ISSUER_ID`/`ASC_KEY_CONTENT` (`.p8` base64), `APPLE_TEAM_ID`. Aucun secret en clair
  au dépôt ni dans les logs (masqués par la CI, token converti en basic-auth). **Rotation synchronisée**
  documentée. Filet `.gitignore` fastlane (`*.p12/*.cer/*.mobileprovision/*.p8`).
- **Docs** : `infra/INSTALL.md §E` — bandeau « 🚧 À VENIR » **retiré**, aligné sur les fichiers réels
  (formats `ASC_KEY_CONTENT`/`MATCH_GIT_TOKEN`, lane `certificates`, gating `mobile-tests`, rotation) ;
  `CLAUDE.md` (§ Outillage) — commandes `bundle exec fastlane ios build|release|certificates`.
- **Revue adversariale** (workflow 7 dimensions → vérification skeptique par constat, 23 agents) :
  16 constats bruts, **5 réfutés** (tag `v*`→release conforme §3.1 ; `APP_IDENTIFIER` défaut Ruby ;
  Ruby non figé Codemagic ; `Generated.xcconfig` généré par `pub get` ; version Flutter en plage dans
  INSTALL non contradictoire), **11 confirmés/plausibles → tous corrigés** : **P1** `pod install`
  prématuré (aucun `Podfile` commité, généré par `flutter build` → étape retirée des 2 CI, redondante
  avec le Fastfile) ; **P2** filet `.gitignore` des identités confiné à `fastlane/` → règles
  `*.p8`/`*.p12`/`*.cer`/`*.mobileprovision` remontées au `.gitignore` **racine** + `.p8` référencée
  hors dépôt ; **P2** Codemagic ne répliquait pas la porte `flutter test`/`dart analyze` (§3.4 exige
  les 2 pipelines) → ancre `flutter_tests` ajoutée aux 2 workflows ; **P3** `output_name` avec
  extension (double `.ipa`) → sans extension ; `changelog` inopérant sous `skip_waiting…` → retiré ;
  `FLUTTER_VERSION` redondant Codemagic → retiré (clé `flutter:` fait foi) ; chemin artefact doc
  Codemagic `build/…`→`mobile/build/…` ; gems bornés (`fastlane ~> 2.226`, `cocoapods ~> 1.16`) +
  versions documentées (Ruby 3.3) dans INSTALL §E.5.
- **Portes** : YAML `build-ios.yml` + `codemagic.yaml` **re-validés** après correctifs (parse OK,
  gate non-régression présent dans les 2 workflows Codemagic, plus d'étape `pod install`),
  `ExportOptions.plist` **XML valide**, CI existante `ci.yml` **inchangée** (job additif). **Modèle de
  données / API / registre RGPD : non applicable** (§5, lot infra).
- **Limite assumée (hors périmètre §0.3)** : les 5 cases DoD « run réel » (produire un `.ipa` sur
  chaque CI, upload TestFlight, non-régression sur runner) exigent des **credentials Apple réels + un
  runner macOS** — indisponibles depuis ce dépôt. L'outillage est livré, validé statiquement et prêt ;
  l'exécution de bout en bout est une action de mainteneur une fois les prérequis §E.1 fournis.

### 2026-07-10 — Lot 8 (Étape F) : finitions + clôture DoD
- **UI mobile RGPD** (`lib/features/settings`) : `RgpdRepository` (export POST/GET, `DELETE /me`) +
  écran `PrivacyScreen` (Paramètres › Confidentialité) : demande d'export → statut → **lien signé
  temporaire** ; suppression de compte (dialogue de confirmation → anonymisation → déconnexion locale ;
  **refus dernier admin 409** affiché). Client `meExport` ajouté à `ApiClients`. i18n `privacy*`.
- **Cache de lecture étendu** : enfants (`listChildren`) et échanges (`list`) passent par `ReadCache`
  (repli hors-ligne cloisonné famille), en plus de l'agenda. Patron uniforme (`toJson`/`fromJson` du
  modèle généré).
- **Tests** : `privacy_screen_test` (export → lien ; suppression → refus dernier admin). **157 tests
  mobiles** verts, couverture **75,5 %**, `dart analyze` 0, format propre, client généré canonique.
- **DoD Lot 8 — vérifiée** :
  - Tests : backend **280** (unit + fonctionnels : anonymiseur/purge/export/idempotence/chiffrement) ✓ ;
    **E2E Playwright 18/18** (dont installabilité PWA + offline RO + blocage mutation) ✓ ;
    mobile **157** (outbox, cache, bannière, RGPD) ✓.
  - Test de cohérence du registre **vert** ; **test de chiffrement au repos** (dump SQL = ciphertext,
    5 champs 🔒) vert.
  - Migrations fournies (`users.technical_admin`, `idempotency_keys`) + Drift v2 ; OpenAPI exporté à jour,
    client Dart régénéré **sans drift**.
  - i18n ICU (backend + mobile ; manifest/UI web en FR hardcodé, divergence assumée Lot 5) ; registre
    `registre-donnees.md` à jour ; **INSTALL.md** à jour (env RGPD, exports, step-up) ; PHPStan 8 / cs-fixer
    / `dart analyze` propres ; design §8 respecté (palette/typos PWA, badges/bannière mobile).
  - Invariants : dernier admin protégé (409), champs 🔒 chiffrés + anonymisation irréversible, offline web
    dégradé (aucune outbox web), scope famille (caches cloisonnés), pas de données de santé.
- **Reste v1 (finitions non bloquantes, hors périmètre DoD)** : câblage outbox-écriture des autres
  écrans mobiles (garde/échanges) sur le patron agenda ; auto-héberger d'éventuelles pièces jointes
  d'export (avatars) ; store objet UE réel en prod (interface `ExportStorage` prête).

### 2026-07-10 — Lot 8 (Étape E) : E2E Playwright PWA + suite complète
- `tests/lot8-pwa.spec.ts` : (1) manifest servi/valide (`standalone`, icônes) + SW servi à la racine
  (`Service-Worker-Allowed: /`) + enregistrement effectif dans le navigateur ; (2) hors-ligne —
  écran servi depuis le cache du SW (titre applicatif, pas d'erreur brute) + bannière « Hors-ligne »,
  **mutation POST bloquée** (503 synthétique, aucune file), reconnexion → bannière masquée.
- **Bugs attrapés par l'E2E** : (a) le contrôleur `pwa` rechargeait la page à la **prise de contrôle
  initiale** du SW (`clients.claim`) → interrompait le formulaire d'inscription ; corrigé (reload gaté
  aux **mises à jour acceptées** uniquement). (b) `.pwa-banner { display:flex }` **écrasait** l'attribut
  `hidden` (bannière vide visible sur toutes les pages) → règle `.pwa-banner[hidden]{display:none}`.
- **Suite E2E complète : 18/18 verte** — aucune régression (le SW s'enregistre partout de façon
  transparente : pass-through en ligne, network-first pour les navigations).

### 2026-07-10 — Lot 8 (Étape D) : offline mobile riche (outbox + cache + UI)
- **Statuts outbox** alignés §3.1 : `pending / inFlight / synced / rejected`. Résolution **autorité
  serveur** dans `OutboxService._send` : 2xx→synced ; 409/403/422/404/410→rejected (terminal, `lastError`
  qualifié) ; 5xx/réseau/timeout/401→reste pending (retry, `attempts++`). `retry()` (relance manuelle) /
  `discard()` / `purgeSynced()`.
- **Cache de lecture générique** `CacheEntries` (clé `"$familyId::$endpoint"`, **cloisonné famille**) +
  `CacheDao` + helper `ReadCache` (stale-while-revalidate : réseau→cache ; hors-ligne→cache ; erreur
  métier HTTP→propagée). Migration Drift **v2** (+ `MigrationStrategy`).
- **Agenda = écran de référence** : `listEvents` via `ReadCache` (repli hors-ligne), `createEvent`
  routé vers l'**outbox** hors-ligne (`OutboxQueuedException` → « en attente de synchro »). Patron
  extensible aux autres écrans (garde/échanges/enfants) — non encore câblés (polish).
- **Rejeu auto** activé : `OfflineBanner` (ConsumerStatefulWidget) tient `outboxServiceProvider` vivant
  (écoute connectivité) + flush initial. **Bannière hors-ligne** + **compteur « N en attente de synchro »**
  (pluriel ICU) branchés dans le shell. Providers `pendingSyncCountProvider`/`rejectedSyncCountProvider`
  (streams Drift réactifs).
- **Tests** : outbox (transitions de statut, retry, purge, FIFO), `ReadCache` (repli hors-ligne,
  cloisonnement famille, erreur métier propagée), bannière (offline/compteur/masquée). **155 tests
  mobiles** verts, couverture **75,5 %**, `dart analyze` 0, format propre, **client généré canonique**
  (drift = ajouts RGPD uniquement). i18n ARB `offline*`/`sync*`.
- **NB** : le sous-agent délégué au départ a échoué (0 action) ; chantier réalisé directement.

### 2026-07-10 — Lot 8 (Étape C) : PWA web (manifest + service worker + offline)
- **Manifest** (`GET /manifest.webmanifest`, `PwaController`) : `standalone`, thème terracotta, icônes
  192/512 + maskable, servi à la **racine du scope**.
- **Service worker** (`GET /sw.js`, Twig-rendu, `Service-Worker-Allowed: /`) : précache du shell
  (CSS/JS digérés, polices, icônes, page offline) ; `activate` purge les caches d'anciennes versions
  (clé = empreinte des assets) ; `fetch` = **network-first + repli cache pour les navigations**
  (cache **cloisonné par famille** via message du client, §2.2), cache-first pour `/assets/`,
  **blocage des mutations hors-ligne** (503 synthétique + message, **aucune file** — cdc §4.3).
- **Contrôleur Stimulus `pwa`** : enregistrement SW + gestion de mise à jour (skip-waiting/reload),
  bannière persistante « Hors-ligne » / toast « Connexion rétablie », prompt d'installation
  (`beforeinstallprompt`), message « Action impossible hors-ligne » (via postMessage du SW).
- **Polices OFL auto-hébergées** (Bricolage/Hanken TTF, réutilisées du mobile) : suppression de l'`@import`
  CDN Google (offline réellement fonctionnel), `@font-face` réécrit par AssetMapper. Icônes PNG générées.
- **Page hors-ligne** dédiée (`GET /offline`), précachée. Libellés FR hardcodés (cohérent UI web, Lot 5).
- **Portes** : WebPagesTest +2 (manifest/sw/offline + intégration shell), lint Twig OK, `asset-map:compile`
  OK (réécriture des polices). Aucun endpoint `/api` ajouté → OpenAPI inchangé.

### 2026-07-10 — Lot 8 (Étape B) : idempotence serveur générique (§3.1/§6.3) + revue
- **Middleware** `IdempotencySubscriber` (kernel.request prio 4 après firewall / kernel.response) : sur
  `POST /api/*` (hors `/api/auth`) portant `Idempotency-Key`, scope `(user, endpoint, clé)` + empreinte
  du payload (query+body). Verrou `pg_advisory_xact_lock` sérialisant la réclamation `in_progress`.
- **Store** `IdempotencyKey` (unique `(user, clé)`) + `IdempotencyStatus` ; **corps de réponse mémorisé
  CHIFFRÉ au repos** (`#[PersonalData]` encrypted, §4.8) → un dump SQL ne révèle rien. Rejeu à l'identique
  d'une mutation aboutie ; **409** sur payload divergent (§4.2). Migration `Version20260710150000` ;
  rétention `IdempotencyKeys` réactivée dans `RetentionPolicy` + purge (TTL 48 h à l'heure près).
- **Revue adversariale ciblée** (2 dimensions → vérification) : **2 constats corrigés** — **P1** : les 4xx
  transitoires (429/403/404-réplication) étaient mémorisées et rejouées → gel/perte de mutation ⇒ **seuls
  les 2xx sont mémorisés**, toute erreur libère la clé ; **P1/P2** : une entrée `in_progress` orpheline
  (worker mort) bloquait la clé jusqu'au TTL ⇒ **bail de fraîcheur** (réclamation après 5 min).
- **Coexistence** : l'idempotence par colonne des Lots 4/6 (Event/Exchange/Message) reste en défense en
  profondeur ; le middleware court-circuite avant le contrôleur sur rejeu (aucun double effet).
- **Portes** : backend tests idempotence +5 (rejeu, scope par utilisateur, incohérence payload 409,
  clé non brûlée par une 4xx), PHPStan 0, cs-fixer 0. OpenAPI inchangé (comportement d'en-tête).

### 2026-07-10 — Lot 8 (Étape A) : backend RGPD Manager complet + revue de sécurité
- **Décisions** (validées) : rétention en **config déclarative** (`RetentionPolicy`, pas de table) ; export
  sur **filesystem local + route signée HMAC** derrière `ExportStorage` (prod → objet UE) ; auth renforcée
  par **step-up** (ré-authentification → jeton court réutilisable dans sa fenêtre).
- **Registre & rapports** : `PersonalDataRegistry` exposé via `GET /admin/rgpd/register` ; `ComplianceReporter`
  (`/admin/rgpd/compliance-report` : rétentions, écarts = champs chiffrés sans entrée, hébergement UE) ;
  journal `GET /admin/rgpd/operations` (filtré/paginé) via `RgpdOperationRepository`.
- **Anonymiseur** (`DELETE /me` + purge) : neutralisation irréversible (`fullName`/`phone`→null, e-mail
  pseudonymisé, hash écrasé), fils **préservés** (retrait des memberships → `SET NULL` auteur), **dernier
  admin protégé** (409), sessions révoquées, **invitations du sujet scrubées** (e-mail chiffré réversible),
  idempotent.
- **Purge planifiée** (`POST /admin/rgpd/purge?dryRun=`, Scheduler /jour) : dry-run par défaut (aucune
  écriture), live idempotent, **isolation d'erreur par compte**, exclusion dernier admin/admin technique,
  bornes en jours civils Europe/Paris **rendues en UTC** ; exports purgés à l'heure près (TTL).
- **Export** (`POST /me/export` 202 → worker async idempotent **réclamation atomique** → `GET /me/export/{id}`
  + **lien signé temporaire** → téléchargement `no-store`, scopé sujet, tiers 403, rate-limité).
- **Auth admin** : `User.technicalAdmin` → `ROLE_ADMIN` (dérivé de l'entité, jamais dans le JWT),
  `access_control ^/api/admin`, `AdminAccess` (step-up requis), `POST /admin/step-up` (rate-limité anti
  brute-force). Migration `Version20260710120000` (`users.technical_admin`).
- **AuditLog** : 13 actions RGPD/admin, sans clair sensible en `metadata` (avertissement ancré) ;
  test append-only. **Chiffrement au repos** : test qui prouve par lecture SQL brute que les 5 champs 🔒
  sont du ciphertext.
- **Revue de sécurité adversariale** (5 dimensions → vérification réfutatrice, 15 agents) : **8 confirmés
  corrigés** — P2 (invitations non scrubées, step-up sans rate-limit, purge sans isolation d'erreur),
  P3 (download sans `no-store`, idempotence export concurrente, sur-rétention exports, décalage fuseau
  borne/ancre, `metadata` json hors garde-fou → différé documenté). **2 réfutés** (admin `Suspended`
  inatteignable ; FK `SET NULL` append-only théorique).
- **Portes** : backend **273 tests / 2304 assertions**, PHPStan niv.8 (0), cs-fixer (0), `lint:container`,
  schéma en sync ; OpenAPI exporté (7 chemins RGPD) + client Dart régénéré (mobile **142 tests**, analyze 0).
  Registre + INSTALL.md à jour. **Reste Lot 8** : idempotence serveur générique (§6.3), PWA web, offline
  mobile riche, E2E Playwright.

### 2026-07-09 — Lot 7 : Notifications e-mail — préférences + pipeline + déclencheurs + rappels + UI
- **Préférences (US-SET-1)** : `GET/PUT /api/me/notification-prefs` (scope utilisateur) — toggles e-mail
  par catégorie v1 (`custody`/`agenda`/`exchanges`/`invitation` ; `expenses`/`push` masqués v2), quiet-hours
  `HH:MM` (validées : format, bornes complètes, plage <23 h), locale. `NotificationPref` finalisée +
  repo (opt-out par défaut §3.6) ; quiet-hours sur `User` (migration `Version20260708215230`).
- **Pipeline async (§3.1)** : `NotificationEmitter` (post-commit, **best-effort** §4.6) → `RecipientResolver`
  (memberships actifs filtrés par Voter du domaine, sécurité prime, hors auteur) → filtrage préférence →
  `SendNotification` (Messenger `async`, retry). `SendNotificationHandler` **idempotent** (`NotificationDispatch`
  clé unique), **quiet-hours** (report `DelayStamp`, plage circulaire/DST), **locale** (§3.5), rendu
  `emails/notification.html.twig` localisé, audit `notification.sent` **sans clair 🔒**. Minimisation :
  lien vers l'app, jamais le texte d'un message (`Message.text` 🔒 jamais embarqué).
- **Déclencheurs** (émission post-commit) : échanges (message, ouverture, décision tranchée, clôture),
  agenda (création/modif clé-par-version/suppression), garde (exception créée/supprimée, régénération du
  modèle → une seule notif « planning modifié »). Migration `NotificationDispatch` `Version20260708220349`.
- **Rappels d'échéance (US-NOTIF-6, §3.9)** : `SendDeadlineReminders` (Scheduler /15 min) → décisions
  ouvertes à échéance : « échéance demain » (<24 h) puis « en retard », dédup idempotente ; tranchée → rien.
- **UI web** : Paramètres › Notifications (sous-nav, toggles switch iOS, quiet-hours) via Stimulus
  `notif-prefs`. **UI mobile** (sous-agent) : écran Notifications (Switch/quiet-hours) + entrée Réglages.
- **Registre RGPD** : `NotificationPref`/quiet-hours (préférences) documentés (non chiffrés, non sensibles).
  i18n `notification.*`/`email.notif.*` ICU. Route Messenger `SendNotification → async`.
- **Revue ciblée** : la régression e2e (transport `sync` exécutant l'envoi dans la requête custody) a
  révélé le besoin du best-effort (§4.6) ; tests dédiés qui exécutent le handler + rendent le template →
  chaîne notif validée en isolation. Correctif fragilité e2e (date d'exception codée en dur → dynamique).
- **Portes** : backend **259 tests / 2149 assertions**, PHPStan niv.8 (0), cs-fixer (0), schéma en sync ;
  **E2E Playwright 15/15** ; mobile **142 tests**, couverture **75,1 %**, `dart analyze`/`format` propres,
  client généré intact. OpenAPI exporté (`notification-prefs`) + client Dart régénéré. INSTALL.md à jour.
- **Assumés documentés** : invitation garde son handler dédié (non re-routé) ; coalescence fine des rafales
  et rappels d'agenda récurrents = hors périmètre livré (anti-spam de masse assuré à l'émission).

### 2026-07-05 — Lot 6 : Échanges (temps réel) — backend + contrat + web + mobile + E2E
- **Modèle** : `Exchange` (discussion/décision, statut open/decided, échéance date, auteur/enfant
  `SET NULL`, `decidedOption`, idempotence, verrou optimiste), `ExchangeOption` (≥2, position unique),
  `Message` (`text` chiffré 🔒 AES-256-GCM, `SET NULL`), `OptionVote` (unicité `(exchange,membership)`).
  Migration `Version20260705145717` ; schéma en sync.
- **Endpoints** (API Platform) : `GET/POST /families/{id}/exchanges?status=`, `GET/PATCH /exchanges/{id}`,
  `POST /exchanges/{id}/decide`, `POST /exchanges/{exchangeId}/messages`. Voter `exchanges`, scope
  `X-Family-Id`, RFC 7807, `Idempotency-Key` (advisory lock).
- **Moteur décision** (`DecisionEngine`) : électorat = actifs `exchanges=edit` ; bascule par consensus +
  **majorité stricte** (égalité → reste ouvert) ; forçage/clôture explicite (PATCH `decidedOptionId`) ;
  `decide` sous **verrou pessimiste FOR UPDATE + refresh** (2ᵉ concurrent → 409). `OverdueChecker` dérivé
  (fin de journée fuseau famille, DST). Badge = `COUNT(status=open)`.
- **Temps réel** : `ExchangePublisher` Mercure **post-commit best-effort** (topics liste + détail).
  Choix : Turbo Stream `refresh` (morphing) — **aucun clair de message dans le payload Mercure** ; les
  clients autorisés re-fetchent via l'API (plus conservateur que la projection HTML de la spec §3.7).
  Claims Mercure `exchanges` (liste + template détail) filtrés `exchanges≥view`.
- **UI web** : `/echanges` maître-détail (`ExchangeController` + `echanges.html.twig` + Stimulus
  `exchanges`), badge nav via `ExchangeBadgeExtension`, `turbo-stream-source` + cookie Mercure.
- **UI mobile** (sous-agent) : `lib/features/exchanges` (repo + providers + écran liste/détail/création),
  carte « Échanges » + **badge** sur l'accueil. Contraintes assumées : filtrage/tri **client** (`?status`
  non exposé par le client généré), **SSE différé** (pull-to-refresh, cohérent Lots 3/4), gating `edit`
  délégué au serveur. i18n ARB `exchange*` (pluriels ICU).
- **RGPD** : `Message.text` 🔒 au registre + `#[PersonalData]` (test de cohérence vert). Audit
  `ExchangeOpened/Decided/Closed`. Aucun champ santé.
- **Revue ciblée** (concurrence decide, idempotence, anti-fuite Mercure, overdue/décidé, scope 404,
  auteur anonymisé) : aucun correctif requis.
- **Portes** : backend **241 tests / 2009 assertions**, PHPStan niv.8 (0), cs-fixer (0), lint Twig (22) ;
  **E2E Playwright 14/14** (discussion+message, décision bascule électorat=1, marqueur « en retard ») —
  temps réel hors stack E2E (Mercure absent, cf. Lots 4/5), couvert backend ; mobile **140 tests**,
  couverture **75,2 %**, `dart analyze`/`format` propres, client généré intact. OpenAPI exporté.
  Aucune dépendance ajoutée (INSTALL.md inchangé).

### 2026-07-05 — Revue graphique : application des corrections de conformité (web + mobile)
- Source : `docs/revue-graphique-maquettes.md` (113 écarts confirmés, 0 P0). Appliqués par lots A→I.
- **Composants partagés créés** : web `.avatar` (7 tailles, initiale) dans `app.css` ; mobile
  `lib/core/widgets/initial_avatar.dart` (`InitialAvatar`) + `large_title_header.dart`
  (`LargeTitleHeader`, grand titre iOS displaySmall 30px remplaçant les AppBar 22px).
- **Web** (`backend/assets/styles/app.css` + templates Twig) : tokens (eyebrow, `.main` surface,
  `.auth__card`, h1) ; Accueil (grille 1.4fr/1fr, cartes « À venir » + bloc date) ; Garde (layout
  2 col, stats tiles A/B colorées, cellules carrées, nav mois carrée, périodes spéciales, légende
  noms réels, sous-titre) ; Agenda (barre latérale catégorie, chips teintés, overlay carte +
  interrupteur iOS `.switch`, CTA compact `<details>`, colonne heure Bricolage) ; Enfants (détail
  2 col, héros, cartes notes, section Informations, avatars) ; Paramètres (sous-nav 248px, « Mon
  compte », membres cartes + badges + édition repliée, panneau invitation teinté, CTA `.btn--ink`) ;
  Shell sidebar (switcher sombre déplacé depuis les 5 en-têtes, icônes nav SVG, vignette marque,
  eyebrow « Navigation », bloc profil + déconnexion).
- **Mobile** (sous-agent) : Accueil/Garde/Agenda/Enfants/Paramètres/onglets — tous IDs mob-* traités
  (grand titre, cartes, avatars, filtres, nav mois présentationnelle, exception pointillée, overlay
  anneau, FAB encre, cartes radio rôle). Onglets = Accueil·Garde·Agenda·Enfants (Réglages via
  engrenage d'accueil) ; branche Garde toujours présente (indexedStack stable). ARB étendu
  (`tabCustody`, `homeAllAgenda`, `custodyScreenTitle`, `agendaFilterAll`, `agendaDayEvents`…).
- **Différés justifiés (3 P2)** : WP-07/WP-08 (selects droits/type famille → cartes radio : le
  contrôleur Stimulus `settings` lit ces `<select>` et l'E2E « ajuster droits » en dépend) ;
  U1-06 mobile (nom du responsable sur l'accueil : imposerait un appel réseau dans les tests
  d'accueil aux providers non surchargés — l'heure est affichée).
- **Portes** : backend **218 tests / 1772 assertions**, PHPStan niv.8 (0), cs-fixer (0), lint Twig
  (21) ; **E2E Playwright 14/14** (5 specs + 2 assertions WebPagesTest adaptées aux changements
  intentionnels : CTA repliés, overlay carte, stats noms réels, titre « Planning de garde ») ;
  mobile `dart analyze` (0), `dart format` (0), **flutter test 124**, couverture **78,0 %**, client
  généré intact. Aucune dépendance ajoutée (INSTALL.md inchangé). Divergences assumées préservées
  (dates dynamiques, pas de champs santé, dépenses v2 masquées, i18n).

### 2026-07-02 — Lot 5 (overlay) : Étape E — E2E Playwright + clôture du lot
- `lot5-overlay.spec.ts` : deux familles (A, B) via UI, événement chez B, liaison A↔B pilotée par API
  (`page.request` partage le cookie JWT ; header `X-Family-Id`) — B propose, A (destinataire) active —
  puis toggle overlay via l'UI. Assertions : activation → événement lié **RO** (badge « Famille liée »,
  0 formulaire/bouton d'édition dans la tuile) ; désactivation → tuile liée disparue. **1 test vert (6,4 s).**
- Piège évité (leçon Lot 4) : la case overlay déclenche un reload Stimulus → on force `checked` + dispatch
  `change` via `evaluate` (un clic natif serait détaché par le reload).
- **Lot 5 clôturé.** DoD complète (voir §0). Prochain : Lot 6 — Échanges temps réel.

### 2026-07-02 — Lot 5 (overlay) : Étape D — UI mobile
- `OverlayRepository` : liens actifs + préférences (clients générés) ; événements projetés via appel dio
  **brut** (`?overlay=` non exposé par le client retrofit généré) → modèle local `OverlayEvent` (projection
  RO, ne parse que titre/horaires/catégorie). Providers `overlayEventsProvider` (agenda),
  `overlayGardeEventsProvider`/`overlayGardeDatesProvider` (garde, spanne les multi-jours, garde-fou 90 j).
- Agenda : section toggles par lien (PUT préférence vue `agenda`) + événements liés **RO** fusionnés par
  jour (badge « Famille liée », `#9C6FB0`). Garde : toggles vue `garde` + **pastilles `#9C6FB0`** par jour
  + entrée de légende ; garde jamais modifiée. Lecture overlay **best-effort** (échec → n'impacte pas
  l'agenda/garde propres). i18n ARB `overlay*` (mobile fiable, pas de `default_locale` conflictuel).
- Nouveau client `OverlayPreferenceClient` câblé dans `ApiClients`. Tests existants agenda/garde : ajout
  d'un `emptyOverlayRepository()` en override (empêche tout appel réseau réel). Nouveau `overlay_test.dart`
  (agenda RO + bascule OFF→ON + section masquée sans lien ; garde pastille présente/absente + toggle).
- Portes : mobile **124 tests**, couverture **77,9 %**, `dart analyze` propre ; contrat inchangé (pas de
  régénération de client).

### 2026-07-02 — Lot 5 (overlay) : Étape C — UI web
- `/agenda` : toggle overlay par lien actif (PUT préférence + reload via Stimulus), événements liés RO
  (badge « Famille liée », `#9C6FB0`), fusion/tri par jour, état vide. `/garde` : pastilles d'activité
  `#9C6FB0` par jour + toggle custody (garde jamais modifiée). CSS `--linked`.
- Piège : les libellés overlay via `|trans` rendaient la clé brute en test (l'UI web du projet hardcode
  le FR partout, `trans` peu fiable côté web) → libellés hardcodés dans les Twig (cohérent), i18n conservé
  pour l'API/mobile. WebPagesTest overlay vert.
- Portes : backend **218 tests / 1772 assertions, couverture 85,6 %**, PHPStan niv.8, cs-fixer, lint Twig.

### 2026-07-01 — Lot 5 (familles liées / overlay) : Étape A — backend + contrat
- Entité `OverlayPreference` + migration ; `FamilyLinkRepository::findActiveBetween` (bidirectionnel).
- `OverlayReader` (habilitation lien actif, 422 uniforme non-énumérant, multi-liens) + projection RO
  **anti-fuite** `OverlayEventResource` (horaires/catégorie/titre only) ; `?overlay=` activé dans
  `EventsProvider` (fusion, expansion fuseau B → affichage fuseau A). Endpoints `GET/PUT /overlay-preferences`
  (scope membre, upsert LWW par advisory lock, lien actif validé). RO strict, garde jamais modifiée.
- **Revue adversariale sécurité** (5 dimensions) : 0 P0/P1/P2, 2 P3 corrigés (dédup lien par identité ;
  upsert préférence en last-write-wins). Registre RGPD (destinataire « famille liée ») + i18n `overlay.*`.
- Portes : backend **217 tests / 1761 assertions, couverture 86,0 %**, PHPStan niv.8, cs-fixer ;
  OpenAPI + client Dart régénérés (sans drift), mobile 120 tests. **Reste : UI web/mobile + E2E.**

### 2026-07-01 — Lot 4 (agenda) : Étape E — E2E Playwright + clôture du lot
- `tests/lot4-agenda.spec.ts` : création événement, série hebdo, filtre catégorie, édition, suppression,
  « À venir » Accueil. **13/13 E2E verts.** Temps réel hors stack E2E (Mercure absent) — couvert backend.
- **Bug attrapé par l'E2E** : chaîne YAML `recurrence_too_long` mal échappée (`\'` en single-quote)
  cassait le catalogue fr → e-mail d'inscription en échec (async→sync en E2E) → tous les parcours KO.
  Corrigé (double-quotes) + garde-fou `TranslationCatalogTest` (parse effectif du catalogue).
- Fragilités E2E corrigées : filtre via `toHaveCount` sur `.agenda-event` (liste absente si vide),
  édition/suppression via `dispatchEvent` (le clic natif se détache au `reload` Stimulus), locator CSS
  pour un bouton dans un `<details>` replié (hors arbre d'accessibilité).
- **Lot 4 clos.** Backend 210 tests / couverture 85,7 % ; mobile 120 tests / 78,5 % ; E2E 13/13.

### 2026-07-01 — Lot 4 (agenda) : Étape D — UI mobile
- Feature Flutter `lib/features/agenda` : repository + providers scopés, `AgendaScreen` (liste groupée
  par jour, pastilles catégorie §8), formulaire de création ponctuel (date/heure → ISO 8601 avec
  offset appareil), bloc « À venir » sur l'accueil. Client `events` ajouté à `ApiClients`.
- Contrainte assumée : création mobile limitée aux ponctuels (`recurrence` généré = `Map<String,String?>`,
  typage int/bool perdu) → séries/édition par portée = web ; offline riche = Lot 8 (§0.3).
- Portes : **120 tests mobiles**, couverture **78,5 %** (≥70 %), `dart analyze`/`format` propres,
  client généré **sans drift**. i18n ARB étendu.

### 2026-07-01 — Lot 4 (agenda) : Étape C — UI web
- Écran `/agenda` (Twig/Turbo/Stimulus) : occurrences groupées par jour, filtres catégorie (pills §8),
  création (ponctuel/série/all-day/récurrence), édition par portée (occurrence/série) + suppression,
  `turbo-stream-source` + cookie Mercure. Onglet sidebar. Bloc « À venir » sur l'Accueil.
- Piège évité : `datetime-local` → ISO 8601 **avec offset** via `Date.toISOString()` (le backend
  exige l'offset, fix de revue #6). WebPagesTest : `mockTime` abandonné (périmait le cookie JWT du
  cookie-jar BrowserKit) → dates relatives à « maintenant » réel.
- Portes : backend **209 tests / 1628 assertions, couverture 85,7 %**, PHPStan niv.8, cs-fixer, lint Twig/conteneur.

### 2026-07-01 — Lot 4 (agenda) : Étape A — backend complet
- **Cartographie préalable** des conventions Lot 3 (workflow 8 lecteurs parallèles) pour coller à l'idiome.
- **Modèle** : `Event` + `event_child` (N-N) + enums ; migration `Version20260701192550` (index uniques
  partiels via `#[ORM\UniqueConstraint(options:['where'])]` pour rester en sync — comme `uniq_invitation_pending`).
- **Moteur d'expansion pur** (testé DST/clamp/until/count/non-cumulatif) séparé de l'orchestration BD
  (`AgendaReader`) ; overrides/EXDATE rattachés au `series_id` (pas de matérialisation des occurrences virtuelles).
- **Scission de série** this-and-future idempotente (`SeriesSplitter`) ; override/EXDATE (`OccurrenceEditor`).
- **Idempotence** de création portée par une colonne `idempotency_key` + index unique partiel (durable ;
  le cache `array` de test ne survit pas au `reset()` du conteneur → décision de la mettre en base).
- **Endpoints** CRUD par portée, Voter `agenda` réutilisé, RFC 7807, Mercure post-commit + claims.
- **Revue adversariale** (7 dimensions → vérification) : 6 constats corrigés (1 P1 idempotence concurrente
  → `pg_advisory_xact_lock` ; 5 P2 : orphelins §4.11, scission freq=none, verrou optimiste TOCTOU→409,
  titre espaces, dates ISO8601 strictes). Régressions ajoutées.
- **Portes** : backend **207 tests / 1609 assertions, couverture 85,3 %**, PHPStan niv.8, cs-fixer ;
  OpenAPI exporté, client Dart régénéré (sans drift), mobile **116 tests**. **Reste : UI web/mobile + E2E.**

### 2026-07-01 — Lot 3 : moteur de garde + vacances + endpoints (backend)
- **Moteur idempotent** : 4 modèles (`alternating`/`pattern_2_2_3` motif figé/`midweek_weekends`/`custom`),
  5 genres de règles vacances (dont `eve_alternating` ancré sur l'**année civile**), pipeline
  base→vacances→exceptions avec **upsert `(family,date)`**, advisory lock, **passé figé**, priorité
  exception>holiday>base. Bug de fuseau **évité** (`CustodyDateMath::daysBetween` compare des dates
  calendaires Y-m-d, pas des instants — sinon off-by-one anchor UTC vs aujourd'hui Paris ; trouvé et
  corrigé via le test d'intégration).
- **Adaptateur vacances isolé** : Opendatasoft v2.1 (schéma vérifié §3.10, conversion UTC→Paris,
  filtrage population « élèves », cache, pagination) derrière une interface + double de test ;
  `SchoolHolidayImporter` upsert idempotent.
- **Async/Scheduler** : `RegenerateCustody`/`ImportSchoolHolidays`/`ExtendCustodyHorizon`/
  `RefreshSchoolHolidays` (routés async, idempotents) ; horizon glissant quotidien + vacances mensuel.
- **Endpoints** : pattern (GET/PUT), days, exceptions (GET/POST/DELETE), stats, holidays (GET/POST/import
  202), rule (PUT). Scope + Voter custody ; famille unie → 404 lecture / 409 mutation ; topic Mercure
  custody dans les claims (filtré `custody ≥ view`).
- **Tests** : moteur (4 modèles, 5 règles, priorité, idempotence, passé figé, suppression d'exception →
  recalcul), provider via `MockHttpClient`, API fonctionnelle (pattern/days/stats/exceptions/holidays/
  import/règle, validations 422, intact 404/409). **Backend 154 tests / 1096 assertions, couverture
  85,1 %.** OpenAPI exporté + client Dart régénéré (sans drift). **Reste : UI web/mobile + E2E + revue.**

### 2026-07-01 — Revue de code Lots 1 & 2 : 11 défauts confirmés corrigés
Revue multi-agents (8 dimensions → vérification adversariale). 11 confirmés / 12, tous corrigés :
- **P0** — FK `notes.author_membership_id` était `ON DELETE CASCADE` : retirer un membre **détruisait
  ses notes**. Passé en **nullable + `ON DELETE SET NULL`** (note préservée, auteur « Membre supprimé »),
  migration `Version20260630220008` + test de survie. (Invariant rétention §4.8.)
- **P1** — acceptation d'invitation : audit `invitation.accepted` manquant → ajouté **dans la
  transaction** (atomique avec `membership.created`) + test.
- **P2** — `Membership::effectiveLevel()` ignorait le statut → garde `isActive()` (défense en
  profondeur) + test ; verrou optimiste `Child.info` jamais armé → **implémenté** (`version` exposée,
  `expectedVersion` → 409) + test ; audit écrit hors transaction (retrait/maj membre, acceptation) →
  `AuditLogger::persist()` **dans** la transaction ; `AgeCalculator`/`NoteDateValidator` décalaient la
  date de naissance (fuseau ≠ UTC) → comparaison en date calendaire brute + test fuseau ouest.
- **P3** — e-mail en clair dans `audit_logs.metadata` (login verrouillé) → blind index ; `AuditLog`
  sans garde `PreRemove` → ajouté + test ; `SendInvitationEmail` non routé async → routé ; réglages
  famille : champs non effaçables (academy/zone/jour) → envoi `null` à l'effacement.
- Portes : backend **134 tests / 946 assertions, couverture 85,5 %**, PHPStan/cs-fixer ; mobile **110**,
  analyze propre ; **E2E 6/6** ; OpenAPI + client Dart régénérés (sans drift).

### 2026-06-30 — Lot 2 : enfants & notes (clos)
- **Modèle** : `Child` (info jsonb paires libres, verrou optimiste, first_name au registre non chiffré),
  `Note` (`text` chiffré AES-256-GCM) ; migration `Version20260630205954` (FK, index, cascade).
- **Autorisation** : `ChildNotesPolicy` 4 actions (séparation contenu/structure ; child jamais CHILD_MANAGE,
  §3.3) + `canMutateNote` (auteur ou parent/admin, §3.7). Services `AgeCalculator`/`InfoNormalizer`/
  `NoteDateValidator`/`AccessRightsBuilder`/`ChildAccess` (résolution scopée → 404 non révélateur).
- **API** : CRUD enfants, `/children/{id}/access`, CRUD notes (+ PATCH/DELETE auteur/parent), audit
  (child.*/note.* dont note.accessed). Push Mercure `/families/{id}/notes` après commit (best-effort,
  §3.6/§4.6) ; topic filtré par `children_notes ≥ view` dans les claims.
- **Web** : `/children` master-détail (fiche, infos, bandeau anti-santé, notes temps réel via
  `turbo-stream-source` + cookie Mercure, bloc Accès & droits) ; Stimulus `children`.
- **Mobile** : écran Enfants + fiche (notes, bandeau) via `ChildrenRepository`.
- **Tests** : backend **129 / 902** (couverture 85,8 %), mobile **110** (79,8 %), **E2E 6/6**
  (parcours Lot 2 ajouté). Registre RGPD à jour ; OpenAPI + client Dart régénérés (sans drift).
  `RealtimeTokenTest` mis à jour (topic notes désormais présent).

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
