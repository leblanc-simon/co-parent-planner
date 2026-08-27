# Revue de code de l'application mobile Flutter — plan de correction (août 2026)

> **Document de suivi.** Établi le 2026-08-27 par une revue croisée multi-agents de la totalité du
> code mobile écrit à la main : **9 axes** menés en parallèle, **81 constats explorés**, **73** après
> dédoublonnage, **68 confirmés** par vérification adversariale (un vérificateur par lot de 4, mandaté
> pour *réfuter*), **5 réfutés**. Sources de vérité : `specs/cdc.md` (§8 = design system, §8.1 =
> amendement accessibilité), `CLAUDE.md` (invariants), `specs/lots/*.md`, `contracts/openapi.json`,
> maquettes `specs/design/` (variante mobile + `ios-frame.jsx`).
>
> Détail intégral (preuve, scénario de panne, correction, verdict du vérificateur) :
> **`specs/revue-mobile-flutter-2026-08-annexes.md`**.
>
> **État d'avancement : voir §Journal en fin de document.**

## Conditions d'observation

Revue **statique exhaustive** : 13 251 lignes de Dart écrit à la main (`mobile/lib/**`, hors
`lib/api/generated/` qui est généré depuis l'OpenAPI et non recevable comme constat), 4 005 lignes de
tests, le catalogue ARB (268 clés), `pubspec.yaml`, la configuration Android/iOS, le job CI `mobile`,
et — pour chaque comportement dépendant du serveur — le code PHP correspondant dans `backend/src/`.

Portes mesurées **avant** toute modification :

| Porte | État initial |
|---|---|
| `dart analyze` | 0 issue |
| `flutter test` | **180 tests verts** |
| Couverture lignes (hors généré) | **75,8 %** (3044/4017), seuil 70 % |

**Cause structurelle de ce que la revue a trouvé** : il n'existe **aucun test de bout en bout mobile
contre un backend réel**. Les 180 tests montent des écrans avec des dépôts mockés ou un
`HttpClientAdapter` scripté ; le client mobile n'a jamais parlé au serveur. Trois défauts bloquants
— dont le seul P0 — sont exactement ceux que le premier lancement contre l'API aurait révélés en
trente secondes. C'est cette dette qui explique le « Lots 0→8 TERMINÉS » de `PROGRESS.md` côté mobile.

---

## Constat structurant : l'app mobile ne fonctionne aujourd'hui ni en ligne ni en release

Trois défauts indépendants se cumulent, chacun suffisant à rendre l'application inutilisable :

1. **`C17` (P0)** — aucune requête vers `/api/families/{familyId}/…` ne porte `X-Family-Id`, alors
   que le serveur l'exige : **toutes** les collections métier répondent 400.
2. **`C4`/`C63` (P1)** — la permission `INTERNET` n'est déclarée que dans les manifestes `debug` et
   `profile` : un APK/AAB **release** ne peut émettre aucune requête.
3. **`C1` (P1)** — au démarrage, toute erreur réseau purge les jetons : lancer l'app hors-ligne
   déconnecte l'utilisateur et lui ferme l'accès à son propre cache.

Vérifiés à la main, hors chaîne d'agents, avant rédaction :

```
mobile/lib/core/network/interceptors/api_routes.dart:14
    if (path.contains('/api/families')) return false;   // dé-scope TOUT /api/families/...
contracts/openapi.json → /api/families/{familyId}/{members,children,events,exchanges,
    custody/*,holidays,links,invitations,overlay-preferences}
backend/src/Security/FamilyScope.php:59-62
    if (null === $raw || '' === $raw) throw new FamilyScopeRequiredException();  // HTTP 400

grep -c INTERNET mobile/android/app/src/{debug,main,profile}/AndroidManifest.xml
    debug: 2 · main: 0 · profile: 2
```

---

## Ordre de correction retenu

L'ordre suit le rapport « constats fermés / effort », puis la gravité. Les étapes 1 et 2 sont des
correctifs d'une dizaine de lignes qui débloquent l'application entière ; elles passent avant tout.

### Étape 1 — `ApiRoutes` : une classification par `contains` casse tout le scope famille  ⬅ le P0

Une fonction de huit lignes décide quelles requêtes portent `X-Family-Id`. Elle raisonne par
sous-chaîne, donc dé-scope l'inverse de ce qu'elle prétend.

| # | Constat | Gravité |
|---|---|---|
| C17 | `path.contains('/api/families')` dé-scope **toutes** les ressources métier, qui vivent précisément sous `/api/families/{familyId}/…` → 400 `/errors/family-scope-required` sur agenda, garde, enfants, échanges, membres, invitations, liens, préférences overlay. Aucun dépôt ne compense : tous documentent « posé par l'intercepteur » | **P0** |
| C18 | `/api/me` est une **sous-chaîne** de `/api/memberships` → `PATCH`/`DELETE /api/memberships/{id}` partent sans `X-Family-Id` : modifier un droit ou retirer un membre échoue | **P1** |

**Correction** : inverser la logique — n'exclure que les chemins réellement non scopés, par égalité ou
par préfixe de **segment complet** (`==`, `startsWith('/api/me/')`, `RegExp(r'^/api/families/[^/]+$')`),
jamais par `contains`.

**Tests à écrire** : le seul test existant (`test/core/network/interceptors_test.dart:31`) interroge
`/api/custody`, **chemin qui n'existe pas au contrat** — d'où l'aveuglement total. Le remplacer par
une **table de cas dérivée de `contracts/openapi.json`** : chaque chemin du contrat, avec son attendu
scopé / non scopé. Toute divergence future entre contrat et intercepteur casse alors la CI.

### Étape 2 — Build Android : l'application release est inerte

| # | Constat | Gravité |
|---|---|---|
| C4 · C63 | Permission `INTERNET` absente de `src/main/AndroidManifest.xml` (présente seulement dans `debug` et `profile`, valeur par défaut du template Flutter) → **aucune requête réseau** depuis un build release | **P1** |
| C6 | `android:allowBackup` non désactivé : la base SQLite locale (en clair) et le magasin de jetons partent dans la sauvegarde Google Drive de l'utilisateur | P2 |
| C73 | `buildTypes.release` signe avec `signingConfigs.debug` — le TODO du template Flutter n'a jamais été traité, alors que le Lot 9 a livré toute la chaîne de signature iOS | P3 |

### Étape 3 — Cloisonnement des données locales entre comptes

Cause racine unique : **rien n'est purgé au changement de compte**. La déconnexion efface les jetons
et la famille active, mais laisse sur l'appareil la file de mutations, le cache de lecture et les
préférences du compte précédent.

| # | Constat | Gravité |
|---|---|---|
| C2 · C9 | L'outbox n'est **pas purgée à la déconnexion** : les mutations en file d'un compte sont rejouées, avec leur `X-Family-Id` figé, sous l'identité du compte suivant connecté sur l'appareil | **P1** |
| C3 · C8 | Le cache de lecture Drift est cloisonné **par famille uniquement**, jamais par utilisateur, et n'est purgé ni à la déconnexion ni à la suppression de compte (`purgeFamily` existe et n'est appelée par personne) | P2 |
| C22 | Les préférences de notification d'un compte restent en cache, sont **affichées** au compte suivant, puis **réécrites** sous son identité | P2 |
| C5 | La réinitialisation de mot de passe ne purge pas les jetons locaux : après un « toutes les sessions sont révoquées », la session se rouvre seule au redémarrage | P2 |
| C25 | `verifyEmail()` et `resetPassword()` passent l'état à « déconnecté » sans purger les jetons — même symptôme, autre chemin | P3 |

