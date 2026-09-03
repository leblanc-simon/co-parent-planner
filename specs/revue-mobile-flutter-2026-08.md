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
> **Revue close le 2026-09-04 : 68 constats corrigés sur 68.** Les onze étapes sont livrées, `C11`
> (fin de l'étape 5) compris. Aucun constat confirmé ne reste ouvert.
> *(Le compteur d'avant l'étape 9 annonçait 51/68 : il oubliait `C66`, livré hors étape.)*
> Détail par étape : voir §Journal en fin de document.

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

### Étape 1 — ✅ LIVRÉE le 2026-08-27 — `ApiRoutes` : classification par `contains`  ⬅ le P0

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

### Étape 2 — ✅ LIVRÉE le 2026-08-27 — Build Android : l'application release était inerte

| # | Constat | Gravité |
|---|---|---|
| C4 · C63 | Permission `INTERNET` absente de `src/main/AndroidManifest.xml` (présente seulement dans `debug` et `profile`, valeur par défaut du template Flutter) → **aucune requête réseau** depuis un build release | **P1** |
| C6 | `android:allowBackup` non désactivé : la base SQLite locale (en clair) et le magasin de jetons partent dans la sauvegarde Google Drive de l'utilisateur | P2 |
| C73 | `buildTypes.release` signe avec `signingConfigs.debug` — le TODO du template Flutter n'a jamais été traité, alors que le Lot 9 a livré toute la chaîne de signature iOS | P3 |

### Étape 3 — ✅ LIVRÉE le 2026-08-27 — Cloisonnement des données locales entre comptes

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

### Étape 4 — ✅ LIVRÉE le 2026-08-27 — Démarrage et session

| # | Constat | Gravité |
|---|---|---|
| C1 | `restoreSession()` capture `ApiException` — qui englobe les erreurs **réseau** — et purge les jetons : démarrer hors-ligne **déconnecte** l'utilisateur et lui ferme l'accès au cache offline, ce qui annule le Lot 8 au cold start | **P1** |
| C21 | Après déconnexion ou session expirée, `familyContextProvider` a été vidé mais rien ne le réhydrate : l'utilisateur qui possède déjà une famille est **piégé dans l'onboarding** | **P1** |
| C24 | `main()` attend `restoreSession()` (appel réseau) avant `runApp()` : écran vide prolongé sur réseau lent | P3 |

**Correction** : distinguer « jeton refusé » (401/403 → purge) de « serveur injoignable » (→ conserver
la session et servir le cache). Réhydrater la famille active après la connexion.

### Étape 5 — ✅ LIVRÉE le 2026-08-27 (`C14` avec l'étape 6, `C11` avec l'étape 11) — Machine d'état de l'outbox

| # | Constat | Gravité |
|---|---|---|
| C7 | Une entrée passée `inFlight` puis interrompue (app tuée) n'est **jamais** reprise : `pendingEntries()` ne sélectionne que `pending` → mutation perdue en silence | **P1** |
| C10 · C64 | Le statut `rejected` n'est branché sur **aucune UI** : un refus serveur (403/409/422) détruit la saisie hors-ligne sans notification ni relance possible, alors que `retry()`/`discard()` existent et sont testés | **P1** |
| C13 | À l'enfilement, l'`Idempotency-Key` est **régénérée** : un timeout après traitement serveur produit un second enregistrement — l'événement est créé deux fois | P2 |
| C12 | `flush()` n'a ni garde de connectivité ni verrou d'exécution : deux flushs concurrents envoient la même entrée, et le 409 du serveur est interprété comme un **refus définitif** | P2 |
| C14 | Le drapeau `available` de `CachedResult` est jeté par tous les dépôts : hors-ligne sans cache, l'écran affiche « Aucun événement ce mois-ci » au lieu de « données non disponibles hors-ligne » | P2 |
| C11 | Aucun effet optimiste local : l'entité créée hors-ligne n'apparaît sur aucun écran, ce qui pousse à la ressaisir → doublons à la resynchro *(écart de périmètre Lot 8, pas un simple défaut)* | P2 |
| C16 · C65 | `purgeSynced()` n'est appelée que par son propre test : la base locale grossit indéfiniment | P3 |

### Étape 6 — ✅ LIVRÉE le 2026-08-27 — Agenda : fenêtre de lecture et fuseau horaire

| # | Constat | Gravité |
|---|---|---|
| C37 | La lecture est bornée au **mois courant** : un événement créé pour le mois suivant n'apparaît jamais dans l'agenda mobile | **P1** |
| C38 · C45 | Le bloc « À venir » de l'accueil est calculé sur ce même mois courant : le 28 du mois il annonce « aucun événement à venir » alors que le serveur expose la logique des 4 prochains | **P1** |
| C20 · C36 | La date affichée est calculée en **UTC** alors que l'API renvoie un offset : un événement de début de nuit s'affiche la veille, avec le mauvais jour de semaine | **P1** |
| C39 | Les événements de famille liée sont **empilés après** tous les événements propres du jour : l'ordre chronologique intra-journée est faux | P2 |
| C41 | Ni édition ni suppression d'événement sur mobile ; `deleteEvent()` est du code mort *(écart de périmètre Lot 4)* | P2 |
| C43 | La tuile d'un événement lié n'affiche pas sa catégorie, alors que le filtre par catégorie s'y applique | P3 |
| C44 | Le pull-to-refresh est inopérant sur Android quand la liste ne défile pas | P3 |

### Étape 7 — ✅ LIVRÉE le 2026-08-28 — Garde : écran, droits, conformité §8.1

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

### Étape 8 — ✅ LIVRÉE le 2026-08-28 — i18n et formatage des dates

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

### Étape 9 — ✅ LIVRÉE le 2026-09-04 — Écrans métier : garde-fous d'action et respect des droits

Cause racine : les écrans métier proposent leurs actions **sans regarder les droits du membre** ni
demander confirmation d'un geste irréversible, et l'après-`await` d'une mutation n'est gardé nulle
part. Trois familles de défauts qui aboutissent toutes au même endroit : l'utilisateur agit, et le
résultat lui échappe (403 imprévu, clôture définitive au premier effleurement, cache périmé).


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

### Étape 10 — ✅ LIVRÉE le 2026-09-04 — Typographie

Cause racine : les deux polices du design system sont **variables**, et Flutter ne pilote pas leurs
axes depuis `TextStyle.fontWeight`. Déclarées en un seul fichier sans descripteur `weight:`, elles ne
rendaient que leur **instance par défaut**.


| # | Constat | Gravité |
|---|---|---|
| C54 | Polices **variables** déclarées dans `pubspec.yaml` sans axe de graisse : toute la hiérarchie typographique du §8 est écrasée (Bricolage Grotesque figé à `wght 800` / `opsz 96`) | P2 |

### Étape 11 — ✅ LIVRÉE le 2026-09-04 — Portes qualité : ce que la CI ne garde pas (+ `C11`)

Cause racine : trois portes qui **passent sans rien garder** — un `dart analyze` qui ignore ses
propres règles, une détection de drift aveugle aux fichiers non suivis, un dénominateur de couverture
qui ignore le code qu'aucun test ne charge — et deux pans de code écrits sans filet.


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

### 2026-09-04 — Étape 11 livrée (portes qualité) + `C11` : la revue mobile est close

**`C11` — l'effet optimiste, sans état parallèle à réconcilier.** La création hors-ligne se limitait à
enfiler la mutation : rien n'était écrit côté lecture, l'agenda restait strictement identique et
l'utilisateur pouvait croire sa saisie perdue. Plutôt que d'écrire une entité fantôme dans le cache
Drift — qu'il aurait fallu retirer à la confirmation, remplacer par la version serveur, et rouler en
arrière au refus —, l'entité est **reconstituée à la lecture depuis l'entrée outbox qui la porte
déjà** (`pendingEventsFrom`). Elle vit donc exactement aussi longtemps que sa mutation : elle
disparaît quand celle-ci est confirmée puis purgée, ou quand l'utilisateur l'abandonne. Aucune
synchronisation à écrire, aucun état à réconcilier. L'identifiant synthétique `pending:<entrée>` porte
l'information jusqu'à l'écran, qui affiche le badge « En attente » et **retire la suppression** — le
serveur ne connaît pas encore cet événement. L'invalidation manquante sur le chemin outbox est
ajoutée. Hors-ligne sans cache, la présence d'une saisie locale l'emporte sur l'état « données non
disponibles » (C14) : il y a bien quelque chose à montrer.

| Constat | Correctif |
|---|---|
| C71 | **9 tests** sur les écritures du Lot 6 : envoi d'un message (posté, champ vidé, fil rechargé), envoi refusé (message affiché, **saisie conservée**), création d'une discussion, décision (deux options exigées avant que le bouton s'active, libellés transmis dans l'ordre), et le contrat HTTP de `create`/`postMessage`/`decide`/`patch` sur un vrai `Dio`. Couverture d'`exchanges_repository.dart` 46,9 % → **83 %**, d'`exchange_detail_screen.dart` 46,2 % → **89 %**. |
| C72 | **13 tests** sur `CustodyRepository`, monté avec un `ScriptedAdapter` : `404 → null` (l'état vide « aucun rythme », premier écran de toute famille séparée), `403`/`500` → `ApiException` (un refus de droit n'est pas « pas de rythme »), fenêtre `[from, to]` identique pour les jours et les stats, `422` sur un jour passé, et le `settings` du modèle `midweek_weekends`. Couverture 33,8 % → **80 %**. Vérifié au passage côté serveur : le client sérialise toujours la clé `settings`, mais à `null`, et `PatternWriter::validateConfig` teste `[] !== ($data->settings ?? [])` — un `null` ne déclenche donc pas `config_conflict`. |
| C69 | **6 tests** de `DeepLinkHandler` sur un `GoRouter` réel : les deux hôtes routés avec leur jeton, un schéma étranger et un hôte inconnu qui ne naviguent nulle part, un lien sans jeton. Le jeton passe désormais par `Uri` au lieu d'être concaténé — vérifié en réintroduisant la concaténation : un jeton `a&b=c d` arrivait tronqué à `a`. |
| C67 | `git diff --exit-code` ne compare que les fichiers **suivis** : un fichier généré nouvellement émis passait la porte. Remplacé par `git status --porcelain`, qui voit aussi les non-suivis et les suppressions. Angle mort reproduit (`touch lib/api/generated/x.dart` → `git diff` sort **0**) puis vérifié fermé. |
| C68 | Le dénominateur ignorait tout fichier qu'aucun test ne charge — **10 fichiers**, dont `deep_link_handler.dart`. `test/coverage_barrel_test.dart` les charge tous **et se vérifie lui-même** : ajouter un fichier à `lib/` sans l'y importer fait échouer le test, avec la ligne exacte à ajouter ; `check_coverage.py` applique la même règle depuis l'autre bord, pour que supprimer le barrel fasse échouer la porte au lieu de faire remonter le taux en silence. Les deux garde-fous ont été exercés en retirant un import. |

**Le taux monte, et c'est justement le point à ne pas mal lire** : 79,7 % → **82,5 %**. Charger un
fichier exécute ses déclarations de haut niveau, qui comptent alors pour couvertes — `main.dart` reste
à 0/9 et `app.dart` à 1/15, désormais **comptés**. Ce que le barrel corrige n'est pas le chiffre mais
le dénominateur : un écran de 300 lignes livré sans test le fera maintenant baisser.

**Code mort supprimé** : `notes_tab.dart`, placeholder du Lot 0 remplacé par l'écran Enfants du
Lot 2, sans aucune référence dans `lib/` ni `test/` — l'un des dix fichiers que le barrel aurait
sinon comptés à 0 % pour toujours. **Correction d'i18n au passage** : `syncRejectedReason` rendait
« Lélément nexiste plus », ses apostrophes ayant été retirées par crainte de l'échappement ICU —
vérifié que `gen-l10n` les accepte telles quelles dans un `select`.

Portes mobile : `dart analyze --fatal-infos --fatal-warnings` 0, format 0, **390 tests verts**
(349 avant), couverture **82,5 %** (79,7 % avant, dénominateur désormais complet). Aucun changement de
contrat, de client Dart ni de backend ; la CI gagne une porte de drift qui voit les fichiers
non suivis.

### 2026-09-04 — Étape 10 livrée (typographie)

**Le constat est démontré, pas seulement argumenté.** Une sonde d'un test — charger
`BricolageGrotesque.ttf` par un `FontLoader` et mesurer la largeur d'un même texte — donne
`fontWeight: w400` et `w800` **strictement identiques** (238,14 px), quand `fontVariations wght=200`
rend 217,08 px et `wght=800` rend 238,14 px. Autrement dit : `fontWeight` ne touche pas l'axe `wght`,
et la valeur par défaut rendue *est* le 800. Toute la hiérarchie de titres du §8 sortait en ExtraBold
display, `titleLarge` w600 comme `displaySmall` w700.

**Correction : coupes statiques plutôt que `fontVariations`.** L'option `fontVariations` aurait
laissé faux **tous** les `fontWeight` écrits dans les écrans (37 `w600`, 17 `w700`, 1 `w800`) —
un `TextStyle(fontWeight: w600)` d'un écran hérite bien les `fontVariations` de son style parent,
donc l'axe du parent, jamais la graisse demandée. Six instances sont donc figées depuis les sources
variables (`tool/build_fonts.py`, fonttools) et déclarées une par une avec leur `weight:` :
Bricolage 600/700/800, Hanken 400/600/700. `fontWeight` retrouve son fonctionnement normal partout,
sans rien à écrire dans les écrans ni risque d'oubli sur un nouveau style.

L'axe `opsz` de Bricolage (12 → 96, **défaut 96**) est figé sur la taille où chaque graisse est
employée : 21 pour la SemiBold (`titleLarge`), 24 pour la Bold (titres de 18 à 30 px), 18 pour
l'ExtraBold (logo d'authentification). Une coupe statique ne peut pas suivre la taille du texte comme
le fait `font-optical-sizing: auto` côté web ; l'écart résiduel se compte en quelques points optiques
sur les plus grands titres, contre 96 avant. Les sources variables restent au dépôt sous
`assets/fonts/src/` (non embarquées) et la table des coupes est documentée dans
`assets/fonts/README.md`. Poids embarqué : **484 Ko** contre 528 Ko auparavant.

