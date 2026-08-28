# Revue de l'application web desktop — plan de correction (juillet 2026)

> **Document de suivi.** Établi le 2026-07-27 après une revue de l'application desktop conduite
> dans Chrome sur la stack de développement, complétée par une revue croisée multi-agents
> (6 dimensions, 42 constats explorés, 39 confirmés par vérification adversariale, 3 réfutés).
> Sources de vérité : `specs/cdc.md` (§8 = design system), `specs/design/*.dc.html` (maquettes,
> font foi pour le rendu), `CLAUDE.md` (invariants).
>
> **État d'avancement : voir §Journal en fin de document.**

## Conditions d'observation

Stack de développement locale (PostgreSQL + Mailpit + `symfony serve`), famille de démo
« Famille Moreau » séparée : 3 membres (parent A/admin, parent B/admin, beau-parent en lecture),
2 enfants, 3 notes, 6 événements dont une série hebdomadaire, 2 échanges dont une décision à
2 options, rythme alterné + 2 exceptions, vacances scolaires officielles importées (zone B/Nantes)
avec règle `alternating_weeks`. Parcours des 6 écrans applicatifs + authentification + hors-ligne,
à **1320×757** (gabarit de référence §8) **et 1920×951**, plus mesure des points de rupture par
iframes de largeur contrôlée.

Console JS propre (aucune erreur). Tokens du design system **conformes au §8 à l'hexadécimal près**.
Les défauts relevés portent sur l'**application** du système, pas sur les tokens.

---

## Ordre de correction retenu

L'ordre est guidé par le rapport « constats fermés / effort », puis par la gravité.

### Étape 1 — Couche partagée pour les contrôleurs Stimulus  ⬅ meilleur rapport effort/gain

Six contrôleurs (`children`, `garde`, `agenda`, `exchanges`, `settings`, `notif_prefs`) portent la
**même** implémentation copiée-collée de `_send()` / `_showError()`, et donc les mêmes trous. Une
classe de base ferme cinq constats d'un coup, dont deux P1. Le motif correct existe déjà, isolé,
dans `auth_form_controller.js`.

| # | Constat | Gravité |
|---|---|---|
| 1.1 | Aucun bouton de mutation n'est désactivé pendant la requête → **doublons définitifs** (aucune suppression de note ni de message côté web) | **P1** |
| 1.2 | Session expirée (`ACCESS_TOKEN_TTL=900`) → « Une erreur est survenue. » + saisie perdue ; le front n'appelle jamais `POST /api/auth/refresh` alors que le refresh token vit 30 jours | **P1** |
| 1.3 | Aucune confirmation de succès nulle part (aucun `addFlash` dans tout `src/Controller/`) | P2 |
| 1.4 | `_showError()` fait `window.scrollTo({top:0})` alors que le bandeau du contrôleur `garde` est à ~1250 px sur `/settings` → l'échec paraît **silencieux** | P2 |
| 1.5 | `Idempotency-Key` jamais envoyé alors que le serveur sait le lire (`CreateMessageProcessor`, `CreateExchangeProcessor`) | P2 |
| 1.6 | Messages d'erreur de l'API affichés en **clés brutes** (`data.detail` verbatim) | **P1** (volet client de 3.2) |

### Étape 2 — Correctifs CSS (trois causes racines, quatre symptômes visibles)

Tous validés empiriquement dans le navigateur avant rédaction.

| # | Constat | Gravité |
|---|---|---|
| 2.1 | `.field input { width:100% }` s'applique aux cases à cocher → la case « Journée entière » est **disloquée** de son libellé ; et au sélecteur de couleur enfant → rendu en **trait noir** de 192×27 px qui ne montre pas la couleur réelle | **P1** |
| 2.2 | `1fr` = `minmax(auto,1fr)` → `/children` **déborde de 135 px** à 1320 px (gabarit de référence), colonne Notes coupée. Correctif mesuré : `scrollWidth` 1455 → 1305 | **P1** |
| 2.3 | Sous le point de rupture 900 px la page garde une **largeur plancher de ~896 px** (débordement de 481 px à 415 px de viewport) ; entre 900 et 1190 px le layout à 2 colonnes ne se replie pas encore (débordement de 105 à 304 px). Contredit `cdc.md` §1, §4 (l.217) et §8 (l.489). Correctif mesuré : 481 px → **0** | **P1** |
| 2.4 | Le filtre agenda actif est **invisible** : `.agenda-chip.is-active` et `.agenda-chip.pill--health` ont la même spécificité, la seconde est déclarée après → `border-color:transparent` gagne ; il ne reste qu'un halo à **1,01:1** de contraste. Aucun `aria-current` | P2 |
| 2.5 | Les interrupteurs n'ont **aucun focus visible** au clavier (case native `opacity:0`, aucun `:focus-visible` reporté sur le curseur) — WCAG 2.4.7. Le motif correct est déjà écrit deux fois dans le même fichier | P2 |
| 2.6 | `/garde` ne marque **jamais le jour courant**, alors que `HomeController` calcule déjà `isToday` pour la mini-semaine de l'accueil | P2 |

### Étape 3 — Chaîne i18n