**Tests à écrire** : un test de bascule de compte (compte A enfile une mutation + remplit le cache →
déconnexion → compte B) asserte que la base locale ne contient plus **rien** de A.

### Étape 4 — Démarrage et session

| # | Constat | Gravité |
|---|---|---|
| C1 | `restoreSession()` capture `ApiException` — qui englobe les erreurs **réseau** — et purge les jetons : démarrer hors-ligne **déconnecte** l'utilisateur et lui ferme l'accès au cache offline, ce qui annule le Lot 8 au cold start | **P1** |
| C21 | Après déconnexion ou session expirée, `familyContextProvider` a été vidé mais rien ne le réhydrate : l'utilisateur qui possède déjà une famille est **piégé dans l'onboarding** | **P1** |
| C24 | `main()` attend `restoreSession()` (appel réseau) avant `runApp()` : écran vide prolongé sur réseau lent | P3 |

**Correction** : distinguer « jeton refusé » (401/403 → purge) de « serveur injoignable » (→ conserver
la session et servir le cache). Réhydrater la famille active après la connexion.

### Étape 5 — Machine d'état de l'outbox

| # | Constat | Gravité |
|---|---|---|
| C7 | Une entrée passée `inFlight` puis interrompue (app tuée) n'est **jamais** reprise : `pendingEntries()` ne sélectionne que `pending` → mutation perdue en silence | **P1** |
| C10 · C64 | Le statut `rejected` n'est branché sur **aucune UI** : un refus serveur (403/409/422) détruit la saisie hors-ligne sans notification ni relance possible, alors que `retry()`/`discard()` existent et sont testés | **P1** |
| C13 | À l'enfilement, l'`Idempotency-Key` est **régénérée** : un timeout après traitement serveur produit un second enregistrement — l'événement est créé deux fois | P2 |
| C12 | `flush()` n'a ni garde de connectivité ni verrou d'exécution : deux flushs concurrents envoient la même entrée, et le 409 du serveur est interprété comme un **refus définitif** | P2 |
| C14 | Le drapeau `available` de `CachedResult` est jeté par tous les dépôts : hors-ligne sans cache, l'écran affiche « Aucun événement ce mois-ci » au lieu de « données non disponibles hors-ligne » | P2 |
| C11 | Aucun effet optimiste local : l'entité créée hors-ligne n'apparaît sur aucun écran, ce qui pousse à la ressaisir → doublons à la resynchro *(écart de périmètre Lot 8, pas un simple défaut)* | P2 |
| C16 · C65 | `purgeSynced()` n'est appelée que par son propre test : la base locale grossit indéfiniment | P3 |