**Garde-fou CI, vérifié en échec** (`test/fonts_weight_test.dart`) : les coupes sont chargées **depuis
les chemins déclarés au `pubspec.yaml`** — pas une liste recopiée — puis la largeur d'un même texte
est mesurée dans chaque graisse ; deux graisses de largeur identique signent une famille réduite à une
seule coupe. Rétabli l'ancien `pubspec.yaml`, le test échoue sur les deux critères : « coupe déclarée
sans descripteur `weight:` » et « Expected: an object with length of <3> / Actual: Set:[238.14] » —
le symptôme du constat, chiffré.

Portes mobile : `dart analyze --fatal-infos --fatal-warnings` 0, format 0, **349 tests verts**
(346 avant), couverture **79,7 %**. `flutter build bundle` vérifié : le `FontManifest.json` produit
porte bien les six coupes et leurs graisses. Une dépendance de **développement** ajoutée (`yaml`,
déjà transitive, pour que le garde-fou lise le `pubspec.yaml` réel) et `fonttools` documenté comme
outil optionnel — `infra/INSTALL.md` à jour. Aucun changement de contrat, de client Dart ni de
backend.

### 2026-09-04 — Étape 9 livrée (garde-fous d'action, droits, `mounted`)

**Trois idiomes partagés remplacent neuf corrections ponctuelles.** Les constats de cette étape
décrivaient neuf symptômes ; ils tenaient à trois manques structurels, corrigés une fois pour toutes :