| # | Constat | Gravité |
|---|---|---|
| 3.1 | `translation.yaml` déclare `framework.default_locale: en` et **écrase** `framework.yaml` (`fr`) — chargement alphabétique de `config/packages/`. Vérifié : `debug:config framework default_locale` → `en`. Aucun catalogue `*.en.yaml` n'existe → le bandeau **anti-données-de-santé**, invariant explicite de `CLAUDE.md`, s'affiche « `child.health_warning` » | **P1** |
| 3.2 | Les messages de contrainte sont traduits dans le domaine **`validators`**, or les clés ne vivent que dans `messages+intl-icu.fr.yaml` et aucun catalogue `validators*` n'existe → `firstName: child.error.first_name_required` affiché à l'écran. Idem pour les exceptions métier (`custody.error.*`) et `"title":"An error occurred"`. Aucun client ne mappe ces clés (ni web, ni ARB mobile) | **P1** |
| 3.3 | Valeurs d'enum et libellés techniques exposés : `Règle : alternating_weeks` (`/garde` et `/settings`), en-têtes de droits en `custody / children_notes / exchanges` avec valeurs `none/view/edit`, rôles `step_parent`, badge `parent · admin` | P2 |
| 3.4 | Heures des messages d'échange affichées en **UTC** (02:21 Paris → « 00:21 ») : `|date('d/m H:i')` sans `timezone`, `date.timezone` valant UTC. Défaut latent identique sur `{{ "now"|date("d/m/Y") }}` de l'accueil (date de la veille entre 0 h et 2 h) | P2 |
| 3.5 | Dates des notes en **ISO** (`2026-07-26`) sur la fiche enfant, alors que l'accueil écrit `26/07` pour la même note ; `_note_item.html.twig` imprime la même valeur dans l'attribut machine et dans le texte visible | P3 |
| 3.6 | Dates des périodes spéciales **sans année** alors que l'import ramène l'année scolaire précédente (« Vacances de la Toussaint 18/10→02/11 » = octobre 2025), et le panneau liste **toutes** les vacances de la famille sans filtre sur le mois affiché | P2 |

### Étape 4 — Garde-fous des actions destructives