### Étape 6 — Agenda : fenêtre de lecture et fuseau horaire

| # | Constat | Gravité |
|---|---|---|
| C37 | La lecture est bornée au **mois courant** : un événement créé pour le mois suivant n'apparaît jamais dans l'agenda mobile | **P1** |
| C38 · C45 | Le bloc « À venir » de l'accueil est calculé sur ce même mois courant : le 28 du mois il annonce « aucun événement à venir » alors que le serveur expose la logique des 4 prochains | **P1** |
| C20 · C36 | La date affichée est calculée en **UTC** alors que l'API renvoie un offset : un événement de début de nuit s'affiche la veille, avec le mauvais jour de semaine | **P1** |
| C39 | Les événements de famille liée sont **empilés après** tous les événements propres du jour : l'ordre chronologique intra-journée est faux | P2 |
| C41 | Ni édition ni suppression d'événement sur mobile ; `deleteEvent()` est du code mort *(écart de périmètre Lot 4)* | P2 |
| C43 | La tuile d'un événement lié n'affiche pas sa catégorie, alors que le filtre par catégorie s'y applique | P3 |
| C44 | Le pull-to-refresh est inopérant sur Android quand la liste ne défile pas | P3 |

### Étape 7 — Garde : écran, droits, conformité §8.1