- `DetachedProviders` (`context.providers`) — un conteneur Riverpod **détaché du widget**, capturé
  avant l'`await`. `ref.invalidate` d'un `ConsumerState` démonté lève un `StateError` réel (pas un
  `assert` : actif en release) ; l'invalidation, elle, doit précisément survivre au démontage, car la
  mutation a réussi et le cache doit suivre. **13 sites** migrés.
- `confirmAction(...)` — un seul idiome de confirmation nommée, là où l'app en comptait deux copies
  pour deux actions et n'en avait aucune pour les deux plus irréversibles.
- `AppFilterChip` — une pastille de filtre unique, partagée par l'agenda et les échanges, qui en
  portaient chacun une copie quasi identique.

| Constat | Correctif |
|---|---|
| C47 | La révocation tenait dans un `onPressed: () async { await … }` **sans `try/catch`** : le dépôt relançant systématiquement une `ApiException`, l'échec partait en erreur de `Future` non attendue — ni invalidation, ni message, l'écran strictement identique. Désormais : confirmation nommée, verrou anti-double-tap, message d'échec, et la section des invitations **dit** son erreur de chargement au lieu de disparaître (`maybeWhen(orElse: SizedBox.shrink())`). |
| C46 | Clôture d'échange sous confirmation nommée — le serveur interdit la réouverture (§4.5) et notifie tous les membres, et le bouton siège dans la barre de titre, à portée du retour. Verrous `_closing`/`_deciding` sur les deux actions. **La confirmation s'arrête là** : `decide` est un *vote* upserté (`DecideProcessor`), pas un geste définitif — seul le verrou lui manquait. |
| C48 | Le composeur de note n'est rendu qu'avec `children_notes = edit`, comme le web (`{% if canEditNotes %}`). Conforme au scénario nominal du Lot 2 : « les actions d'édition sont absentes ou désactivées ». |
| C50 | Rôle enfant : « Modifier » retiré du menu, mention explicite du plafond, et **rabaissement des droits déjà saisis** au passage sur ce rôle — sans quoi un « Modifier » choisi avant serait parti tel quel au serveur, qui le rabaisse silencieusement (`RightsNormalizer::capForRole`). |
| C52 | L'identifiant d'export est **persisté** (secure storage, effacé à la déconnexion et une fois l'archive livrée), relu à l'ouverture de l'écran, et le statut est **scruté** tant qu'il vaut `pending`. Une opération que le serveur ne connaît plus (404/410) est oubliée en silence — l'utilisateur n'a rien demandé en ouvrant l'écran ; hors-ligne, l'identifiant est conservé. |
| C23 | `context.providers` sur les 13 sites d'invalidation post-`await`, `if (mounted)` avant tout geste d'interface. Deux sites que la fiche du constat ne citait pas ont été trouvés au passage : la bascule d'overlay de l'agenda et la création d'échange. |
| C59 | La carte héro dérive sa couleur et sa phrase de la **garde du jour** : liseré `accent` (parent A) / `positive` (parent B), « Les enfants sont chez X cette semaine. » et la phrase de transmission. Nouveau `currentCustodyProvider`, volontairement **indépendant** de `familyCustodyDaysProvider` : naviguer dans le planning ne doit pas changer ce que dit l'accueil. Garde inconnue (chargement, hors-ligne, hors horizon) → liseré **neutre** plutôt qu'une couleur de parent au hasard. |
| C60 | Nom d'auteur `Flexible` + ellipsis, date incompressible. Test au gabarit d'un iPhone SE (320 pt) avec l'adresse e-mail sur laquelle le serveur retombe faute de nom complet. |
| C61 | Cible tactile portée à 44 pt (HIG iOS) **sans changer l'aspect** : `ConstrainedBox` + `Align` autour de la pilule, qui garde ses 32 pt dessinés. |

**Registre § accessibilité** : le liseré coloré de la carte héro n'est pas un marqueur chromatique
seul au sens du §8.1 — la phrase juste à côté **nomme** le parent.

**24 nouveaux tests**, dont deux exercés à l'envers en réintroduisant le défaut : le débordement de
la ligne « auteur · date » (C60, `RenderFlex overflowed by 618 pixels`) et le cache périmé après
sortie d'écran (C23 — sans l'invalidation détachée, la réouverture ressert le fil d'avant l'envoi).
Portes mobile : `dart analyze --fatal-infos --fatal-warnings` 0, format 0, **346 tests verts**
(322 avant), couverture **79,6 %** (77,9 % avant). Aucun changement de contrat, de client Dart ni de
backend.