| # | Constat | Gravité |
|---|---|---|
| 4.1 | Suppression d'un événement — voire de **toute une série** — sans confirmation, avec un bouton visuellement identique à « Enregistrer » ; si la portée a été basculée sur « Toute la série », le clic détruit l'occurrence pivot et toutes les suivantes sans rappel de la portée | **P1** |
| 4.2 | Un simple clic sur une case du calendrier **réassigne un jour de garde** : aucune confirmation, aucun retour de succès, aucune annulation depuis cet écran (la suppression n'existe que dans `/settings`). Vérifié : le 10/08 est passé de B à A, stats 15/16 → 16/15 | P2 |
| 4.3 | « Appliquer » une règle de vacances **réinitialise le parent de départ sur A** (aucun `selected` sur le select, et `h.rule.params` n'est jamais affiché) → le serveur remplace la règle et **régénère la garde**, retournant plusieurs semaines d'été si la règle démarrait chez B | P2 |
| 4.4 | Aucune variante destructive dans le CSS : « Supprimer le profil », « Supprimer » (exceptions, membres, liens) sont des boutons neutres | P2 |
| 4.5 | Un membre sans « Nom complet » (champ facultatif) est signé **« Membre supprimé »** dans les fils d'échange, avatar « ? », pendant que la barre latérale de la même page affiche son e-mail : chaîne vide non normalisée en `null` + `??` au lieu de `?:`. Une note poussée en temps réel apparaît sans auteur du tout | **P1** |

### Étape 5 — ✅ LIVRÉE le 2026-08-03 (arbitrage rendu : ne pas assombrir · initiale · amender le §8)

> Constat initial ci-dessous, conservé tel quel. **Issue** : 5.5 corrigé par un marqueur non
> chromatique (initiale du parent, web + mobile) ; 5.1 à 5.4 **conservés en divergence assumée** et
> documentés au nouveau **cdc §8.1**, avec un `@media (prefers-contrast: more)` conforme AA en
> contrepartie. Détail dans le journal (2026-08-03).

#### Étape 5 — Arbitrage produit requis avant codage : les contrastes

Ces constats **ne peuvent pas être corrigés sans décision**, parce que `cdc.md` §8 (l.475-481) et la
maquette prescrivent verbatim des paires de couleurs qui échouent au niveau AA. Corriger exige
d'**amender explicitement le design system**, faute de quoi un futur passage « réalignera » le CSS
sur la spec et réintroduira le défaut.

| # | Constat | Ratio mesuré | Seuil |
|---|---|---|---|
| 5.1 | `--muted-2 #A39B8C` sur crème, à 10-12 px : en-têtes du calendrier, eyebrows de toutes les cartes, mini-semaine, compteur de votes, libellés de nuits | **2,44:1** | 4,5:1 |
| 5.2 | `--muted #857E71` sur une vingtaine de règles, dont les sous-titres d'authentification | 3,30 à 4,02:1 | 4,5:1 |
| 5.3 | Les cinq paires `--cat-*` à 11-12 px : École **2,21:1**, Autre 3,21, Musique 3,25, Santé 3,30, Sport 3,44 | 2,21 à 3,44:1 | 4,5:1 |
| 5.4 | Étiquettes d'échange (discussion 3,44:1, en retard 3,68:1, décision 4,27:1) et `.form-error` **4,08:1** (tous les messages d'erreur) | 3,44 à 4,27:1 | 4,5:1 |
| 5.5 | Attribution parent A/B portée **uniquement** par la teinte : fonds 1,12:1, bordures 1,21:1, mini-semaine 1,09:1 ; la case ne contient que le numéro du jour, rendu en `--ink` dans les deux cas. S'effondre en protanopie et en niveaux de gris — WCAG 1.4.1 | 1,09 à 1,21:1 | — |

Aucun `prefers-contrast`, aucun `forced-colors`, aucun test de contraste dans `web-e2e/`.

### Étape 6 — Écarts de périmètre (vrais développements)

Ces points remettent en cause le « Lots 0→8 TERMINÉS » de `PROGRESS.md` : ce sont des
fonctionnalités **contractualisées et non livrées côté web**, pas des défauts de finition.

| # | Constat | Référence contrat |
|---|---|---|
| 6.1 | `/garde` n'a **ni fiche de jour ni activités d'agenda** : une case est un `<div>` avec le numéro, un « vac. » et une pastille. Un membre en lecture seule n'a **aucune** interaction ; personne ne peut connaître le parent en clair, l'heure de transmission, la raison de l'attribution ni les activités du jour | `cdc.md:158` et `:511` (**Lot 3**) ; maquette `:234-242` et `:828-868` |
| 6.2 | Le champ « Commentaire » d'un événement n'est **saisissable dans aucun formulaire web** (ni création ni édition), alors que le gabarit sait l'afficher et que l'API l'accepte de bout en bout | `cdc.md:117` et `:312` ; maquette `:935-936` |
| 6.3 | **Aucun écran RGPD ni gestion de compte** sur le web : pas d'export, pas de suppression de compte, et « Mon compte » est un simple affichage (nom, téléphone, locale, mot de passe non modifiables) alors que l'API expose `POST /api/me/export`, `DELETE /api/me`, `PATCH /api/me` et que le mobile a l'écran | Lot 8 (`cdc.md:531`) |
| 6.4 | `/settings` est une page unique de 4 094 px avec 9 liens d'ancre dont **aucun n'est marqué actif**, là où la maquette impose un master-détail à **trois onglets exclusifs** | maquette `:581/630/720`, état `:1596` |
| 6.5 | L'accueil n'énonce **ni chez qui sont les enfants ni le prochain échange** : la maquette impose une phrase de 25 px, une sous-ligne horloge (« Retour chez X dimanche 28 juin, 19h00 ») et les avatars des enfants ; `custodyWeek()` ne calcule même pas l'information | maquette `:112-127` et `:1300-1306` |
| 6.6 | **« Familles liées » est inutilisable de bout en bout** : le formulaire demande un UUID que l'UI n'affiche nulle part | Lot 5 |
| 6.7 | Une famille à **un seul parent actif** ne peut pas enregistrer de rythme (deux selects proposant la même personne, 422 systématique) et boucle entre `/settings` et `/garde` pendant tout le premier jour d'usage. Aucun test ne couvre `parents|length < 2` | — |
| 6.8 | Les liens des e-mails de notification **perdent leur cible** (redirection `/login` sans `next`, redirection en dur vers l'accueil) et peuvent aboutir sur la **page d'erreur brute de Symfony** : `ProblemExceptionSubscriber` ne traite que `/api` et `templates/bundles/` est absent → page blanche en anglais pour toute URL web inconnue | — |
| 6.9 | Réglages « Jour / Heure d'échange » **en double**, dont un inerte : ceux de `/settings` § Famille sont stockés dans `family.settings` et **lus par personne** (ni le moteur, ni le mobile) ; ils s'affichent vides alors que le rythme est configuré. Conventions de numérotation des jours divergentes (1=Lundi vs 0=Dimanche) | — |
| 6.10 | Impossible de **créer ou rejoindre une seconde famille** depuis l'UI : `/onboarding` répond 200 mais aucun template ne le lie, et le menu du sélecteur de famille n'offre aucune action | — |
| 6.11 | Barre latérale **non collante** : sur `/settings` (3 962 px), après 1 500 px de défilement la navigation principale est hors écran (`top: −1316`) et « Se déconnecter » est à `top: 2380`. La sous-navigation des réglages est pourtant `position: sticky` | — |
| 6.12 | Vue à droits restreints : l'accueil affiche « Le planning de garde s'affichera ici. » à un membre qui n'aura **jamais** ce droit ; l'onglet « Garde » reste dans la navigation et mène à un refus ; « + Nouvel événement » est proposé à quelqu'un qui n'a que `view` | — |

### Reste à traiter (P2/P3 de finition, sans dépendance)

Labels du panneau d'édition d'événement non associés à leur champ (`</label>` fermés avant le champ,
aucun `for`) — WCAG 1.3.1/4.1.2 ; pas de lien d'évitement clavier ; sélecteur de famille sans
`aria-expanded`/`role`/fermeture par Échap ; cases du calendrier sans `aria-label` ; barres A/B des
« périodes spéciales » **décoratives et trompeuses** (toujours 50/50, y compris sur une exception
attribuée à 100 % à un parent et sur 5 périodes sans aucune règle) ; hiérarchie des boutons
incohérente en **couleur** (terracotta vs noir sans règle) **et en forme** (pilule 999 px vs
rectangle 42 px dans un même formulaire, alors que la maquette n'utilise aucune pilule pour un
bouton d'action) ; contenu bloqué à 1320 px et **calé à gauche** (bande morte de 348 px à 1920 px) ;
badges de droits inversés (« Lecture » porte le teal du parent B) ; « une carte pour un seul
bouton » sur `/agenda` et `/echanges` avec le mois affiché deux fois ; champs de série toujours
visibles quand la récurrence vaut « Aucune » ; `/echanges` ne présélectionne rien alors que
`/children` le fait ; exceptions n'indiquant pas le parent bénéficiaire et libellé tronqué par le
bouton ; cinq traitements de champ de saisie dont deux dans le même formulaire ; déclencheur de
création en trois apparences ; prénoms tronqués sur les 14 cartes-nuit dès les données de démo ;
« Parent A »/« Parent B » avant le premier enregistrement du rythme ; famille neuve dont le volet
principal demande de sélectionner un élément inexistant ; marqueur « vac. » répété sans légende ;
mois courant coloré seulement à partir d'aujourd'hui sans explication ; aucun lien vers l'éditeur de
rythme depuis `/garde` une fois configuré ; cartes blanches imbriquées dans des cartes blanches ;
pastille des événements liés re-teintée en violet (identique à « Musique »).

### Dette de test révélée

`web-e2e/tests/responsive.spec.ts` ne vérifie l'absence de débordement horizontal que sur
**`/login`** — page sans barre latérale ni calendrier. **Aucun écran authentifié** n'est couvert par
ce garde-fou, ce qui explique que 2.2 et 2.3 aient passé la DoD. Aucun test de contraste. Aucun jeu
de données à un seul parent (`grep parents_invalid tests/` → 0).

### Constats écartés par la vérification adversariale

Trois sur 42, tous avec confiance haute : halo de focus jugé trop pâle (symptôme faux, vérifié dans
le navigateur) ; débordement de noms longs dans les tuiles de statistiques (`.garde-stat` est un flex
sans largeur fixe, le texte passe à la ligne — conclusion atteinte deux fois indépendamment) ;
`turbo-refresh-scroll` (affirmations de code exactes, effet inexistant).

---

## Journal

### 2026-07-27 — Revue conduite, plan établi
Revue navigateur (25 constats) + revue croisée multi-agents (39 constats confirmés / 42 explorés,
3 réfutés). Plan de correction ordonné ci-dessus. Aucune modification de code à ce stade.

### 2026-07-28 — Étapes 1 à 4 livrées

**Portes qualité** : backend **313 tests / 2585 assertions** (303 avant), PHPStan niveau 8 **0 erreur**,
php-cs-fixer **0**, lint Twig **30 fichiers OK**, **E2E Playwright 21/21** (19 avant).

#### Étape 1 — Couche partagée Stimulus ✅
Nouveau socle `assets/api_base.js` (classe `ApiController`) dont héritent les **six** contrôleurs
(`children`, `garde`, `agenda`, `exchanges`, `settings`, `notif_prefs`) ; les six `_send()`/`_showError()`
copiés-collés sont supprimés. Ferme d'un coup : **1.1** déclencheur désactivé + `aria-busy` pendant la
requête (plus de double soumission), **1.2** rattrapage du 401 par `POST /api/auth/refresh` puis rejeu
**avec la même clé d'idempotence** — la saisie est donc conservée, et on ne redirige vers
`/login?expired=1` que si le rafraîchissement échoue vraiment, **1.3** confirmation de succès portée par
`sessionStorage` (survit au rechargement) affichée dans une cible `notice` ajoutée aux 6 gabarits,
**1.4** `scrollIntoView({block:'center'})` au lieu de `scrollTo(top:0)`, **1.5** `Idempotency-Key` sur
les créations, **1.6** les violations de validation sont préférées au `detail` RFC 7807 (qui préfixait
le chemin de propriété).

#### Étape 2 — CSS ✅
**2.1** `.field input:not([type=checkbox]):not([type=radio]):not([type=color])` + `label:has(> input[type=checkbox])`
en flex → la case « Journée entière » est alignée avec son libellé ; `input[type=color]` en pastille
56×34 px qui montre enfin la couleur. **2.2 / 2.3** `minmax(0, 1fr)` généralisé (grilles enfants,
`.app-shell`, toutes les grilles repliées), `.main__header` repliable, calendrier et panneau de rythme
en `overflow-x: auto` dans leur carte, `min-width: 0` + `overflow-wrap: anywhere` sur les porteurs de
texte long (profil, membres, lignes de réglages), repli du détail enfant dès 1100 px.
**2.4** `.agenda-chip.is-active` déplacé **après** les teintes de catégorie (à spécificité égale la
dernière règle gagnait) + contour, encre soutenue et `aria-current="page"`. **2.5** focus visible des
interrupteurs. **2.6** marqueur du jour courant sur le calendrier mensuel (`isToday` exposé par
`GardeController`, comme la mini-semaine de l'accueil le faisait déjà). **2.13** `margin-inline: auto`
sur `.main` (fin de la bande morte de 348 px à 1920 px).

**Deux causes racines découvertes en cours de route, plus larges que le constat initial :**
- `[hidden]` ne posait qu'un `display:none` de feuille UA : `.agenda-recurrence { display:flex }` le
  défaisait, d'où les champs de série (« Jusqu'au », « nombre d'occurrences », « Exclure les vacances »)
  visibles pour un événement ponctuel. Corrigé par `[hidden] { display: none !important }` global.
- **Le bloc `@media (max-width: 900px)` était déclaré AVANT les composants qu'il replie** : à
  spécificité égale la règle de base gagnait, donc le repli de `/garde`, `/settings` et `/echanges`
  n'a **jamais** fonctionné. Le bloc est désormais en fin de feuille, avec un commentaire d'avertissement.

Mesures après correctifs (`scrollWidth − clientWidth`), **6 écrans × 9 largeurs de 390 à 1440 px : 0
partout** (avant : 135 px à 1320 px sur `/children`, 481 px à 415 px sur `/garde`).

#### Étape 3 — i18n ✅
**3.1** `default_locale: en` retiré de `translation.yaml` (il écrasait `framework.yaml` par ordre
alphabétique) → le bandeau anti-données-de-santé affiche enfin son texte, et les réponses RFC 7807
sortent localisées (`"title": "Données invalides"` au lieu de `problem.title.unprocessable`).
**3.2** nouveau catalogue `translations/validators+intl-icu.fr.yaml` (le Validator traduit dans le
domaine `validators`, pas `messages`) → `"message": "Le prénom est requis."` au lieu de la clé brute,
web **et** mobile. **3.3** dictionnaires FR (`holidayRuleLabel`, `roleLabel`, `rightDomainLabel`,
`rightLevelLabel`) déclarés dans `shell.html.twig` — et **non** dans un fichier inclus : un
`{% include %}` évalue ses `{% set %}` dans son propre contexte, les variables ne remontaient pas.
**3.4** `timezone` passé au filtre `date` sur les messages d'échange (02:21 Paris s'affichait « 00:21 »)
et sur la date du jour de l'accueil. **3.5** `noteDateLabel` séparé de `noteDate` (attribut machine vs
texte affiché), y compris pour les notes poussées par Mercure. **3.6** périodes spéciales filtrées sur
le mois affiché, avec l'année et le parent bénéficiaire.

**Garde-fou ajouté** : `TranslationCatalogTest` couvre désormais la locale par défaut, la résolution du
bandeau santé, la présence de chaque clé `#[Assert]` dans le domaine `validators`, la non-divergence
des deux catalogues, et la localisation des messages de contrainte natifs de Symfony.

#### Étape 4 — Garde-fous destructifs ✅
**4.1** confirmation de suppression d'événement **libellée selon la portée réellement transmise**
(« … ET toutes les occurrences suivantes de la série ? »). **4.2** confirmation nommée de l'échange de
jour (« Attribuer le 10/08/2026 à Nicolas Moreau ? ») + `aria-label` complet sur les cases du calendrier
(un lecteur d'écran n'annonçait que le numéro du jour). **4.3** parent de départ des règles de vacances
**préselectionné d'après la règle en vigueur** et nommé avec le prénom réel — « Appliquer » le remettait
silencieusement sur A et régénérait plusieurs semaines de garde ; la règle en vigueur est désormais
affichée. **4.4** variante `.btn--danger` créée et appliquée aux 7 boutons destructifs, avec
confirmations nommant la cible (membre, invitation, exception, lien). **4.5** `User::setFullName()`
normalise la chaîne vide en `null` **à la source** (couvre les 4 appelants et les futurs), et les 5
replis `?? getEmail()` passent en `?:` → un membre sans « Nom complet » n'est plus signé
« Membre supprimé » avec un avatar « ? ».

#### Dette de test fermée
`responsive.spec.ts` ne couvrait que `/login`. Nouveau test **`les ecrans authentifies ne debordent pas
horizontalement`** : inscription → famille → enfant, puis les **6 écrans applicatifs** vérifiés sans
débordement, exécuté sur les **deux** projets Playwright (Desktop Chrome **et** Pixel 7 / 412 px).
C'est le garde-fou qui manquait pour empêcher 2.2 et 2.3 de revenir.

#### Tests E2E adaptés (changements de comportement voulus)
- `lot3-garde`, `lot3-garde-advanced`, `lot4-agenda` : `page.on('dialog', d => d.accept())` — les
  actions destructives demandent désormais confirmation, et Playwright rejette les dialogues par défaut.
- `lot3-garde-advanced` : l'assertion portait sur la valeur d'enum `fixed_parent` ; elle porte
  maintenant sur le libellé affiché « Toujours chez le même parent » **et** sur le parent de départ.
- `lot3-garde` : **échec révélé, non causé, par ces travaux.** Le test affirmait la présence des deux
  couleurs A **et** B dans le mois **courant**. Or le moteur fige le passé : en fin de mois les jours
  restants tombent tous dans la même semaine d'alternance, et un seul parent apparaît. Le test ne
  passait donc que selon le jour d'exécution (il a échoué le 28/07 parce qu'il ne restait que 28→31,
  tous du même côté). Il affiche désormais le **mois suivant**, toujours complet.

#### Décision assumée
Les exceptions de garde sont maintenant visibles des membres en lecture seule (elles étaient réservées
à `custody:edit` alors que les vacances, elles, étaient visibles) : un parent qui consulte doit pouvoir
savoir **pourquoi** un jour est une exception, information que le calendrier montre déjà.

#### Reste à faire
Étapes 5 (arbitrage contrastes — **décision produit requise**) et 6 (écarts de périmètre), plus la
liste « Reste à traiter » ci-dessus. Rien n'a été entamé sur ces deux étapes.

### 2026-08-03 — Étape 5 livrée (arbitrage produit rendu, web + mobile)

**Arbitrage rendu par le PO**, sur les trois questions ouvertes du 28/07 :
**(a) ne pas assombrir** la palette par défaut · **(b)** marqueur = **initiale du parent** ·
**(c) amender** le §8. Le mobile Flutter a été ajouté au périmètre.

#### Décisions et conséquence assumée
La non-conformité AA de 5.1 à 5.4 est **conservée** et devient une **divergence documentée** au
nouveau **cdc §8.1**, ratios mesurés à l'appui — y compris `.form-error` à 3,61:1, qui concerne tous
les messages d'erreur du produit. Le but de l'amendement est qu'un futur réalignement ne la traite ni
comme un bug à corriger, ni comme une licence pour assombrir sans décision. **Contrepartie** ajoutée
sans toucher à la palette livrée : un bloc **`@media (prefers-contrast: more)`** en opt-in système,
où chaque token de texte est assombri à teinte constante jusqu'à ≥ 4,5:1 **sur les quatre fonds**
possibles (`--app-bg`, `--surface`, `--card`, fond de pastille). Conséquence acceptée dans ce mode :
`--muted` et `--muted-2` convergent (4,59 et 4,53 sur `--app-bg`) — aucune valeur plus claire ne
tient le seuil, donc la hiérarchie à trois gris tombe à deux.

#### 5.5 — le constat de nature différente, traité en priorité
Mesures refaites avant codage : le contraste de luminance des fonds A/B est de **1,12:1 en vision
normale**. La couleur n'était donc pas *un* canal parmi d'autres, elle était le **seul**. Simulation
Viénot 1999 : protanopie **1,08:1** sur les bordures (pire que la vision normale), deutéranopie
1,38:1, niveaux de gris 1,20:1, et fonds écrasés en `forced-colors`. Trois situations, pas une.

**Marqueur retenu : initiale du prénom** — le seul canal qui survive aux trois (du texte), et qui
donne en plus l'information *en clair*, ce que ne fait aucune trame ni écart de luminosité. Règle
normative au §8.1 : initiale capitale (accent conservé), **deux lettres** en cas de collision
(`Sophie`/`Simon` → `So`/`Si`), repli `A`/`B` si aucune longueur ne sépare ou si le nom manque.
**Plafond à deux lettres** : défaut trouvé pendant l'implémentation — sans plafond, deux parents sans
nom renseigné retombent sur leur e-mail et produisaient des marqueurs à 4 caractères (« Web »/
« Web2 ») dans une case de 53px déjà chargée. Cas de test dédié.

Livré sur les **deux clients** et les **deux vues** : `/garde` (`.garde-day__parent`, coin bas
gauche — les trois autres sont pris) et la mini-semaine de l'accueil (`.home-custody__parent`, elle
était à 1,09:1) côté web ; `CustodyScreen` côté Flutter. La **légende** porte désormais le marqueur
et reproduit l'aspect de la case (fond doux + bordure) au lieu d'un carré de couleur pleine : une
légende purement chromatique n'explique rien, et le blanc sur `--accent` n'atteignait que 4,05:1.
Marqueur `aria-hidden`, les lecteurs d'écran recevant le **nom en clair** (aria-label de la case
échangeable, ou `.sr-only` ajouté aux cases non interactives — l'aria-label sur un `<div>` sans rôle
n'était de toute façon pas exposé de façon fiable). Bloc `@media (forced-colors: active)` rendant à
la légende son contour et au jour courant son cadre (`box-shadow` étant ignoré dans ce mode).