| # | Constat | Gravité |
|---|---|---|
| C26 | Rythme `midweek_weekends` : l'écran Paramètres › Garde est inutilisable et la sauvegarde systématiquement rejetée | **P1** |
| C29 | Aucun contrôle du droit `custody:edit` : toutes les actions de mutation (échanger un jour, supprimer une exception, importer les vacances) sont offertes à un membre en lecture seule, qui reçoit un refus serveur | P2 |
| C33 | L'onglet Garde reste dans la barre pour une famille **unie** (`intact`), que la spec impose de masquer sur mobile comme sur web | P2 |
| C27 | Navigation de mois inerte : le calendrier est figé sur le mois courant | P2 |
| C28 | Le bandeau de rythme teste une valeur `'223'` **inexistante** → la clé technique `pattern_2_2_3` s'affiche à l'utilisateur | P2 |
| C55 | §8.1 non respecté : le **nom du parent en clair** n'est exposé ni visuellement ni aux lecteurs d'écran (aucun `Semantics` dans toute l'app), alors que l'amendement l'impose explicitement — le marqueur devait être `aria-hidden` et le nom lisible | P2 |
| C30 | Carte de période de vacances : barre 50/50 systématique, laissant croire à un partage qui n'existe pas *(jumeau du constat web de la liste « Reste à traiter »)* | P2 |
| C32 | Fiche de jour amputée de l'info de transmission, parents désignés « Parent A/B » alors que la légende affiche leurs vrais noms *(jumeau mobile du 6.1 web)* | P2 |
| C34 | Icône « vacances » et anneau « famille liée » peints au **même coin** de la case : le marqueur de vacances est recouvert | P3 |

### Étape 8 — i18n et formatage des dates

Cause racine : `intl`/`DateFormat` n'est **jamais** utilisé ; le formatage est réimplémenté à la main
en français dans quatre écrans, et une partie des chaînes n'a jamais atteint le catalogue ARB. Cela
viole l'invariant « toutes les chaînes externalisées, ICU » du `CLAUDE.md`.

| # | Constat | Gravité |
|---|---|---|
| C57 | Catalogue ARB fr : une **quarantaine** de libellés sont écrits sans diacritiques (« Verifiez », « donnees », « echange ») et s'affichent tels quels | P2 |
| C19 | Messages d'erreur réseau codés en dur en français dans `ApiException` (`'Une erreur est survenue…'`, `'Connexion impossible…'`) | P2 |
| C40 | Libellés de jours et de mois codés en dur dans le Dart de l'agenda | P2 |
| C51 | Valeurs d'énumération brutes affichées à l'utilisateur (« step_parent », « separated ») | P2 |
| C31 · C56 · C58 · C53 | Dates **ISO brutes** (`2026-06-23`) affichées sur cinq emplacements (garde, notes enfant), mois français en dur, énumérations non traduites | P2/P3 |
| C62 | Deux chaînes françaises en dur hors ARB, dont une visible dans les Paramètres et écrite sans cédille | P3 |

### Étape 9 — Écrans métier : garde-fous d'action et respect des droits

| # | Constat | Gravité |
|---|---|---|
| C47 | Révocation d'une invitation : ni confirmation, ni retour d'échec — l'exception asynchrone est avalée | P2 |
| C46 | Clôture d'un échange et validation d'option : actions irréversibles sans confirmation ni verrou anti-double-tap | P2 |
| C48 | Fiche enfant : la saisie de note est toujours proposée, quel que soit le droit `children_notes` | P2 |
| C50 | Formulaire d'invitation : le niveau « Modifier » est proposé pour le rôle **enfant**, que le serveur rabaisse silencieusement | P2 |
| C52 | Export RGPD : l'identifiant d'opération ne survit pas à la sortie de l'écran → le lien signé devient inatteignable | P2 |
| C23 | `ref.invalidate` et `TextEditingController.clear()` appelés après un `await` **sans garde `mounted`** : exception non capturée, détail d'échange périmé | P2 |
| C59 | Carte héro « Garde de la semaine » de l'accueil : liseré figé en terracotta (parent A) et contenu générique au lieu de l'information de la semaine *(jumeau mobile du 6.5 web)* | P2 |
| C60 | La ligne « auteur · date » d'une note enfant n'a ni `Flexible` ni ellipsis → débordement | P3 |
| C61 | Cibles tactiles des filtres agenda et échanges à ~32 pt, sous le minimum de 44 pt | P3 |

### Étape 10 — Typographie

| # | Constat | Gravité |
|---|---|---|
| C54 | Polices **variables** déclarées dans `pubspec.yaml` sans axe de graisse : toute la hiérarchie typographique du §8 est écrasée (Bricolage Grotesque figé à `wght 800` / `opsz 96`) | P2 |

### Étape 11 — Portes qualité : ce que la CI ne garde pas

| # | Constat | Gravité |
|---|---|---|
| C66 | `dart analyze` sans `--fatal-infos` : **aucune** des règles de lint déclarées dans `analysis_options.yaml` n'est réellement appliquée | P2 |
| C71 | Écriture des échanges (message, création, clôture) : **zéro test** sur le cœur du Lot 6 | P2 |
| C72 | `CustodyRepository.getPattern` : la branche 404 → `null` n'est exercée par aucun test (uniquement simulée par un mock) | P2 |
| C67 | Le garde-fou de drift du client Dart est aveugle aux fichiers générés **non suivis** par git | P3 |
| C68 | Garde-fou de couverture : les fichiers jamais importés par un test sont absents de `lcov`, donc du **dénominateur** — la couverture réelle est inférieure à 75,8 % | P3 |
| C69 | Aucun test de `DeepLinkHandler.handle` : le routage des liens de vérification e-mail / réinitialisation (décision D5) n'est couvert par rien | P3 |

---

## Dette de test révélée

Le trou n'est pas quantitatif — 75,8 % de couverture, 180 tests — mais **structurel** :

1. **Aucun test ne parle au contrat.** Le seul test du scope famille interroge `/api/custody`, chemin
   absent de `contracts/openapi.json` (C17). Rien ne relie les chemins réellement émis par le client
   généré à la classification de l'intercepteur.
2. **Deux tests verts couvrent du code mort** : `retry()` et `purgeSynced()` sont testés unitairement
   alors qu'aucun appelant de production ne les atteint (C64, C65). Un test vert y a tenu lieu de
   fonctionnalité livrée.
3. **Le dénominateur de couverture est faux** : un fichier jamais importé par un test n'entre pas dans
   `lcov` (C68). Les dépôts les moins testés sont donc doublement invisibles.
4. **La CI n'applique aucun lint** (C66) et le garde-fou de drift a un angle mort (C67).
5. **Aucun test de bout en bout mobile.** C'est la cause de tout ce qui précède : le client mobile
   n'a jamais été exécuté contre le backend, en aucun point de la chaîne CI.

---

## Constats écartés par la vérification adversariale

Cinq sur 73, chacun réfuté code en main :

- **C15** — « connectivité jamais initialisée » : faux pour `connectivity_plus` 7.1.1, qui émet l'état
  courant à l'abonnement sur les deux plateformes.
- **C35** — « règle jumelle PHP/Dart divergente sur les espaces Unicode » : la moitié technique est
  fausse, vérifiée en exécutant PHP — le modificateur `u` active `PCRE2_UCP`, donc `\s` couvre
  U+00A0 comme le Dart.
- **C42** — « récurrence absente sans signalement » : faits exacts, mais limitation explicitement
  assumée et documentée dans `PROGRESS.md` (Lot 4, contrainte du modèle généré).
- **C49** — « notes/messages non mis en outbox » : code cité exact, mais limitation assumée du Lot 8.
- **C70** — « tous les tests widget tournent en 1080×2400 » : fait technique exact, aucun défaut
  observable démontré.

---

## Journal

### 2026-08-27 — Revue conduite, plan établi

Revue multi-agents sur 9 axes (sécurité/auth/stockage · offline/outbox/cache · réseau/intercepteurs/
dépôts · état Riverpod/routage/cycle de vie async · garde · agenda+overlay · écrans métier ·
design system/i18n/a11y · tests et conformité projet), puis vérification adversariale par lots de 4
avec consigne de réfuter par défaut. 29 agents, 857 appels d'outils, 3,07 M tokens.

Résultat : **68 constats confirmés** (1 P0, 14 P1, 40 P2, 13 P3), 5 réfutés. Les deux constats
structurants (C17 scope famille, C4/C63 permission `INTERNET`) ont été re-vérifiés à la main hors
chaîne d'agents avant rédaction. Aucune modification de code à ce stade : les portes sont restées
à leur état initial (`dart analyze` 0 issue, 180 tests verts, 75,8 % de couverture).

**Reprise** : étape 1 (`ApiRoutes`), puis étape 2 (manifeste Android) — ensemble une trentaine de
lignes qui débloquent l'application entière et doivent précéder toute autre correction.