**Compteur d'avancement corrigé au passage** : il annonçait 51/68 en oubliant `C66`, livré hors
étape. L'avancement réel passe de 52 à **61 constats sur 68**.

### 2026-08-28 — Étape 8 livrée (i18n et formatage des dates)

**Cause racine confirmée par la correction** : `intl`/`DateFormat` n'était utilisé nulle part hors du
code généré, et **quatre** tables de noms de jours et de mois français littérales vivaient dans les
écrans (accueil, agenda, garde, échanges) — dont deux en double exemplaire. Elles sont supprimées au
profit de `DateLabels` (introduit à l'étape 7 pour la garde, complété ici), qui n'expose que des
formats `intl` paramétrés par la locale de l'écran. Les symboles de date sont chargés par
`GlobalMaterialLocalizations`, déjà présent dans `AppL10n.localizationsDelegates` : rien à appeler au
démarrage, contrairement à ce que la fiche du constat supposait — vérifié en exécutant le formatage
sous le harnais de test.

| Constat | Correctif |
|---|---|
| C57 | **49 valeurs** du catalogue réaccentuées (« Reessayer », « Verifiez », « Reglages », « A venir », « Role », « Edition »…). La moitié haute du fichier — Lots 0/1 — était saisie sans diacritiques quand la moitié basse était correcte, incohérence visible sur un même écran. |
| C19 | Les **trois** littéraux français du code disparaissent : `ApiException` n'expose plus de `message` composé en français dans une couche sans `BuildContext`, mais un `serverMessage` (le `detail` du serveur, déjà localisé par lui) et un discriminant neutre `isConnectionFailure`. Un helper unique `errorText(context, error)` résout le repli dans la locale de l'écran, et l'extension `localized(l)` sert les `catch` situés **après un `await`**, où l'écran a déjà capturé ses `AppL10n`. **26 sites d'appel migrés.** |
| C40 · C56 | `AgendaScreen._frenchDay`, `_monthNames`, `HomeTab._frenchLongDate`/`_frenchShortDow` et `exchanges_screen.frenchDate` supprimés. L'abréviation du jour venait d'un `substring(0, 3)` sur le nom français — découpe qui n'a de sens qu'en français ; `DateFormat.E` rend « lun. ». |
| C51 | Rôle d'invitation et type de famille **traduits** : l'écran Membres affichait « step_parent » sous l'e-mail alors qu'il possédait déjà son traducteur, et les deux feuilles de bascule affichaient « separated ». Une valeur inconnue rend désormais une chaîne **vide** plutôt que la clé technique. Les deux feuilles de bascule, dupliquées et déjà divergentes, sont mutualisées (`showFamilySwitcher`). |
| C53 · C58 | Date des notes enfant formatée dans la locale (« Sophie · 23 juin 2026 »). Le backend expose bien un `noteDateLabel`, absent du contrat — mais c'est un libellé **français figé** : le formater côté client est la seule forme traduisible, et le drift OpenAPI reste sans autre conséquence. |
| C62 | La ligne « Langue » des Paramètres affichait « Francais (fr-FR) » en dur, sans cédille : seule chaîne visible de l'app qu'aucun catalogue ne pouvait traduire. Chaque catalogue porte désormais son propre nom de langue. |

**Deux garde-fous CI, dont il a été vérifié qu'ils échouent réellement** (`test/l10n_guard_test.dart`,
exercé en réintroduisant chaque défaut avant de le retirer) : aucune valeur ARB ne contient un mot
français privé de son accent, et **aucune chaîne accentuée n'existe en dur hors du catalogue**. Le
second critère est précis sans faux positif : une chaîne destinée à l'utilisateur porte presque
toujours un accent en français, une chaîne technique n'en porte jamais.