La règle est **dupliquée** entre `ParentInitials.php` et `parent_initials.dart`, avec des jeux de
tests **jumeaux** (22 cas identiques de part et d'autre) : duplication assumée pour ne pas exposer
une donnée de présentation dans le contrat OpenAPI — le seul risque est de faire diverger les deux
jeux, d'où leur alignement littéral. Côté mobile, la résolution des noms est **best-effort** (comme
l'overlay du Lot 5) : membres non chargés → repli `A`/`B`, marqueur dégradé mais toujours non
chromatique. Effet de bord favorable : la légende mobile affiche enfin le **nom réel** des parents,
là où elle disait « Parent A »/« Parent B ».

#### Garde-fous (la revue avait relevé « aucun test de contraste »)
- `ContrastTokensTest` — recalcule les ratios WCAG **depuis le CSS** (aucune valeur attendue
  recopiée, seul le seuil l'est) pour 7 tokens × 3-4 fonds ; vérifie aussi la présence du marqueur et
  du bloc `forced-colors`. **Vérifié négativement** : en remettant `--muted: #857E71` dans le bloc
  opt-in, il échoue avec « 3,06:1, sous le seuil AA de 4,5:1 ».
- `WebPagesTest::testGardeCarriesNonColourParentMarker` — vérifie que le marqueur porte l'initiale
  **réelle** de chaque parent (« Sophie » → S, « Marc » → M), que les deux diffèrent, que la légende
  les reproduit, que les lecteurs d'écran reçoivent le nom en clair, et que la mini-semaine de
  l'accueil est traitée aussi.
- Test widget Flutter « marqueur non chromatique » — prénoms volontairement **autres** qu'Alice/Bob,
  dont les initiales « A »/« B » seraient indiscernables du repli : le test ne prouverait rien.
- E2E `lot3-garde.spec.ts` — présence sur chaque case attribuée, A ≠ B, autant de marqueurs que de
  cases colorées, et **survie sous `forcedColors: 'active'`**.

#### Portes
Backend **338 tests / 2740 assertions** (313 avant), couverture **85,6 %**, PHPStan 8 à 0, cs-fixer 0,
lint Twig 30 ; mobile **180 tests** (157 avant), couverture **75,9 %**, `dart analyze` 0, `format` 0 ;
**E2E Playwright 21/21**. **Aucun changement d'API, d'OpenAPI, de client Dart, de migration ni de
registre RGPD** (aucun champ personnel nouveau — l'initiale est dérivée d'un nom déjà affiché).
Aucune dépendance ajoutée : `parent_initials.dart` itère sur les **runes** plutôt que d'introduire
`package:characters`, donc INSTALL.md est inchangé.

#### Reste à faire
Étape 6 (12 écarts de périmètre) et la liste « Reste à traiter ». L'étape 5 est **close**.

### 2026-08-03 — Étape 6, vague 1 : bloqueurs à coût contenu (6.7, 6.9, 6.11, 6.12, 6.2)

Les constats datant du 27/07 et les étapes 1 à 5 ayant beaucoup remanié le code, **chaque item a été
re-vérifié dans le code avant correction**. Les cinq ci-dessous étaient tous confirmés.

**6.7 — famille à un seul parent.** Les deux sélecteurs « Parent A » et « Parent B » proposaient la
même personne : « Enregistrer » répondait 422 (titulaires identiques) sans dire pourquoi, et `/garde`
renvoyait vers ce même formulaire — boucle fermée pendant tout le premier jour d'usage. Le builder,
les exceptions et les règles de vacances (toutes désignent un parent bénéficiaire) sont remplacés par
la condition manquante et l'action qui la lève ; `/garde` renvoie vers les membres et non plus vers le
builder. **Deux tests existants échouaient après correction — ils affirmaient la présence du builder
dans une famille à un seul parent, c'est-à-dire qu'ils validaient le bug.** Adaptés, et la dette
signalée par la revue (« aucun test ne couvre `parents|length < 2` ») est fermée dans les deux sens :
un test pour un parent, un pour deux.

**6.9 — réglage inerte supprimé.** Le « jour / heure d'échange » du § Famille était bien le doublon
mort : stocké dans `family.settings` (« persistés non interprétés », décision Lot 1 §3.7), lu ni par
le moteur, ni par le mobile, ni par rien. Pire, les deux champs employaient des conventions
incompatibles (1=Lundi…7=Dimanche ici, 0=Dimanche…6=Samedi dans le rythme) : une valeur recopiée de
l'un vers l'autre désignait un autre jour. Saisie retirée de l'UI ; `FamilyResource` conserve les
champs, donc **contrat OpenAPI inchangé**. Les lignes correspondantes du contrôleur Stimulus ont dû
partir aussi : le champ disparu, `data.get()` aurait renvoyé `null` et le processor — qui n'écrit que
les clés reçues — aurait effacé la valeur en base à chaque sauvegarde des réglages.

**6.11 — barre latérale collante.** `align-self: start` est la clé : sans lui, la colonne du grid est
étirée sur toute la hauteur de la ligne et `sticky` n'a rien à coller. Neutralisée dans le repli
responsive, où la barre devient horizontale.

**6.12 — l'UI suit les droits.** Constat plus large que décrit : seul l'onglet Échanges se masquait
(via `ExchangeBadgeExtension`) ; **Garde, Agenda et Enfants s'affichaient sans vérifier le domaine**
et menaient à un écran de refus. Nouvelle extension Twig `can('domaine', 'niveau')` — le shell est
rendu pour toutes les pages, y compris celles dont le contrôleur n'a aucune raison de résoudre les
droits, d'où une extension plutôt qu'une variable de contrôleur (appartenance résolue une seule fois
par requête). L'encart « Le planning de garde s'affichera ici. » n'est plus montré à qui n'aura jamais
le droit, et porte désormais le lien de configuration quand la promesse est tenable ; « + Nouvel
événement » exige `agenda:edit`.

**6.2 — champ Commentaire.** Le contrôleur Stimulus savait **déjà** l'envoyer (création et PATCH
partiel), et l'API l'accepte (max 2000) : seul le HTML manquait, dans les deux formulaires. Au
passage, `textarea` rejoint le style générique `.field` au lieu d'un sixième traitement local de champ
de saisie — le déséquilibre entre ces traitements est lui-même un constat de la revue.

Portes : backend **341 tests / 2778 assertions** (338 avant), PHPStan 0, cs-fixer 0, Twig 30 ;
**E2E 21/21** (assertions Commentaire ajoutées au parcours agenda, aller-retour complet jusqu'à
l'affichage). Aucun changement d'API, d'OpenAPI, de migration ni de registre RGPD.

**Reste de l'étape 6** : vague 2 — parcours cassés (**6.8** liens e-mail sans cible + page d'erreur
brute de Symfony, **6.10** seconde famille impossible, **6.6** familles liées inutilisables) ;
vague 3 — vrais développements (**6.1** fiche de jour + activités sur `/garde`, **6.5** phrase de
garde sur l'accueil, **6.4** `/settings` en trois onglets, **6.3** écran RGPD web).

### 2026-08-28 — Étape 6, vague 3 : 6.1 — pastilles d'activité + fiche de jour sur `/garde`

Constat re-vérifié avant correction, et plus large qu'écrit : `GardeController` **n'injectait ni
`AgendaReader` ni `UpcomingEventsFinder`** — l'agenda de la famille n'était donc jamais lu. La seule
pastille existante (`garde-day__overlay`) venait de `overlayBadges()`, c'est-à-dire des familles
**liées**, désactivées par défaut. Aucune régression : la fonctionnalité n'avait jamais existé.

**Pastilles (maquette `:236-238`).** Indexation par jour **couvert**, pas par jour de début : la
liste de `/agenda` groupe sur `startsAt`, ce qui laisserait un séjour de trois jours invisible sur
les deux suivants. Une fin posée à minuit pile est reculée d'une seconde, sans quoi un créneau
18h→00h posait une pastille sur le lendemain. La boucle est bornée aux clés de jour du mois — un
`all-day` de plusieurs mois ne doit pas faire itérer hors grille. Groupement dans le fuseau **de la
famille** (celui dans lequel les horaires ont été saisis), là où la grille de garde reste en
Europe/Paris comme le moteur. Deux activités affichées par case, puis « +N » ; sous 900 px la case
tombe à ~55 px et seuls les points subsistent, avec un contour en `forced-colors` sans quoi
l'information disparaîtrait entièrement sur téléphone dans ce mode.

**Fiche de jour (US-CUST-6, maquette `:828-868`).** Route `GET /garde/jour/{date}` rendant un
**fragment**, chargé à la demande dans un `<turbo-frame>` logé dans un `<dialog>` natif. Trois choix
et leur raison :

- `<dialog>` plutôt qu'une surcouche maison : fond modal, piège à focus, restauration du focus et
  touche Échap viennent du navigateur — tout ce qu'une modale en `<div>` réimplémente, souvent mal ;
- chargement à la demande plutôt que 28 à 31 fiches rendues dans la page : sinon transmission et
  activités sont calculées 31 fois pour un jour consulté ;
- le fragment vit dans la portée du contrôleur Stimulus `garde`, donc ses `data-action` (fermeture,
  « Échanger ce jour ») sont câblés sans une ligne de JS de plus. Le `<turbo-frame>` enveloppe le
  fragment **entier**, réponse de refus comprise : sans frame de même id dans la réponse, Turbo
  n'affiche rien et l'utilisateur verrait une modale vide au lieu du refus.

**Transmission.** « Retour chez {Parent} le {date} à {handoverTime} » est dérivée des `CustodyDay`
**matérialisés** qui suivent (`DayDetailReader`, fenêtre de 45 jours), et non d'une projection du
rythme : exceptions et règles de vacances sont ainsi prises en compte comme partout ailleurs. En
bout d'horizon glissant, aucun changement ne suit — la phrase est alors **omise** plutôt qu'inventée.

**Le clic sur une case change de sens.** Il ouvrait `swapDay` : un clic, ou la touche Espace en
parcourant le planning au clavier, réattribuait durablement un jour de garde derrière un simple
`confirm()`. Il ouvre désormais la fiche, pour tout membre `custody=view` — qui n'avait jusqu'ici
**aucune** interaction — et « Échanger ce jour » vit dans la fiche, sous `custody=edit`, comme la
maquette le prescrit (`:862`). Sur un jour passé le bouton est présenté **inerte** avec sa raison,
l'API refusant l'exception rétroactive (422, §3.3) : mieux vaut une action visiblement fermée qu'une
action qui échoue.

**Les deux domaines de droit sont vérifiés séparément** — c'est le croisement qu'un écran
server-rendered laisse fuiter sans qu'aucun test d'API ne s'en aperçoive : `custody` ouvre la fiche,
`agenda` ouvre les activités. Sans `agenda`, la section entière disparaît (des cases comme de la
fiche) : son état vide dirait déjà s'il y a ou non des activités.

Deux défauts trouvés en cours de route : `text-transform: capitalize` rendait « Mercredi 2 Septembre
2026 » — en français le nom du mois reste en minuscules, la capitale initiale est posée au gabarit ;
et `/garde` ne transmettait pas les noms des parents au contrôleur Stimulus, si bien que la
confirmation d'échange disait « l'autre parent » au lieu de nommer la personne.

Portes : backend **387 tests / 3196 assertions** (371 avant — 16 nouveaux dans
`GardeDayDetailTest`, un par comportement contractuel) / couverture **86,4 %** / PHPStan 0 /
cs-fixer 0 / Twig 36 ; **E2E 21/21**, le parcours garde exerçant désormais pastille, fiche, Échap et
échange depuis la fiche. Aucun changement d'API, d'OpenAPI, de client Dart, de migration, de
registre RGPD ni de dépendance.

**Reste de l'étape 6** : vague 2 — **6.8**, **6.10**, **6.6** ; vague 3 — **6.5** phrase de garde sur
l'accueil (elle réutilisera `DayDetailReader`, qui calcule déjà la transmission), **6.4**
`/settings` en trois onglets, **6.3** écran RGPD web.