**Correction de fond prise au passage** : les écrans affichaient `e.toString()` pour toute erreur
non-HTTP, c'est-à-dire le texte technique d'une exception Dart montré tel quel à l'utilisateur
(« Bad state: No element »). `errorText()` rend le message générique du catalogue.

**14 nouveaux tests** (`DateLabels` en français **et en anglais** — l'invariant « ajouter une langue
ne doit demander aucun changement de code » est ainsi réellement exercé —, `errorText`, la
traduction du rôle d'invitation, les deux garde-fous). Portes mobile : `dart analyze --fatal-infos`
0, format 0, **322 tests verts** (309 avant), couverture **77,9 %** (77,3 % avant). Aucun changement
de contrat, de client Dart ni de backend.

> **Piège rencontré, à retenir** : `dart format lib` reformate aussi `lib/api/generated`, ce que le
> `CLAUDE.md` interdit — c'est exactement ce que compare la porte de drift de la CI. Toujours
> restreindre le formatage (`dart format $(find lib test -name '*.dart' -not -path 'lib/api/generated/*')`).

### 2026-08-28 — Étape 7 livrée (garde) + parité des pastilles avec le web

**`C26` était plus grave qu'écrit, et le défaut vient du contrat.** La revue décrivait un sélecteur
inutilisable et une sauvegarde refusée. En réalité, une famille en `midweek_weekends` ne pouvait pas
même **charger** son rythme : API Platform n'inférait rien du `array<string, mixed>` du DTO et
publiait `settings` en `additionalProperties: {type: string}` ; le modèle Dart généré depuis ce
contrat portait donc `Map<String, String?>`, et `CustodyPattern.fromJson` levait
`type 'List<int>' is not a subtype of type 'String?' in type cast` sur `weekend_days` — vérifié en
exécutant la désérialisation. L'écran Garde entier tombait en erreur avant d'atteindre le sélecteur.
Même cause structurelle qu'à l'étape 6 (`from`/`to` non déclarés) : **le contrat doit décrire ce qui
circule réellement**. `settings` est désormais déclaré **objet libre** (`additionalProperties: true`
+ exemple) plutôt qu'avec ses trois clés en `properties` : la forme détaillée corrigeait bien le
typage, mais faisait générer trois classes anonymes `Settings`/`Settings2`/`Settings3`, renumérotées
au moindre objet inline ajouté ailleurs. Le générateur produit maintenant `dynamic`, la forme réelle
restant validée par `PatternWriter::validateSettings()` et relue côté mobile par un préremplissage
tolérant. Contrat ré-exporté, client Dart régénéré (génération vérifiée idempotente).

| Constat | Correctif |
|---|---|
| C26 | Contrat corrigé (ci-dessus) ; `midweek_weekends` ajouté au sélecteur avec son libellé ARB ; **panneau d'édition** des sept jours (cycle parent A → parent B → week-end) et du parent qui ouvre l'alternance, jumeau du web ; `settings` transmis par `updatePattern`, et **uniquement** pour ce modèle (`config_conflict` ailleurs) ; les jours de semaine sont **dérivés** des jours de week-end, le serveur exigeant une couverture exacte. Repli sur `alternating` si le serveur annonce un modèle inconnu — jamais une valeur absente des items du `DropdownButtonFormField`. |
| C29 | `rightProvider` / `canViewProvider` / `canEditProvider` génériques sur `Member.rights` du membre `self` — que **aucun** écran ne lisait. Réglages du rythme, « Configurer le rythme », « Échanger ce jour », suppression d'exception et import des vacances sont désormais **masqués** sans `custody:edit`. Règle asymétrique et assumée : un **bouton** de mutation reste masqué tant que le droit est inconnu (un 403 est pire qu'une action qui apparaît un instant plus tard), un **onglet** reste visible (voir C33). |
| C33 | Onglet Garde masqué pour une famille `intact` et pour un domaine explicitement `none`, Agenda et Enfants compris (le web le fait depuis la revue 6.12). Les quatre branches de l'`indexedStack` restent en place — seul le bouton disparaît — et la clé de test reste celle de la **branche**, pas du rang affiché. Un onglet n'est masqué que sur un `none` **explicite** : le serveur renvoie toujours les six domaines, donc une carte vide signifie « pas encore chargé », et masquer dans ce cas viderait la barre au démarrage comme hors-ligne. L'onglet couramment sélectionné reste toujours rendu : personne ne doit se retrouver sur un écran sans bouton pour en sortir. |
| C27 | `custodyMonthProvider` + fenêtre `from`/`to` sur `custody/days` **et** `custody/stats` — sur la **même** fenêtre, des jours et des statistiques portant sur deux périodes serait pire que l'absence de navigation. Chevrons câblés (cible tactile portée à 44 pt, §8.1), appui long sur le titre pour revenir au mois courant. La grille est bâtie sur le mois **demandé** et non sur les jours reçus : sinon un mois hors période générée effaçait le calendrier **et ses chevrons**, sans retour possible. |
| C28 | `'223'` → `'pattern_2_2_3'`, `midweek_weekends` ajouté, et branche par défaut remplacée par un libellé localisé : plus aucune valeur d'énumération ne peut fuir à l'écran. |
| C55 | `Semantics(button:, label:, excludeSemantics:)` sur chaque case — il n'existait **aucun** `Semantics` dans toute l'app. VoiceOver/TalkBack annoncent « mercredi 1 juillet 2026 — chez Sophie Martin », avec les mentions « Exceptionnel », « Vacances », « Activité » quand elles s'appliquent ; l'`excludeSemantics` joue le rôle de l'`aria-hidden` du gabarit web. `nameForSlot()`, jusque-là code mort, est enfin appelé. |
| C30 | Barre 50/50 retirée (même décision et même motif que côté web) ; sans règle, la carte dit « Aucune règle de partage — le rythme habituel s'applique. » ; le genre de règle est **nommé** au lieu d'afficher `eve_alternating`. |
| C32 | Fiche de jour reconstruite en transposition du fragment web : date en toutes lettres, **nom en clair** du parent avec son marqueur, origine de l'attribution dans la priorité du moteur (exception + raison > vacances + règle > rythme habituel, « pendant {période} » compris), **transmission** « Retour chez {Parent} le {date} à {heure} » — dérivée des `CustodyDay` **matérialisés** qui suivent (fenêtre de 45 jours, comme `DayDetailReader`), donc exceptions et vacances comprises, et **omise** en bout d'horizon plutôt qu'inventée. Les statistiques nomment elles aussi les parents. |
| C34 | L'anneau « famille liée » passe **haut-droite** : il était peint par-dessus l'icône de vacances, bas-droite. Le commentaire de répartition des coins, qui décrivait déjà le conflit, est corrigé. |

**Parité avec les pastilles du planning web (constat web 6.1, livré la veille).** Les activités
étaient indexées sur le seul jour de **début** : un séjour de trois jours n'apparaissait que sur le
premier. Elles le sont désormais par jour **couvert**, avec la même règle que le web — une fin posée
à minuit pile appartient au jour précédent, et un événement commencé avant le mois entre au 1er
plutôt que de faire itérer hors grille. La pastille grise unique devient **une pastille par
catégorie** (§8), trois au plus sur une case de ~45 px, et la fiche de jour liste les activités avec
leur horaire et leur catégorie **nommée** (la barre colorée ne survit ni aux niveaux de gris ni au
contraste forcé). Les activités relèvent du domaine `agenda`, résolu **séparément** de `custody` :
sans ce droit, ni pastille ni section — un état vide dirait déjà s'il y a des activités. Enfin la
fenêtre est celle du **planning**, et non plus celle de l'agenda : naviguer dans le planning aurait
sinon affiché les pastilles d'un autre mois.

**Deux corrections d'i18n prises au passage** (elles appartiennent à l'étape 8 mais sont
indissociables des écrans repris) : `DateLabels`, premier usage de `intl`/`DateFormat` du projet,
remplace le tableau `['janvier', …]` littéral du calendrier et les dates **ISO brutes** de la fiche
de jour, des exceptions et des périodes de vacances (part garde de C31/C58) ; le motif « Échange »
envoyé au serveur passe par l'ARB (part garde de C62). Le reste de l'étape 8 est intact.

**Deux points d'architecture introduits, à connaître pour la suite** : `todayProvider` (jour civil
injectable — « ce jour est passé » décide si « Échanger ce jour » est offert ou **inerte**, et un
test ne doit pas dépendre de la date à laquelle il tourne) et `custodyMonthProvider`, distinct du
mois de l'agenda (les deux écrans se naviguent séparément).

**26 nouveaux tests** (18 sur l'écran Garde et ses réglages, 12 unitaires purs sur l'indexation par
jour couvert et la recherche de transmission, 3 sur la barre d'onglets — dont le cas « droits
inconnus »). Portes mobile : `dart analyze --fatal-infos` 0, format 0, **309 tests verts** (283
avant), couverture **77,3 %** (76,0 % avant). Portes backend : **387 tests / 3196 assertions**,
PHPStan niv.8 0, cs-fixer 0. Contrat + client Dart régénérés, génération **idempotente**.

### 2026-08-27 — Étape 6 livrée (agenda) + C14

**Cause racine commune à C37 et C38/C45** : le contrat ne déclarait pas les paramètres `from`/`to`
que `AgendaRange` lisait déjà. Le client mobile, **généré depuis l'OpenAPI**, ne pouvait donc pas les
passer et restait bloqué sur la fenêtre par défaut du serveur — le mois civil courant. Le correctif
part donc du contrat : `GET /api/families/{familyId}/events` déclare `from`, `to` et `overlay`, en
`type: string` + `pattern` plutôt que `format: date` (le générateur Dart mappe `format: date` sur
`DateTime` et sérialise en ISO 8601 complet, que `AgendaRange` refuse en 422 — le contrat doit décrire
ce qui circule réellement).

**Drift trouvé au passage** : `contracts/openapi.json` avait dérivé du backend depuis `0bc6f6d`
(`noteDateLabel` ajouté sans ré-export) — la porte de drift du job CI **backend** était donc rouge.
Contrat ré-exporté, client Dart régénéré (génération vérifiée idempotente).

| Constat | Correctif |
|---|---|
| C37 | Fenêtre explicite + **navigation de mois** (`agendaMonthProvider`, précédent/suivant/aujourd'hui). Un événement créé pour un autre mois était invisible, ce qui poussait à le recréer. |
| C38·C45 | `upcomingEventsProvider` : fenêtre dédiée aujourd'hui → +60 j et borne `startsAt >= now \|\| endsAt >= now` reprise de `UpcomingEventsFinder`, au lieu de la liste mensuelle avec un `isAfter` strict. |
| C20·C36 | `CivilDate` : la date vient des composants **textuels** de l'horodatage. `DateTime.parse` d'une chaîne à offset renvoie un instant UTC → la veille s'affichait pour tout événement de nuit **et pour tous les événements journée entière** (`T00:00:00+02:00`). `.toLocal()` ne corrige rien, le fuseau appareil n'étant pas celui de la famille. |
| C39 | Fusion des deux sources dans une liste unique triée par la clé du serveur (jour, journée entière, heure, titre). |
| C41 | Tuile actionnable → feuille d'actions avec suppression et confirmation nommée. Une occurrence de série dit explicitement que la portée reste web (US-AG-6). |
| C43 | Pastille de catégorie sur la tuile d'un événement lié. |
| C44 | `AlwaysScrollableScrollPhysics` sur les **six** écrans à `RefreshIndicator`. |
| C14 | `CachedResult` remonte jusqu'aux écrans : « Données non disponibles hors-ligne » au lieu d'un faux mois vide ; la clé de cache inclut la fenêtre. |

**21 nouveaux tests.** Portes mobile : analyze `--fatal-infos` 0, format 0, **283 tests verts**,
couverture **76,0 %**. Portes backend : **341 tests / 2778 assertions**, PHPStan niv.8 0, cs-fixer 0.

> Note d'environnement : les clés JWT (`backend/config/jwt/*.pem`, non committées) doivent être
> générées dans chaque worktree neuf — `php bin/console lexik:jwt:generate-keypair` — sinon 156 tests
> fonctionnels échouent sur « error while trying to encode the JWT token ».

### 2026-08-27 — Étapes 1 à 5 livrées

**Étape 1 — le P0.** `ApiRoutes.isFamilyScoped()` classait les chemins par `contains`, donc
dé-scopait `/api/families/{familyId}/…` — c'est-à-dire **toutes** les ressources métier. La règle est
désormais **scopé par défaut** (on n'énumère plus les routes scopées, qui grandissent à chaque lot,
mais les rares exceptions), avec comparaison par **segment complet** et normalisation du chemin (URL
absolue, requête, fragment, slash final). `/api/me` ne capture plus `/api/memberships` (C18).
Le test précédent interrogeait `/api/custody`, chemin **absent du contrat** : remplacé par une table
fixant l'attendu pour chacun des **49 chemins** de `contracts/openapi.json`, avec contrôle de
recouvrement exact — ajouter un endpoint sans décider s'il est scopé rend la CI rouge.

**Étape 2 — build Android.** Permission `INTERNET` ajoutée au manifeste `main` (C4/C63) ;
`allowBackup="false"` (C6) ; la signature release ne passe plus par la clé de debug (C73) — keystore
hors dépôt via `android/key.properties` ou variables `ANDROID_KEYSTORE_*`, artefact **non signé**
(échec visible) en l'absence de configuration, procédure dans `infra/INSTALL.md §F`.
`android_release_test.dart` verrouille les trois points, plus l'absence de clé committée.

**Étape 3 — cloisonnement par compte.** Schéma Drift **v3** : colonne `userId` sur `OutboxEntries` et
`CacheEntries`, clé de cache `userId::familyId::endpoint`. Toutes les lectures de l'outbox sont
filtrées par compte (C2/C9, C3/C8). `LocalDataPurger` purge à la déconnexion volontaire, après
réinitialisation de mot de passe (C5) et après suppression de compte ; la déconnexion **forcée** ne
purge **pas** — les données appartiennent au même compte, sont cloisonnées par son identifiant et
seront rejouées à la reconnexion, les effacer ferait perdre du travail hors-ligne légitime.
`currentUserIdProvider` invalide le cache Riverpod des providers de compte à la bascule (C22).

**Étape 4 — démarrage et session.** `restoreSession()` distingue « jeton refusé » de « serveur
injoignable » : une erreur réseau ne purge plus les jetons et repart du dernier profil connu, persisté
au stockage sécurisé — démarrer hors-ligne ne déconnecte plus (C1). `resolveActive()` choisit la
famille après connexion (automatique s'il n'y en a qu'une) et l'onboarding propose désormais les
familles existantes (C21). `main()` ne bloque plus l'affichage derrière `GET /api/me` : nouvel état
`AuthRestoring` + écran d'attente, ni fenêtre vide ni clignotement de l'écran de connexion (C24).

**Étape 5 — outbox.** Reprise des entrées `inFlight` orphelines (C7) ; statut `rejected` enfin branché
sur l'UI, avec relance et abandon (C10/C64) ; `flush()` sérialisé (C12) ; `IdempotencyScope` pour que
la tentative en ligne et son rejeu partagent la même clé, le client généré n'acceptant ni en-tête ni
`extra` par appel (C13) ; `purgeSynced()` appelée par `flush()` (C16/C65).
**Restent de l'étape 5** : `C14` (drapeau `available` jeté par les dépôts) et `C11` (effet optimiste
local), qui demandent de faire remonter `CachedResult` jusqu'aux écrans.

**Étape 11 (partiel).** `dart analyze --fatal-infos --fatal-warnings` en CI (C66) : sans ce drapeau,
aucune règle de `analysis_options.yaml` n'était appliquée. Le code était déjà propre à ce niveau.

**Portes** : `dart analyze --fatal-infos` 0 issue, format 0, **262 tests verts** (180 au départ),
couverture **75,3 %** (seuil 70 %), client OpenAPI **sans drift** (attention : ne jamais passer
`dart format` sur `lib/api/generated`, le générateur produit un style différent — c'est ce que la
porte de drift de la CI compare).

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
