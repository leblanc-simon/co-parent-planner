# Lot 2 — Enfants & notes

> Spécification détaillée d'implémentation. Source de vérité : `specs/cdc.md` (§2.3, §2.8, §3.5, §5.2, §6.2, §7) et `CLAUDE.md` (invariants verrouillés). En cas d'ambiguïté de rendu visuel, les maquettes HTML/JSX du bundle Claude Design font foi. Ce document ne contredit aucune décision d'architecture verrouillée (§0 du cdc).

---

## 0. Objectif & périmètre

### 0.1 Objectif

Permettre aux membres d'une famille de **gérer le profil de chaque enfant** (informations pratiques libres) et de **partager des notes** rattachées à l'enfant, dans le strict respect du modèle de droits (RBAC par Voters) et de l'invariant « pas de données de santé ». L'écran Enfants est en **master-détail** (liste des enfants → fiche détaillée), décliné en web (Symfony UX/Twig) et mobile (Flutter). Les notes ajoutées sont **poussées en temps réel** via Mercure (Turbo Streams côté web, SSE/JSON côté mobile).

### 0.2 Dans le périmètre

- Entités **`Child`** et **`Note`** (création des tables, migrations, mapping Doctrine, validation, sérialisation API Platform).
- Écran **Enfants** master-détail :
  - liste des enfants de la famille active (avatar couleur + initiale, prénom, âge calculé) ;
  - fiche détail : prénom, couleur d'avatar, initiale, date de naissance → **âge calculé**, niveau/établissement (libellé libre), **informations pratiques** (liste label/valeur libre, ordonnée), **notes partagées** (auteur, date, texte) ;
  - **bandeau d'avertissement persistant** « ne pas saisir de données de santé » sur l'écran d'édition ;
  - bloc **« Accès & droits »** (US-CH-2) : qui peut voir/éditer l'enfant et ses notes, avec quel niveau.
- **CRUD enfant** : créer, consulter, éditer, supprimer un profil enfant (selon droit `children_notes`).
- **CRUD note** : lister, ajouter, éditer/supprimer (par l'auteur ou un parent/admin — politique verrouillée §5.4 #1/#2, §3.7), soumis au droit `children_notes`.
- **Chiffrement au repos** de `Note.text` 🔒 (AES-256-GCM applicatif).
- **Temps réel** des notes : publication sur le topic Mercure `/families/{id}/notes` à chaque note ajoutée.
- **Journalisation** (`AuditLog`) de l'accès aux notes et des modifications de profil/notes (§5.4 du cdc : « accès aux notes » est explicitement listé comme action sensible).
- **Registre des données personnelles** : entrée pour le nouveau champ personnel `Note.text` (et vérification que `Child.first_name`, `Child.info`, `Child.birth_date` sont couverts), conformément à l'invariant de DoD.
- **i18n** (fr-FR livrée) : toutes les chaînes UI externalisées, format ICU (notamment le pluriel/format de l'âge).
- **Tests** : unitaires (calcul d'âge, Voter `children_notes`, chiffrement/déchiffrement Note, sérialisation info), fonctionnels API (scope famille, droits, CRUD), E2E web (Playwright), widgets/intégration Flutter.

### 0.3 Hors périmètre (traité dans d'autres lots ou en v2)

- **Rattachement compte-enfant ↔ profil `Child`** (champ `Membership.child_id`) : la *relation* est créée/gérée au **Lot 1** (Membership) ; le Lot 2 la **consomme en lecture** pour le bloc « Accès & droits » mais ne crée pas la mécanique d'invitation/rattachement.
- **Définition/édition des droits granulaires** d'un beau-parent ou d'un enfant : géré au **Lot 1** (écran Paramètres › membres). Le Lot 2 **applique** ces droits via le Voter `children_notes`, il ne les édite pas.
- **Agenda lié à l'enfant** (événements concernant un enfant) : **Lot 4**.
- **Échanges rattachés à un enfant** : **Lot 6**.
- **Garde** (l'enfant n'a pas de planning de garde propre : la garde est au niveau famille) : **Lot 3**.
- **Overlay famille liée** : **Lot 5** — aucune donnée enfant ne fuit hors de la famille (et l'overlay ne concerne que l'agenda en lecture seule, jamais les profils/notes).
- **Export/suppression RGPD, anonymiseur, purge** des notes et profils : la mécanique transverse est livrée au **Lot 8** ; le Lot 2 se contente de poser les attributs déclaratifs de registre et le chiffrement.
- **Notifications e-mail** « note ajoutée » : le canal e-mail est livré au **Lot 7**. Le Lot 2 livre uniquement le push temps réel Mercure.

### 0.4 Contexte v1 / v2

Le module Enfants & notes est **entièrement v1**. Aucun champ médical typé n'est créé ni en v1 ni en v2 (invariant permanent « pas de données de santé », cdc §1.4.1 et §7). Les informations pratiques restent des paires `{label, value}` libres ; les presets médicaux du prototype (« Allergies », « Médecin ») ne sont **pas** repris (divergence assumée vs maquette).

---

## 1. Dépendances

### 1.1 Lots prérequis (avant)

| Lot | Apport requis pour le Lot 2 |
|---|---|
| **Lot 0 — Fondations** | Squelette Symfony + API Platform + Doctrine + PostgreSQL ; auth JWT ; Voters (squelette) ; i18n (ICU) ; Mercure opérationnel + JWT Mercure ; AssetMapper/Turbo/Stimulus et thème §8 ; couche chiffrement applicatif (service de (dé)chiffrement AES-256-GCM via enveloppe KMS UE) ; squelette RGPD Manager + `registre-donnees.md` initialisé ; harnais de tests (PHPUnit, Playwright, Flutter, PHPStan). |
| **Lot 1 — Familles, membres, rôles & droits** | Entités `Family`, `Membership`, `Right` ; **scope `X-Family-Id`** validé contre les memberships ; **Voter `children_notes`** câblé (niveaux `none|view|edit`) avec règle « parent ⇒ edit forcé », « enfant ⇒ `none` par défaut » ; protection dernier admin ; `Membership.child_id` (lien compte-enfant ↔ profil) ; `Membership.display_color`. Sans ce lot, impossible de scoper, d'autoriser, ni de calculer le bloc « Accès & droits ». |

### 1.2 Lots débloqués (après)

| Lot | Pourquoi il dépend du Lot 2 |
|---|---|
| **Lot 4 — Agenda** | Un événement concerne **un ou plusieurs enfants** (relation N-N `event_child`) → besoin de l'entité `Child` et de son scope. |
| **Lot 6 — Échanges** | Un échange est **rattaché à un enfant** ou à « toute la famille » (`Exchange.child_id`) → besoin de `Child`. |
| **Lot 3 — Garde** | Indépendant au niveau modèle de données, mais l'écran Accueil et le détail du jour affichent « activités/enfants » : la présence des profils enfants enrichit ces vues (dépendance faible, surtout d'affichage). |
| **Lot 8 — RGPD Manager** | L'anonymiseur et la purge doivent traiter `Note.text` 🔒, l'auteur des notes (« auteur anonymisé »), et `Child.info`/`Child.first_name` → les attributs de registre et le chiffrement posés au Lot 2 sont prérequis. |
| **Lot 7 — Notifications e-mail** | Déclencheur « note ajoutée » (topic `/families/{id}/notes`) : l'événement applicatif émis au Lot 2 sera consommé par le module notifications. |

---

## 2. User stories complètes

> Rôles employés : **parent**, **beau-parent** (`step_parent`), **enfant** (`child`), **admin** (parent + `is_admin`). Le droit pertinent pour ce lot est `children_notes` ∈ {`none`, `view`, `edit`}. Rappels d'invariants : parent ⇒ `edit` forcé sur tous les domaines ; enfant ⇒ `children_notes = none` par défaut ; toute requête est scopée à la **famille active** (`X-Family-Id`).

---

### US-CH-1 — Consulter / éditer le profil d'un enfant et ses notes

**En tant que** membre habilité (parent, beau-parent ou enfant avec `children_notes ≥ view`),
**je veux** consulter et (si `edit`) modifier le profil d'un enfant (infos pratiques libres) ainsi que ses notes partagées,
**afin de** garder une fiche enfant à jour et partagée entre co-parents.

#### Scénario nominal — consultation (view)

```gherkin
Étant donné que je suis membre actif de la famille F avec children_notes = view
  Et que la famille F contient un enfant "Léa" avec date de naissance, niveau et 3 infos pratiques
Quand j'ouvre l'écran Enfants et que je sélectionne "Léa"
Alors je vois son prénom, son avatar (couleur + initiale), son âge calculé, son niveau/établissement
  Et la liste ordonnée de ses informations pratiques (label/valeur)
  Et la liste de ses notes partagées (auteur, date, texte) triées par date décroissante
  Et les actions d'édition (modifier profil, ajouter note) sont absentes ou désactivées
```

#### Scénario nominal — édition du profil (edit)

```gherkin
Étant donné que je suis membre actif de la famille F avec children_notes = edit
  Et que j'ouvre la fiche de l'enfant "Léa" en mode édition
Quand je modifie le niveau/établissement, j'ajoute une info pratique {label:"Pointure", value:"34"} et j'enregistre
Alors la fiche est mise à jour et persistée
  Et un évènement d'audit "child.updated" est journalisé (acteur, famille, entité)
  Et le bandeau "ne pas saisir de données de santé" reste affiché pendant toute l'édition
```

#### Scénario nominal — ajout d'une note (edit)

```gherkin
Étant donné que je suis membre actif de la famille F avec children_notes = edit
Quand je saisis une note "RDV pris pour la rentrée" sur l'enfant "Léa" et que je valide
Alors une Note est créée avec auteur = mon membership, note_date = aujourd'hui (Europe/Paris), text chiffré au repos
  Et la note apparaît en tête de la liste des notes
  Et la note est poussée en temps réel sur le topic /families/F/notes (cf. US-CH-3)
  Et un évènement d'audit "note.created" est journalisé
```

#### Scénario d'erreur — validation des entrées

```gherkin
Étant donné que je suis membre avec children_notes = edit
Quand j'enregistre un profil avec un prénom vide
Alors la requête est refusée avec 422 (application/problem+json) et un message localisé "Le prénom est requis"
  Et aucune écriture n'est effectuée

Étant donné que je suis membre avec children_notes = edit
Quand j'ajoute une info pratique dont le label est vide mais la valeur renseignée
Alors la requête est refusée avec 422 et un message localisé "Le libellé de l'information est requis"

Étant donné que je suis membre avec children_notes = edit
Quand j'enregistre une date de naissance dans le futur (postérieure à maintenant, Europe/Paris)
Alors la requête est refusée avec 422 et un message localisé "La date de naissance ne peut pas être dans le futur"

Étant donné que je suis membre avec children_notes = edit
Quand j'enregistre une note dont le texte est vide ou uniquement des espaces
Alors la requête est refusée avec 422 et un message localisé "Le texte de la note est requis"
```

#### Scénario d'autorisation refusée — droit insuffisant (Voter)

```gherkin
Étant donné que je suis membre de la famille F avec children_notes = view
Quand je tente PATCH /api/children/{id} ou POST /api/children/{id}/notes
Alors je reçois 403 application/problem+json { type:"/errors/forbidden", detail:"Droit 'children_notes:edit' requis." }
  Et aucune écriture n'est effectuée

Étant donné que je suis membre de la famille F avec children_notes = none (ex. enfant par défaut)
Quand je tente GET /api/children/{id} ou GET /api/children/{id}/notes
Alors je reçois 403 application/problem+json { detail:"Droit 'children_notes:view' requis." }
  Et l'enfant et ses notes n'apparaissent pas dans la liste
```

#### Scénario d'autorisation refusée — scope famille

```gherkin
Étant donné que l'enfant "Léa" appartient à la famille F1
  Et que je suis membre de la famille F2 (mais pas de F1)
Quand j'envoie GET /api/children/{id de Léa} avec X-Family-Id = F2
Alors je reçois 404 (ressource hors scope, non révélatrice) — l'enfant de F1 n'est jamais exposé dans le contexte F2

Étant donné que je suis membre de F1 et de F2
Quand j'envoie une requête avec X-Family-Id = F1 mais l'enfant ciblé appartient à F2
Alors je reçois 404 — la ressource est résolue strictement dans le scope du header
```

---

### US-CH-2 — Voir qui a accès à l'enfant et avec quels droits

**En tant que** membre habilité (parent, beau-parent ou enfant avec `children_notes ≥ view`),
**je veux** consulter un bloc « Accès & droits » récapitulant qui peut voir/éditer cet enfant et ses notes,
**afin de** comprendre la confidentialité de la fiche et qui co-gère l'enfant.

#### Scénario nominal

```gherkin
Étant donné une famille F avec :
  - un parent A (children_notes forcé edit)
  - un parent B (children_notes forcé edit)
  - un beau-parent C (children_notes = view)
  - un enfant-membre D rattaché au profil "Léa" (children_notes = view)
  - un membre E (children_notes = none)
Quand j'ouvre la fiche "Léa" et le bloc "Accès & droits"
Alors je vois A et B listés avec le niveau "Édition"
  Et C et D listés avec le niveau "Lecture"
  Et E n'est pas listé (aucun accès)
  Et l'enfant-membre D est signalé comme "C'est son profil" (rattachement child_id)
  Et chaque entrée affiche le nom du membre, son rôle, sa pastille de couleur (display_color) et son niveau d'accès localisé
```

#### Scénario d'autorisation refusée

```gherkin
Étant donné que je suis membre avec children_notes = none
Quand je tente d'accéder au bloc "Accès & droits" de l'enfant
Alors je reçois 403 — le bloc n'est exposé qu'aux membres pouvant déjà voir l'enfant (children_notes ≥ view)
```

#### Scénario de cohérence — parent toujours en édition

```gherkin
Étant donné un membre de rôle parent dont un éventuel Right children_notes serait "view" ou "none" en base
Quand le bloc "Accès & droits" est calculé
Alors ce parent apparaît avec le niveau "Édition" (le rôle parent force edit, le Right stocké est ignoré)
```

---

### US-CH-3 — Recevoir en temps réel les nouvelles notes

**En tant que** membre habilité (`children_notes ≥ view`) ayant la fiche enfant ou la famille ouverte,
**je veux** voir apparaître automatiquement les notes ajoutées par un autre membre, sans recharger,
**afin de** rester synchronisé en co-parentalité.

#### Scénario nominal — web (Turbo Streams over Mercure)

```gherkin
Étant donné que je suis sur la fiche "Léa" (web) avec children_notes = view, abonné au topic /families/F/notes
Quand un autre membre (children_notes = edit) ajoute une note sur "Léa"
Alors un Turbo Stream "prepend" injecte la note (fragment HTML) en tête de la liste des notes
  Et l'auteur, la date et le texte (déchiffré côté serveur au rendu) sont affichés
  Et aucun rechargement de page n'est nécessaire
```

#### Scénario nominal — mobile (SSE/JSON)

```gherkin
Étant donné que l'app mobile est abonnée au topic /families/F/notes via Mercure (EventSource)
Quand une note est ajoutée sur un enfant de F
Alors le cache local Drift est mis à jour et la liste se rafraîchit
  Et la note s'affiche avec auteur/date/texte
```

#### Scénario d'autorisation — abonnement filtré par droits

```gherkin
Étant donné que je suis membre avec children_notes = none
Quand le JWT Mercure est émis pour ma session
Alors le topic /families/F/notes n'est PAS inclus dans mes claims (je ne reçois pas les notes en direct)

Étant donné un membre d'une autre famille F2
Quand une note est ajoutée dans F1
Alors aucun push n'est reçu (le topic est scopé /families/F1/notes et absent de ses claims)
```

#### Scénario de dégradation — Mercure indisponible

```gherkin
Étant donné que le hub Mercure est injoignable (web)
Quand une note est ajoutée par un autre membre
Alors la note n'apparaît pas en direct mais reste visible au prochain chargement/navigation Turbo
  Et aucune erreur bloquante n'est présentée à l'utilisateur (dégradation silencieuse)
```

#### Scénario de dégradation — échec de déchiffrement au rendu du fragment live

```gherkin
Étant donné qu'une note vient d'être créée et commitée (text chiffré correctement en base)
  Et que le service de déchiffrement (KMS) est momentanément injoignable AU MOMENT du rendu du fragment poussé
Quand le pipeline post-commit tente de composer le Turbo Stream (web) / payload SSE (mobile)
Alors la transaction déjà commitée n'est ni annulée ni rejouée (la note existe, chiffrée)
  Et aucun fragment avec texte vide ou erreur cryptographique brute n'est publié
  Et soit le push est omis (la note apparaîtra déchiffrée au prochain chargement),
     soit un placeholder localisé "contenu temporairement indisponible" est poussé (cohérent avec le GET dégradé §4.6)
  Et l'échec est journalisé techniquement sans plantage du listener postFlush/Messenger
  Et une fois le KMS revenu, la note est lisible normalement au rechargement
```

---

### US-CH-4 — Créer un profil enfant

**En tant que** membre avec `children_notes = edit` (parent, ou beau-parent habilité),
**je veux** créer un nouveau profil enfant dans la famille active,
**afin de** référencer un enfant (avec ou sans compte associé).

#### Scénario nominal

```gherkin
Étant donné que je suis membre de F avec children_notes = edit
Quand je crée un enfant {first_name:"Tom", color:"#5E7CB8", birth_date:"2017-09-12", school_label:"CE2", info:[{label:"Pointure",value:"30"}]}
Alors un Child est créé dans F, l'âge est calculé pour l'affichage, l'initiale dérivée de first_name ("T")
  Et la réponse est 201 avec l'identifiant
  Et un audit "child.created" est journalisé
```

#### Scénario d'erreur — date de naissance invalide / absente

```gherkin
Étant donné children_notes = edit
Quand je crée un enfant sans date de naissance
Alors selon la règle (date de naissance optionnelle, cf. §5.2) : si autorisée vide, l'âge n'est pas affiché ;
  si la valeur fournie est une date non parsable, 422 avec message localisé
```

#### Scénario d'autorisation refusée

```gherkin
Étant donné children_notes = view ou none
Quand je tente POST /api/families/{id}/children
Alors 403 "Droit 'children_notes:edit' requis."

Étant donné un membre de rôle child (children_notes ≤ view par construction)
Quand il tente de créer un profil enfant
Alors 403 — un enfant ne crée jamais de structure (invariant : pas d'édition de structure pour child)
```

---

### US-CH-5 — Supprimer un profil enfant

**En tant que** membre avec `children_notes = edit`,
**je veux** supprimer un profil enfant,
**afin de** retirer une fiche obsolète ou créée par erreur.

#### Scénario nominal

```gherkin
Étant donné children_notes = edit et un enfant "Doublon" sans dépendances bloquantes
Quand je confirme la suppression de "Doublon"
Alors le Child et ses Notes sont supprimés (cascade), un audit "child.deleted" est journalisé
  Et un push /families/F/notes peut signaler la disparition (ou la liste se met à jour au rechargement)
```

#### Scénario de garde-fou — enfant rattaché à un compte / référencé ailleurs

```gherkin
Étant donné un enfant "Léa" rattaché à un compte-enfant (Membership.child_id) OU référencé par des Events/Exchanges (lots ultérieurs)
Quand je tente de le supprimer
Alors la suppression est refusée ou requiert un détachement préalable, avec message localisé explicite
  Et aucune donnée orpheline n'est créée (intégrité référentielle préservée)
```

#### Scénario d'autorisation refusée

```gherkin
Étant donné children_notes = view
Quand je tente DELETE /api/children/{id}
Alors 403 "Droit 'children_notes:edit' requis."
```

---

### US-CH-6 — Gérer les notes (lister / ajouter / éditer / supprimer)

**En tant que** membre habilité,
**je veux** lister les notes d'un enfant, en ajouter, et (si je suis l'auteur — politique verrouillée §5.4 #2, §3.7) éditer/supprimer mes notes,
**afin de** partager des informations utiles avec les co-parents.

#### Scénario nominal — listing

```gherkin
Étant donné children_notes = view et un enfant avec 5 notes
Quand je consulte GET /api/children/{id}/notes
Alors je reçois les notes triées par note_date puis created_at décroissants, text déchiffré, auteur résolu (nom + couleur)
  Et un audit "note.accessed" peut être journalisé (accès aux notes = action sensible §5.4)
```

#### Scénario nominal — ajout (idempotent depuis l'outbox mobile)

```gherkin
Étant donné children_notes = edit et l'app mobile hors-ligne ayant mis une note en outbox
Quand la connexion revient et que la note est rejouée avec un Idempotency-Key
Alors le serveur crée la note une seule fois même si la requête est rejouée (clé déjà vue ⇒ même réponse, pas de doublon)
```

#### Scénario d'édition / suppression par l'auteur (si politique = autorisée)

```gherkin
Étant donné que je suis l'auteur d'une note et children_notes = edit
Quand j'édite le texte de ma note
Alors la note est mise à jour (text re-chiffré), updated_at avancé, audit "note.updated" journalisé

Étant donné que je ne suis PAS l'auteur d'une note et que je ne suis pas parent/admin
Quand je tente d'éditer cette note
Alors 403 — seul l'auteur (ou un parent/admin — politique verrouillée §5.4 #2, §3.7) peut éditer/supprimer une note
```

#### Scénario d'autorisation refusée

```gherkin
Étant donné children_notes = none
Quand je tente GET /api/children/{id}/notes
Alors 403 "Droit 'children_notes:view' requis."

Étant donné children_notes = view (lecture seule)
Quand je tente POST /api/children/{id}/notes
Alors 403 "Droit 'children_notes:edit' requis."
```

---

### US-CH-7 — Voir l'avertissement « pas de données de santé »

**En tant que** membre éditant une fiche enfant,
**je veux** un bandeau d'avertissement persistant m'invitant à ne pas saisir de données de santé,
**afin de** respecter le cadre RGPD du produit (aucune donnée de santé).

#### Scénario nominal

```gherkin
Étant donné que j'ouvre l'écran d'édition d'un enfant (création ou modification)
Quand l'écran est affiché
Alors un bandeau d'avertissement localisé "Ne saisissez pas de données de santé..." est visible en permanence (non masquable)
  Et il reste visible pendant la saisie des informations pratiques et des notes
```

#### Scénario — aucun champ médical typé

```gherkin
Étant donné le formulaire d'informations pratiques
Quand je consulte les champs proposés
Alors il n'existe AUCUN champ typé santé (pas de "Allergies", "Médecin", "Traitement", etc. en preset)
  Et seules des paires libres {label, value} sont proposées
```

---

## 3. Algorithme(s)

### 3.1 Calcul de l'âge à partir de la date de naissance (fuseau Europe/Paris)

Objectif : afficher l'âge en années révolues, calculé par rapport à **« maintenant »** dans le fuseau de la famille (par défaut `Europe/Paris`), **sans jamais coder en dur la date** (notamment pas le 23 juin 2026 des maquettes).

```
fonction calculerAge(birthDate: Date|null, timezone = famille.timezone ?? "Europe/Paris"): int|null
  si birthDate est null:
      retourner null            # âge non affiché
  maintenant ← horloge_injectable().now(timezone)    # ClockInterface, jamais "new DateTime()" en dur
  aujourdHui ← maintenant.dateSeulement()             # composantes Y/M/D dans le fuseau
  naissance  ← birthDate.dateSeulement()
  si naissance > aujourdHui:                           # défense : ne devrait pas arriver (validé en amont)
      retourner null            # ou lever une erreur de cohérence
  age ← aujourdHui.year - naissance.year
  si (aujourdHui.month, aujourdHui.day) < (naissance.month, naissance.day):
      age ← age - 1            # anniversaire pas encore passé cette année
  retourner age
```

Notes :
- Utiliser `Symfony\Component\Clock\ClockInterface` (injectable, mockable en test) — jamais d'instanciation directe de l'horloge. Côté Flutter, injecter une `Clock` testable.
- Le calcul se fait **en date locale** (composantes calendaires dans le fuseau famille), pas en différence d'instants UTC, pour éviter les décalages d'un jour autour de minuit et des changements d'heure.
- Cas 29 février : un enfant né le 29/02 a son anniversaire considéré le 29/02 ; les années non bissextiles, l'âge augmente le 1er mars (comportement standard de la comparaison `(month, day)`). À documenter et tester.
- L'âge est **dérivé/affiché**, jamais stocké (sinon il se périme). Complexité O(1).
- Localisation : le rendu textuel (« 8 ans », « 11 mois » si on choisit d'afficher les nourrissons en mois) passe par ICU (pluriels). Décision de format à figer ; minimum requis : années révolues.

### 3.2 Rendu de la liste d'informations pratiques (label/valeur libre)

`Child.info` est un `jsonb` : tableau ordonné d'objets `{ "label": string, "value": string }`.

```
fonction normaliserInfo(items: liste<{label, value}>): liste<{label, value}>
  resultat ← []
  pour chaque item dans items (dans l'ordre fourni):
      label ← trim(item.label)
      value ← trim(item.value)
      si label == "" ET value == "":
          ignorer          # ligne entièrement vide supprimée
      sinon si label == "":
          rejeter 422 "Le libellé de l'information est requis"   # valeur sans label interdite
      sinon:
          resultat.ajouter({ label, value })   # value peut être vide (label informatif seul autorisé ? -> décision: value vide tolérée)
  appliquer limites: longueur(resultat) ≤ MAX_INFO_ITEMS (ex. 50)
                     longueur(label) ≤ 120, longueur(value) ≤ 500
  retourner resultat        # l'ordre du tableau EST l'ordre d'affichage
```

- Aucun preset médical n'est injecté (invariant). Les paires sont 100 % libres.
- L'ordre d'affichage = ordre du tableau persisté (l'utilisateur peut réordonner côté UI → on réécrit le tableau). Pas de tri implicite.
- Échappement HTML systématique au rendu Twig (les valeurs sont du texte libre saisi par l'utilisateur) pour éviter toute injection.
- Validation déclarative côté entité + contrainte applicative (le `jsonb` n'est pas typé par la base).

### 3.3 Contrôle du droit `children_notes` (Voter)

Le Voter `children_notes` est défini au Lot 1 ; le Lot 2 l'**utilise** sur chaque ressource `Child`/`Note`.

**Décision verrouillée (cf. §5.4)** : on distingue **quatre attributs d'action** au lieu de deux, pour séparer explicitement l'édition de **contenu** (notes) de l'édition de **structure** (profil enfant) — sans cette distinction, un `child` avec `children_notes=edit` pourrait créer/supprimer un profil, ce qui **viole** l'invariant « enfant ⇒ jamais d'édition de structure » :

| Attribut | Porte sur | Niveau requis | `child` autorisé ? |
|---|---|---|---|
| `CHILD_VIEW` | voir un profil enfant | `children_notes ≥ view` | oui (si view/edit explicite) |
| `CHILD_MANAGE` | créer / éditer / supprimer un **profil** (structure) | `children_notes = edit` **ET** rôle ≠ `child` | **jamais** (structure interdite) |
| `NOTE_VIEW` | lister / lire les notes | `children_notes ≥ view` | oui (si view/edit explicite) |
| `NOTE_EDIT` | ajouter / éditer / supprimer une note (contenu) | `children_notes = edit` | oui (si edit explicite ; édition/suppression d'une note existante = auteur ou parent, cf. §3.7) |

```
fonction voteChildrenNotes(membre, ressource, attribut ∈ {CHILD_VIEW, CHILD_MANAGE, NOTE_VIEW, NOTE_EDIT}): bool
  # 0. scope famille déjà garanti en amont (X-Family-Id == famille de la ressource), sinon 404
  si membre.famille != ressource.famille:        # défense en profondeur
      retourner DENY (résolu en 404 hors scope)
  # 1. rôle parent => edit forcé sur tous domaines, y compris la structure
  si membre.role == "parent":
      retourner GRANT          # parent : CHILD_VIEW, CHILD_MANAGE, NOTE_VIEW, NOTE_EDIT
  # 2. niveau effectif du domaine children_notes pour ce membre (pour child : défaut none)
  niveau ← membre.droits["children_notes"]        # none|view|edit
  # 3. garde-fou STRUCTURE : un child ne gère JAMAIS un profil, quel que soit son niveau
  si attribut == CHILD_MANAGE ET membre.role == "child":
      retourner DENY            # invariant : enfant => jamais d'édition de structure
  # 4. décision selon l'attribut et le niveau (pour les non-parents)
  selon attribut:
      CHILD_VIEW   -> GRANT si niveau ∈ {view, edit}
      NOTE_VIEW    -> GRANT si niveau ∈ {view, edit}
      CHILD_MANAGE -> GRANT si niveau == edit            # (role child déjà éliminé à l'étape 3)
      NOTE_EDIT    -> GRANT si niveau == edit            # (édition/suppression d'une note existante : voir §3.7)
  retourner DENY par défaut
```

Précisions et invariants :
- **Défaut** : pour un membre `child`, `children_notes` vaut `none` tant qu'un parent n'a pas explicitement élevé le droit. Donc un enfant ne voit pas les notes par défaut.
- **Édition de structure (`CHILD_MANAGE`)** : créer/éditer/supprimer un *profil* enfant relève de la **structure** ; un `child` ne doit **jamais** y accéder, **même si** `children_notes=edit` lui était octroyé. Le garde-fou est désormais **dans le pseudocode lui-même** (étape 3, court-circuit avant toute évaluation de niveau) — l'algorithme ne peut plus retomber sur un `GRANT` d'édition pour un `child`. L'ancienne signature `{VIEW, EDIT}` ne modélisait pas cette séparation et était en contradiction avec l'invariant ; elle est remplacée par les quatre attributs ci-dessus.
- **`NOTE_EDIT` pour un `child`** : un enfant explicitement élevé à `children_notes=edit` peut ajouter/éditer ses **notes** (contenu), mais cette élévation **n'ouvre aucun accès à la structure** (le garde-fou §3.3 étape 3 le bloque sur `CHILD_MANAGE`). Les deux dimensions sont strictement disjointes.
- **Parent** : son `Right.children_notes` stocké est ignoré ; le rôle force `edit` sur les quatre attributs (utilisé aussi dans le calcul du bloc Accès & droits, §3.5).
- Le Voter s'exécute **sur chaque ressource** (chaque `Child`, chaque `Note`), pas une fois pour toutes. Complexité O(1) par ressource (lecture du membership + droit en mémoire après chargement).

### 3.4 Chiffrement / déchiffrement de `Note.text` 🔒

Le texte des notes est chiffré au repos (AES-256-GCM applicatif, enveloppe de clé KMS UE). Le service de (dé)chiffrement est fourni au Lot 0.

```
# À l'écriture (POST/PATCH note):
fonction chiffrerNoteText(clair: string): bytes
  dek ← obtenirDataEncryptionKey()                 # via enveloppe (KEK gérée par KMS)
  iv  ← random(12 octets)                           # nonce unique par chiffrement (jamais réutilisé)
  (cipher, tag) ← AES_256_GCM.encrypt(dek, iv, clair, aad = contexte)  # aad optionnel: id note/child
  retourner encoder(iv || tag || cipher)            # stocké dans Note.text (colonne bytea/texte base64)

# À la lecture (GET / rendu Turbo):
fonction dechiffrerNoteText(stocke: bytes): string
  (iv, tag, cipher) ← decoder(stocke)
  dek ← obtenirDataEncryptionKey()
  retourner AES_256_GCM.decrypt(dek, iv, cipher, tag, aad = contexte)   # échec si tag invalide
```

Invariants :
- La clé n'est **jamais** stockée en base (§5.3). Un `pg_dump` brut ne doit révéler ni le texte des notes, ni les identités (`User.full_name`, etc.).
- IV (nonce) **unique** par message chiffré ; ne jamais réutiliser un couple (clé, IV).
- Le (dé)chiffrement est **transparent** : géré par un type Doctrine custom ou un listener (pré-persist/post-load), de sorte que le code métier manipule du clair. Recommandation : type Doctrine dédié `encrypted_string` réutilisable (déjà utilisé pour `Message.text`, `full_name`, `phone`, `Invitation.email`).
- Le tri et la recherche plein-texte sur `Note.text` sont **impossibles** côté SQL (champ opaque) → tri par `note_date`/`created_at`, pas de filtre serveur sur le contenu.
- Performance : (dé)chiffrement O(n) sur la taille du texte ; négligeable pour des notes courtes.

### 3.5 Calcul du bloc « Accès & droits » (US-CH-2)

Objectif : pour un enfant donné, lister qui peut **voir** et qui peut **éditer** les notes/profil, avec le niveau effectif.

```
fonction calculerAccesEtDroits(enfant): liste<{membre, role, niveauEffectif, estSonProfil}>
  famille ← enfant.famille
  resultat ← []
  pour chaque membership m actif de famille:           # status = active
      niveau ← niveauEffectifChildrenNotes(m)          # cf. ci-dessous
      si niveau == "none":
          continuer        # non listé (pas d'accès)
      estSonProfil ← (m.child_id == enfant.id)         # rattachement compte-enfant (Lot 1)
      resultat.ajouter({ m.user (nom déchiffré), m.role, niveau, estSonProfil, m.display_color })
  trier resultat: edit avant view, puis par rôle (parent, step_parent, child), puis nom
  retourner resultat

fonction niveauEffectifChildrenNotes(m): "none"|"view"|"edit"
  si m.role == "parent":
      retourner "edit"                  # rôle force edit (Right stocké ignoré)
  # tout autre rôle (step_parent, child) : on lit le Right stocké ; défaut "none" si absent.
  # Le défaut est le MÊME pour tous les rôles non-parent (y compris child) : il n'y a
  # donc aucune branche spécifique à child ici (le child n'a aucun privilège implicite).
  retourner m.droits["children_notes"] ?? "none"
```

- Le bloc n'est exposé qu'aux membres ayant déjà `children_notes ≥ view` (sinon 403, cf. US-CH-2).
- Les noms sont issus de `User.full_name` 🔒 (déchiffré au rendu serveur uniquement ; jamais exposé hors scope famille).
- Complexité O(m) où m = nombre de memberships de la famille (petit).
- Ordre de priorité du niveau : rôle `parent` (edit forcé) > `Right.children_notes` stocké. Aucun autre facteur.

### 3.6 Push temps réel d'une note ajoutée (Mercure)

```
# Transaction de création de note (atomique)
1. Vérifier scope X-Family-Id == child.family (sinon 404).
2. Voter NOTE_EDIT sur (membre, child) -> sinon 403.
3. Valider l'entrée (texte non vide, longueurs).
4. Dans UNE transaction Doctrine:
   a. Créer Note { child_id, author_membership_id = membre, text = clair (chiffré au flush), note_date = now(tz).date }
   b. Écrire AuditLog "note.created" (append-only, même transaction logique ou pipeline garanti)
   c. flush()
5. APRÈS commit (post-flush / event listener) :
   a. Rendre le fragment HTML de la note (Twig) -> Turbo Stream "prepend" cible #notes-list-{childId}
   b. Publier sur le topic Mercure /families/{familyId}/notes :
        - payload web : Turbo Stream (text/vnd.turbo-stream.html)
        - payload mobile : JSON { id, childId, author, noteDate, text } (text déchiffré, livré uniquement aux abonnés autorisés)
   c. (Lot 7) émettre l'évènement applicatif "NoteAdded" pour le pipeline notifications e-mail
6. Réponse HTTP 201 au client appelant (idempotent si Idempotency-Key déjà vu -> 200/201 rejouée).
```

Points clés :
- **Ordre** : la publication Mercure intervient **après commit** de la transaction (sinon risque de pousser une note non persistée si rollback). Utiliser un listener `postFlush`/message Messenger.
- **Autorisation des abonnés** : seul un abonné dont le JWT Mercure contient `/families/{familyId}/notes` reçoit le push ; ce topic n'est mis dans les claims que si le membre a `children_notes ≥ view` (cf. US-CH-3). Le contenu déchiffré ne transite que vers des abonnés autorisés (le hub Mercure ne livre qu'aux subscribers dont les claims matchent).
- **Déchiffrement au rendu du fragment live** : à l'étape 5.a/5.b, le texte de la note est **déchiffré côté serveur** pour composer le fragment Turbo Stream (web) et le payload JSON (mobile). Ce déchiffrement peut échouer (KMS injoignable, tag GCM invalide, clé indisponible) **au moment du rendu du fragment**, indépendamment de l'écriture qui, elle, a déjà commité. Comportement requis (cf. cas limite §4.6) :
  - L'échec de déchiffrement **au rendu live** ne doit **jamais** annuler ni rejouer la transaction déjà commitée (la note existe en base, chiffrée correctement) ;
  - Le fragment **n'est pas publié avec un contenu vide ou en erreur brute** : soit le push est **omis** (dégradation silencieuse, la note apparaîtra déchiffrée au prochain chargement via §4.6), soit on pousse un fragment **placeholder** localisé « contenu temporairement indisponible » cohérent avec le rendu GET dégradé (§4.6) ;
  - L'échec est **journalisé techniquement** (log), sans exposer la cause cryptographique à l'utilisateur ni planter le pipeline `postFlush`/Messenger (un échec de push n'invalide pas le commit).
- **Idempotence** : avec `Idempotency-Key` (outbox mobile), une même note rejouée ne crée pas de doublon et ne re-publie pas un second push (ou re-publie un événement idempotent côté client via l'id).
- **Dégradation** : si le hub est indisponible, la note est persistée quand même ; la diffusion live échoue silencieusement (log technique), la donnée reste visible au prochain chargement.

### 3.7 Autorisation d'édition / suppression d'une note (politique verrouillée)

La politique d'édition/suppression d'une note **existante** était laissée ouverte (ancienne §5.4) ; elle est désormais **tranchée** (cf. §5.4 ci-dessous) et formalisée ici en algorithme dédié, distinct du simple `NOTE_EDIT` (qui autorise l'**ajout**). Règle retenue : **append + édition/suppression réservées à l'auteur, plus les parents/admins** ; un beau-parent `edit` **non-auteur** ne peut **pas** modifier la note d'autrui (préservation de l'attribution).

```
fonction peutModifierNoteExistante(membre, note, attribut ∈ {NOTE_UPDATE, NOTE_DELETE}): bool
  # prérequis déjà vérifié en amont : voteChildrenNotes(membre, note, NOTE_EDIT) == GRANT (sinon 403)
  # et scope famille OK (sinon 404)
  si membre.role == "parent":            # parent/admin : edit forcé, peut modérer toute note
      retourner GRANT
  si note.author_membership_id == membre.id:   # l'auteur peut éditer/supprimer SA note
      retourner GRANT
  retourner DENY                          # beau-parent/enfant non-auteur : refusé (403)
```

- **Ajout d'une note** : régi par `NOTE_EDIT` seul (§3.3) — tout membre `children_notes=edit` peut créer une note ; l'auteur est figé à son `membership`.
- **Édition/suppression** : régies par `peutModifierNoteExistante` ci-dessus, **en plus** de `NOTE_EDIT`. Un beau-parent `edit` peut donc créer ses propres notes et éditer/supprimer **ses** notes, mais pas celles d'un autre membre.
- Cette décision **verrouille** le Voter (`NOTE_EDIT` pour l'ajout + contrôle d'auteur pour la mutation) et les routes `PATCH/DELETE /api/notes/{id}` (§6, désormais **exposées**). Toute évolution de cette politique passe par une demande explicite.

### 3.8 Cohérence de `note_date` (vs `birth_date`, vs futur)

`note_date` est une **date** (pas un instant), exprimée en date locale de la famille (défaut `Europe/Paris`), avec défaut = aujourd'hui via horloge injectable (§3.1). Comportement de cohérence (le cdc ne le spécifiait pas — comblé ici) :

```
fonction validerNoteDate(noteDate: Date, child: Child, tz = famille.timezone ?? "Europe/Paris"): void
  aujourdHui ← horloge_injectable().now(tz).dateSeulement()       # jamais "new DateTime()" en dur
  si noteDate > aujourdHui:
      rejeter 422 "La date de la note ne peut pas être dans le futur"
  si child.birth_date != null ET noteDate < child.birth_date:
      # note antidatée AVANT la naissance de l'enfant : incohérent
      rejeter 422 "La date de la note est antérieure à la naissance de l'enfant"
```

- **Note dans le futur** : refusée (422). Une note décrit un fait passé/présent ; `note_date` ne peut excéder « aujourd'hui » (date locale famille). La valeur par défaut (aujourd'hui) est donc toujours valide.
- **Note antidatée avant la naissance** : si `child.birth_date` est connue, une `note_date` strictement antérieure est refusée (422). Si `birth_date` est `null` (profil sans date connue), cette borne basse n'est pas applicable — seule la borne « pas dans le futur » s'applique.
- **Borne basse alternative** : à défaut de `birth_date`, on tolère toute date passée raisonnable (cohérent avec §4.1 « né le … / date très ancienne ») ; aucune date codée en dur.
- Le calcul se fait en **composantes calendaires locales** (comme §3.1), insensible au DST ; `note_date` étant une date pure, pas d'ambiguïté minuit/changement d'heure.

---

## 4. Cas limites (edge cases)

### 4.1 Dates, fuseau, calendrier

- **Date de naissance dans le futur** : rejet 422 (validation), et défense dans `calculerAge` (retourne null si jamais persistée). Comparaison en date locale Europe/Paris.
- **Anniversaire aujourd'hui** : l'enfant passe à l'âge supérieur le jour J (comparaison `(month, day)` incluse). Tester J-1, J, J+1.
- **Né le 29 février** (année bissextile) : âge incrémenté au 1er mars les années non bissextiles. Comportement documenté et testé (pas de plantage le 28/02).
- **Changement d'heure été/hiver (DST)** : le calcul d'âge se fait en composantes calendaires locales, donc insensible au DST. `note_date` est une **date** (pas un instant) → pas d'ambiguïté DST. Si un horodatage instantané est ajouté (created_at), il est stocké en UTC et affiché en Europe/Paris.
- **Date de naissance très ancienne / âge > 25** : pas de borne métier stricte mais l'UI cible des mineurs ; tolérer mais ne pas planter. Borne basse raisonnable (ex. année ≥ 1900) en validation.
- **Date de naissance absente (null)** : autorisé (un profil peut exister sans date connue) → âge non affiché, pas d'erreur.
- **Fuseau famille non standard** : si `Family.timezone` ≠ Europe/Paris, le calcul d'âge suit `Family.timezone`. Ne jamais coder en dur Europe/Paris dans la logique (c'est seulement le **défaut**).
- **`note_date` dans le futur** : refus 422 (« La date de la note ne peut pas être dans le futur »), comparaison en date locale famille via horloge injectable (cf. §3.8). Le défaut « aujourd'hui » reste toujours valide.
- **`note_date` antidatée avant la naissance de l'enfant** : si `child.birth_date` est connue et `note_date < birth_date`, refus 422 (« La date de la note est antérieure à la naissance de l'enfant », cf. §3.8). Si `birth_date` est `null`, cette borne n'est pas applicable (seule la borne « pas dans le futur » joue). Tester : `note_date == birth_date` (accepté), `note_date == birth_date - 1j` (refusé), `note_date == aujourd'hui` (accepté), `note_date == aujourd'hui + 1j` (refusé).
- **Édition de `note_date`** (politique d'édition verrouillée §5.4 #1, §3.7) : la cohérence §3.8 est revalidée à chaque `PATCH /api/notes/{id}` (on ne peut pas faire « glisser » une note dans le futur ou avant la naissance par édition).
- **Date des maquettes (23/06/2026)** : jamais en dur ; horloge injectable partout.

### 4.2 Concurrence / idempotence

- **Deux notes ajoutées simultanément** sur le même enfant : deux INSERT indépendants, aucun conflit (pas d'unicité sur Note). Les deux pushes Mercure arrivent ; l'ordre d'affichage suit `note_date`/`created_at`.
- **Édition concurrente du profil** (`Child`) par deux membres : *last-write-wins* sur les champs simples (cohérent avec §4.4 mobile). Pour `info` (jsonb), le dernier flush remplace l'ensemble du tableau → risque d'écrasement d'une ligne ajoutée par l'autre ; mitigation possible via `version` (verrou optimiste Doctrine) renvoyant 409 si conflit. Recommandation : verrou optimiste sur `Child`.
- **Rejeu outbox mobile** (même note avec `Idempotency-Key`) : création unique ; la 2ᵉ requête renvoie la réponse mémorisée (pas de doublon, pas de 2ᵉ push).
- **Suppression d'un enfant pendant l'ajout d'une note** : la transaction d'ajout échoue (FK manquante) → 404/409 propre ; pas de note orpheline.

### 4.3 Autorisations (RBAC / Voters)

- **Parent avec Right.children_notes = view/none en base** : ignoré, edit forcé (rôle prioritaire). Tester explicitement.
- **Beau-parent children_notes = view** : peut voir profil + notes, ne peut ni créer/éditer/supprimer note, ni gérer le profil → 403 sur les mutations.
- **Beau-parent children_notes = edit** : peut ajouter/éditer ses propres notes ET gérer le profil enfant (création/édition/suppression du profil via `CHILD_MANAGE`) — c'est un non-`child`, autorisé à la structure des enfants (décision verrouillée §5.4 #3 : le profil enfant *est* « structure », mais ouverte aux non-`child` `edit`, conformément au tableau de droits cdc §2.2). En revanche il **ne peut pas** éditer/supprimer la note d'**un autre** membre (§3.7).
- **Enfant children_notes = none (défaut)** : ne voit ni profil ni notes (403/absence). Ne peut jamais gérer un profil (structure interdite aux `child`).
- **Enfant children_notes = view** (élevé par un parent) : voit profil + notes en lecture seule ; aucune mutation.
- **Enfant children_notes = edit** (cas rare, élevé explicitement) : peut ajouter/éditer **ses** notes (`NOTE_EDIT` + auteur, §3.7), mais **jamais** créer/éditer/supprimer un profil (structure). Le Voter distingue `NOTE_EDIT` (autorisé) de `CHILD_MANAGE` (**refusé pour `child`** par le garde-fou §3.3 étape 3, décision verrouillée §5.4 #3).
- **Dernier admin** : non concerné par les notes/enfants directement, mais aucune action du Lot 2 ne doit retirer/rétrograder un admin → invariant préservé par construction (le Lot 2 ne touche pas aux memberships).
- **Membre `status = invited`** (pas encore actif) : exclu du bloc Accès & droits et des autorisations effectives.

### 4.4 Scope famille (X-Family-Id)

- **Header X-Family-Id absent** sur une route scopée : 400/401 (selon convention Lot 1) — jamais de requête métier non scopée.
- **X-Family-Id pointant une famille dont l'utilisateur n'est pas membre** : 403/404 (non révélateur).
- **Enfant d'une autre famille ciblé sous X-Family-Id courant** : 404 (résolution stricte dans le scope du header). L'identifiant d'un enfant d'une autre famille ne doit jamais fuiter.
- **Utilisateur multi-familles** : les enfants listés sont uniquement ceux de la famille active ; un changement de famille (sélecteur) recharge la liste scopée.
- **Note ciblée via son id mais enfant hors scope** : 404.

### 4.5 Valeurs vides / limites

- **Prénom vide / espaces seuls** : 422.
- **first_name très long** : tronqué/refusé selon `maxLength` (ex. 120) → 422 si dépassement.
- **info = tableau vide** : valide (aucune info pratique).
- **info avec ligne label vide + value renseignée** : 422 (valeur orpheline interdite).
- **info avec label renseigné + value vide** : tolérée (label informatif) — décision figée en §3.2.
- **info doublons de label** : tolérés (pas d'unicité sur label, ce sont des paires libres).
- **Très grand nombre d'infos** : borne `MAX_INFO_ITEMS` (ex. 50) → 422 au-delà.
- **Note text vide / espaces** : 422.
- **Note text très longue** : borne `maxLength` (ex. 5000) → 422 ; attention au chiffrement (taille du ciphertext > clair).
- **Couleur invalide** (hors palette / format non hex) : 422 ou normalisation vers la palette §8 (décision : valider format hex `#RRGGBB`).
- **Initiale** : dérivée de `first_name` (première lettre, majuscule, gestion des accents/diacritiques et premier graphème Unicode) ; si first_name commence par un emoji/chiffre, prendre le premier caractère affichable.

### 4.6 Échecs externes

- **Mercure hors-ligne** (web/mobile) : la note est persistée ; le push échoue silencieusement (log) ; donnée visible au rechargement. Pas d'erreur bloquante.
- **KMS / service de chiffrement indisponible (à l'écriture)** : l'écriture d'une note échoue proprement (500 contrôlé, message localisé générique) car `text` ne peut être chiffré ; **aucune écriture en clair** ne doit avoir lieu.
- **Échec de déchiffrement à la lecture GET** : la lecture d'une note échoue si la clé/le service est inaccessible ou le tag GCM invalide → dégradation : afficher « contenu temporairement indisponible » pour la note concernée plutôt que planter la liste entière.
- **Échec de déchiffrement AU MOMENT DU RENDU DU FRAGMENT LIVE (Turbo Stream / SSE, §3.6)** : distinct du GET ci-dessus. La note vient d'être commitée (chiffrée correctement), mais le déchiffrement **côté serveur au rendu du fragment poussé** peut échouer (KMS momentanément injoignable, clé indisponible, tag invalide) entre le commit et la publication Mercure. Comportement requis :
  - le commit **n'est jamais annulé ni rejoué** par cet échec (la note existe, chiffrée) ;
  - le fragment **n'est pas publié avec un texte vide ou une erreur crypto brute** : soit le push est **omis** (la note apparaîtra déchiffrée au prochain chargement, cf. « Mercure hors-ligne »), soit on pousse un **placeholder** localisé « contenu temporairement indisponible » cohérent avec le GET dégradé ci-dessus ;
  - l'échec est **journalisé techniquement** sans exposer la cause cryptographique ni faire échouer le listener `postFlush`/Messenger (un push raté n'invalide pas la transaction). Tester : note créée + KMS indisponible au seul moment du rendu live ⇒ note bien persistée et lisible au rechargement une fois le KMS revenu, aucun fragment corrompu publié.
- **API vacances scolaires** : **non concernée** par ce lot (Lot 3). Ne rien appeler.
- **E-mail** : non concerné en Lot 2 (notifications e-mail = Lot 7).

### 4.7 Conflits offline (mobile)

- **Note créée hors-ligne** : mise en outbox, rejouée avec `Idempotency-Key` à la reconnexion ; statut « en attente de synchro » visible ; autorité serveur.
- **Profil édité hors-ligne par les deux parents** : *last-write-wins* sur champs simples ; pour `info`, le serveur peut renvoyer 409 (verrou optimiste) → l'app recharge l'état canonique et signale le conflit à l'utilisateur.
- **Note créée hors-ligne sur un enfant supprimé entre-temps** : à la synchro, le serveur renvoie 404/409 ; l'app retire la note de l'outbox et informe l'utilisateur.
- **Déchiffrement hors-ligne** : le cache Drift stocke le **clair déchiffré** localement (le mobile a reçu le clair via l'API/SSE) ; protéger le stockage local par le chiffrement de l'OS (Keychain/Keystore) — le chiffrement « au repos » du cdc concerne la base serveur, mais le cache mobile doit aussi être protégé.

### 4.8 Sécurité / RGPD

- **Pas de données de santé** : aucun champ typé santé ; bandeau persistant à l'édition ; presets médicaux du prototype non repris. Tests de non-régression : aucune migration n'introduit de colonne « allergies/médecin/traitement ».
- **Chiffrement au repos** de `Note.text` 🔒 : vérifié par un test qui inspecte la valeur brute en base (doit être opaque, non lisible).
- **Registre des données personnelles** : entrée obligatoire pour `Note.text` (catégorie « contenu », sensibilité 🔒) ; vérifier la couverture de `Child.first_name` (identité d'un mineur), `Child.birth_date`, `Child.info` (contenu libre). Test de cohérence CI : échoue si un champ personnel n'a pas d'entrée.
- **Journalisation `AuditLog`** : « accès aux notes » est explicitement listé par le **cdc §5.4** → journaliser au minimum les écritures (création/édition/suppression de note et de profil) ; l'audit de **lecture** des notes est requis par le cdc §5.4 mais peut être agrégé/échantillonné pour éviter le volume — en gardant l'esprit append-only/immuable.
- **Mineurs** : aucune donnée d'enfant exposée hors de la famille (et l'overlay famille liée ne concerne que l'agenda en lecture seule, Lot 5 — jamais les profils/notes). Les notes/profils ne sortent jamais du scope famille.
- **Anonymisation (préparée pour Lot 8)** : à la suppression d'un compte auteur, les notes deviennent « auteur anonymisé » (préserver le fil), pas effacées ; le Lot 2 pose l'attribut de registre + s'assure que `author_membership_id` peut pointer un membre anonymisé sans casser l'affichage.
- **Injection** : `info` et `Note.text` sont du texte libre → échappement HTML systématique au rendu Twig ; pas d'interprétation Markdown non maîtrisée (ou sanitization stricte si Markdown autorisé).

### 4.9 i18n / localisation

- **Format de l'âge** : pluriel ICU (« 1 an » / « 2 ans »). Aucune chaîne en dur.
- **Bandeau anti-santé** : chaîne externalisée, traduisible.
- **Niveaux d'accès** (« Lecture », « Édition », « Aucun ») : libellés localisés dans le bloc Accès & droits.
- **note_date / dates de profil** : formatées selon la locale (`fr-FR`) et le fuseau (Europe/Paris) ; jamais de format ISO brut affiché à l'utilisateur.
- **Messages d'erreur RFC 7807** : `detail` localisé ; ne pas révéler d'information hors scope (non énumérant). Inclut les nouveaux messages de cohérence `note_date` (§3.8 : « La date de la note ne peut pas être dans le futur », « La date de la note est antérieure à la naissance de l'enfant ») — externalisés en catalogue.
- **Placeholder live « contenu temporairement indisponible »** (§3.6/§4.6, échec de déchiffrement au rendu du fragment poussé) : chaîne externalisée, identique au placeholder du GET dégradé.
- **Ajout d'une langue** : aucun changement de code requis (chaînes externalisées dès ce lot).

---

## 5. Modèle de données touché

> Convention commune (§5.2) : tous les entités ont `id` (UUID), `created_at`, `updated_at`. 🔒 = chiffré au repos (AES-256-GCM applicatif, §5.3).

### 5.1 `Child` (créée par ce lot)

| Champ | Type | Notes |
|---|---|---|
| `id` | UUID | PK |
| `family_id` | UUID FK → Family | **scope famille**, NOT NULL, indexé |
| `first_name` | string (≤120) | **personnel (identité mineur)** — **au registre** (catégorie « identité »). Chiffrement 🔒 **non listé** dans le cdc §5.2 pour `Child` (≠ `User.full_name`). **Décision verrouillée (§5.4 #4) : non chiffré au repos**, inscrit au registre. Tri/recherche serveur conservés. |
| `color` | string (hex `#RRGGBB`) | couleur d'avatar (palette §8) |
| `birth_date` | date (nullable) | → âge calculé (jamais stocké) ; date locale |
| `school_label` | string (libre, nullable) | niveau/établissement, libellé libre |
| `info` | jsonb | liste ordonnée `[{ "label": string, "value": string }]` — **aucun champ médical typé** ; contenu personnel libre → registre |
| `created_at` / `updated_at` | timestamptz | |
| (`version`) | int | **recommandé** : verrou optimiste pour éviter l'écrasement concurrent de `info` |

- Relation : `Family (1) — (N) Child`. Suppression de famille → cascade enfants (selon politique Lot 1).
- Relation `Membership (0..1) — (0..1) Child` via `Membership.child_id` (créée au **Lot 1**) : un profil peut être rattaché à un compte-enfant. Le Lot 2 lit cette relation (bloc Accès & droits), ne la crée pas.
- **Pas de contrainte d'unicité** sur (family_id, first_name) : deux enfants peuvent partager un prénom (jumeaux homonymes improbable mais pas interdit). Décision : pas d'unicité.

### 5.2 `Note` (créée par ce lot)

| Champ | Type | Notes |
|---|---|---|
| `id` | UUID | PK |
| `child_id` | UUID FK → Child | NOT NULL, indexé ; ON DELETE CASCADE |
| `author_membership_id` | UUID FK → Membership | NOT NULL (peut pointer un membre anonymisé après Lot 8) |
| `text` | 🔒 (bytea/text chiffré) | **chiffré au repos** (AES-256-GCM) — catégorie « contenu » au registre |
| `note_date` | date | date de la note (locale famille) ; défaut = aujourd'hui (Europe/Paris) |
| `created_at` / `updated_at` | timestamptz | |

- Relation : `Child (1) — (N) Note` ; `Membership (1) — (N) Note` (auteur).
- Index recommandé : `(child_id, note_date DESC, created_at DESC)` pour le listing (le tri ne porte jamais sur `text`, opaque).
- Pas d'unicité métier sur les notes.

### 5.3 Entités lues mais non modifiées (dépendances Lot 1)

- `Family` (scope, `timezone`).
- `Membership` (`role`, `is_admin`, `child_id`, `display_color`, `status`) — pour autorisation et bloc Accès & droits.
- `Right` (`domain=children_notes`, `level`) — pour le niveau effectif.
- `User` (`full_name` 🔒) — nom de l'auteur/des membres, déchiffré au rendu serveur.
- `AuditLog` — écriture (append-only) des actions sensibles.

### 5.4 Décisions de modélisation **verrouillées** (politique notes & structure)

> Ces décisions étaient laissées ouvertes (elles changeaient le Voter, l'OpenAPI et US-CH-6). Elles sont désormais **tranchées** ci-dessous pour permettre le codage sans ambiguïté résiduelle. Toute remise en cause exige une demande explicite (cf. CLAUDE.md). La DoD §8.5 vérifie que code/OpenAPI/doc reflètent ces choix.

1. **Notes éditables/supprimables (pas append-only)** : une note **peut** être éditée et supprimée — ce n'est **pas** un journal append-only. Conséquence : les routes `PATCH /api/notes/{id}` et `DELETE /api/notes/{id}` **sont exposées** (§6).
2. **Qui peut éditer/supprimer une note existante** : l'**auteur** de la note (sous réserve `children_notes=edit`) **et** les **parents/admins** (edit forcé, rôle de modération). Un beau-parent `edit` **non-auteur** ne peut **pas** éditer/supprimer la note d'autrui (préservation de l'attribution). Formalisé en §3.7 (`peutModifierNoteExistante`). L'**ajout** d'une note reste régi par `NOTE_EDIT` seul.
3. **Profil enfant = structure** : **oui**. La création/édition/suppression d'un profil `Child` relève de la structure → attribut Voter **`CHILD_MANAGE`**, **refusé aux `child`** quel que soit leur niveau `children_notes` (garde-fou dans le pseudocode §3.3, étape 3). Un beau-parent `children_notes=edit` (non-`child`) **peut** gérer un profil (aligné sur le tableau de droits cdc §2.2 qui autorise `step_parent` à `edit`). La lecture du profil reste régie par `CHILD_VIEW` (`children_notes ≥ view`).
4. **Chiffrement de `Child.first_name`** : le cdc §5.2 **ne marque pas** `Child.first_name` 🔒 (≠ `User.full_name`). **Décision verrouillée : non chiffré au repos**, mais **inscrit au registre** des données personnelles comme identité d'un mineur (catégorie « identité »). Justification : alignement strict sur le cdc (qui réserve 🔒 à une liste fermée : `full_name`, `phone`, `Message.text`, `Note.text`, `Invitation.email`) et conservation de la possibilité de tri/recherche serveur sur le prénom. Cette décision est **documentée pour le DPO** mais ne reste pas « ouverte » au moment du codage. (Si le DPO impose le chiffrement ultérieurement, ce sera un changement explicite, hors périmètre Lot 2.)
5. **Exposition du bloc « Accès & droits »** : servi en **sous-ressource dédiée** `GET /api/children/{id}/access` (droit `children_notes ≥ view`), plutôt qu'embarqué dans `GET /api/children/{id}` — choix verrouillé pour rester explicite et auditable (cf. §6).

> Aucune de ces cinq décisions ne reste « à trancher par l'implémenteur » : elles sont fixées ici, reportées dans le Voter (§3.3, §3.7), l'OpenAPI (§6) et la DoD (§8.5). Il ne subsiste donc plus de choix structurant ouvert avant codage.

---

## 6. Endpoints API concernés

> Base : REST/JSON sous `/api`, OpenAPI exporté (§6.1). **Toutes** ces routes sont scopées famille (header `X-Family-Id` validé contre les memberships). Erreurs en **RFC 7807** (`application/problem+json`), messages localisés. `Idempotency-Key` accepté sur les POST (rejeu outbox mobile). Autorisation par **Voter `children_notes`** sur chaque ressource, via les attributs d'action `CHILD_VIEW` / `CHILD_MANAGE` / `NOTE_VIEW` / `NOTE_EDIT` (§3.3) — la mutation d'une note existante ajoutant le contrôle d'auteur §3.7.

| Méthode | Chemin | Droit requis | Description |
|---|---|---|---|
| GET | `/api/families/{id}/children` | `children_notes ≥ view` | Lister les profils enfants de la famille active (scopé) |
| POST | `/api/families/{id}/children` | `children_notes = edit` (non-`child`) | Créer un profil enfant |
| GET | `/api/children/{id}` | `children_notes ≥ view` | Détail d'un profil enfant (résolu dans le scope X-Family-Id) |
| PATCH | `/api/children/{id}` | `children_notes = edit` (non-`child`) | Éditer un profil enfant (prénom, couleur, date, niveau, `info`) |
| DELETE | `/api/children/{id}` | `children_notes = edit` (non-`child`) | Supprimer un profil enfant (cascade notes ; garde-fous §4.2) |
| GET | `/api/children/{id}/access` | `children_notes ≥ view` | Bloc « Accès & droits » (US-CH-2) — sous-ressource dédiée (§5.4 #5) |
| GET | `/api/children/{id}/notes` | `children_notes ≥ view` | Lister les notes (text déchiffré au rendu ; tri date desc) |
| POST | `/api/children/{id}/notes` | `children_notes = edit` (`NOTE_EDIT`) | Ajouter une note (→ chiffrement, audit, push Mercure `/families/{id}/notes`) |
| PATCH | `/api/notes/{id}` | `children_notes = edit` **+ auteur ou parent/admin** (§3.7) | Éditer une note (re-chiffrement ; revalidation cohérence `note_date` §3.8) |
| DELETE | `/api/notes/{id}` | `children_notes = edit` **+ auteur ou parent/admin** (§3.7) | Supprimer une note |

Notes :
- **Routes `/api/notes/{id}` (PATCH/DELETE) : exposées (décision verrouillée §5.4 #1/#2).** Elles ne figurent pas dans l'extrait §6.2 du cdc (qui ne liste que `GET/POST /api/children/{id}/notes`), mais sont **ajoutées** pour couvrir l'édition/suppression par l'auteur ou un parent/admin (US-CH-6). La politique « notes append-only » est **écartée** (§5.4 #1). L'OpenAPI **doit** exposer ces deux routes ; l'autorisation combine `NOTE_EDIT` (§3.3) **et** le contrôle d'auteur (`peutModifierNoteExistante`, §3.7).
- **Bloc « Accès & droits » (US-CH-2) : sous-ressource dédiée `GET /api/children/{id}/access`** (décision verrouillée §5.4 #5), droit `children_notes ≥ view`. Choix retenu plutôt qu'une propriété embarquée dans `GET /api/children/{id}`, pour rester explicite et auditable. À exposer dans l'OpenAPI.
- **Temps réel** : `POST /api/children/{id}/notes` publie sur le topic Mercure `/families/{id}/notes` (Turbo Stream web + JSON mobile) après commit (§3.6).
- Codes : 200/201 (succès), 403 (droit insuffisant), 404 (hors scope / introuvable non révélateur), 422 (validation), 409 (conflit verrou optimiste / rejeu non idempotent divergent).

---

## 7. Invariants & règles métier

Invariants du cdc / `CLAUDE.md` que ce lot **doit** respecter (uniquement les pertinents) :

1. **Scope famille obligatoire** : toute ressource `Child`/`Note` est scopée via `X-Family-Id`, validé contre les memberships. Jamais de requête métier non scopée. Résolution stricte → 404 non révélateur hors scope.
2. **Autorisation par Voters** : Voter `children_notes` (niveaux `none|view|edit`) sur **chaque** ressource (`Child`, `Note`). Parent ⇒ `edit` forcé. Enfant ⇒ `children_notes = none` par défaut et **jamais** d'édition de structure (création/suppression de profil interdite aux `child`).
3. **Pas de données de santé** : aucun champ typé santé créé ; `info` reste paires libres `{label, value}` ; presets médicaux non repris ; **bandeau d'avertissement persistant** sur l'écran d'édition enfant. (Divergence assumée vs maquette.)
4. **Champ chiffré au repos** : `Note.text` 🔒 chiffré AES-256-GCM applicatif ; un dump SQL brut ne révèle pas le contenu. Clé jamais en base. (`Child.first_name` non marqué 🔒 par le cdc mais inscrit au registre comme identité de mineur.)
5. **Registre des données personnelles** : ajout d'une entrée pour `Note.text` (+ vérif `Child.first_name`, `Child.birth_date`, `Child.info`) — attribut déclaratif sur l'entité + mise à jour de `/backend/docs/registre-donnees.md`. Test CI de cohérence vert. Fait partie de la DoD du lot.
6. **Journalisation `AuditLog`** : actions sensibles (accès/écriture des notes, modification de profil) journalisées, append-only, immuable.
7. **i18n** : toutes les chaînes (UI + futures e-mails) externalisées dès le départ, format ICU (pluriel de l'âge, niveaux d'accès). Seule `fr-FR` livrée ; ajout d'une langue sans changement de code. Fuseau par défaut `Europe/Paris`.
8. **Dates non codées en dur** : âge et `note_date` via horloge injectable ; ne jamais coder le 23/06/2026 ni `new DateTime()` direct. Calcul en date locale (fuseau famille).
9. **Mineurs / confidentialité** : aucune donnée d'enfant exposée hors de la famille ; l'overlay famille liée (Lot 5) ne touche jamais profils/notes (uniquement agenda RO).
10. **Dépenses = v2** : sans objet ici, mais ne rien activer côté dépenses depuis ce lot.
11. **Temps réel unifié via Mercure** : Turbo Streams (web) / SSE JSON (mobile) ; abonnement autorisé par claims JWT Mercure filtrés sur le droit `children_notes ≥ view`.
12. **Conformité design system §8** : master-détail pour Enfants (desktop), liste inset / liquid glass (mobile iOS), palette terracotta/teal sur crème, polices Bricolage Grotesque / Hanken Grotesk.

---

## 8. Definition of Done (DoD)

Le Lot 2 est « fait » lorsque **tous** les critères ci-dessous sont remplis (en plus de la DoD générale §9 du cdc) :

### 8.1 Fonctionnel & conformité
- [ ] US-CH-1 à US-CH-7 implémentées et vérifiées (web **et** mobile pour les écrans concernés).
- [ ] Écran Enfants en **master-détail** conforme au design system §8 (desktop : sidebar + master-détail ; mobile : liste inset iOS « liquid glass » + variante Material).
- [ ] **Bandeau anti-données-de-santé** persistant et non masquable sur l'édition enfant (web + mobile).
- [ ] **Aucun champ médical typé** introduit (revue des migrations + test de non-régression).
- [ ] Bloc « Accès & droits » correct (parent edit forcé, beau-parent/enfant selon Right, « c'est son profil » via `child_id`).
- [ ] Âge calculé via horloge injectable, fuseau famille (défaut Europe/Paris), pluriel ICU ; aucune date en dur.

### 8.2 Sécurité, RGPD, données
- [ ] `Note.text` 🔒 **chiffré au repos** : test inspectant la valeur brute en base (opaque) vert.
- [ ] **Registre des données personnelles** mis à jour (attributs déclaratifs sur `Child`/`Note` + `/backend/docs/registre-donnees.md`) ; **test de cohérence CI vert** (aucun champ personnel sans entrée).
- [ ] `AuditLog` branché sur les actions sensibles (notes : accès/création/édition/suppression ; profil : création/édition/suppression), append-only.
- [ ] Scope `X-Family-Id` appliqué et testé (404 non révélateur hors scope).
- [ ] Voter `children_notes` testé unitairement pour chaque combinaison rôle × niveau × **attribut** (`CHILD_VIEW`/`CHILD_MANAGE`/`NOTE_VIEW`/`NOTE_EDIT`, §3.3) : parent edit forcé, enfant `none` par défaut, et **cas critique** `child` + `children_notes=edit` ⇒ `CHILD_MANAGE` **refusé** (structure interdite, garde-fou §3.3 étape 3) alors que `NOTE_EDIT` reste possible.
- [ ] Mutation d'une note existante testée (`peutModifierNoteExistante`, §3.7) : auteur autorisé, parent/admin autorisé, beau-parent `edit` non-auteur **refusé** (403).

### 8.3 Temps réel
- [ ] Note ajoutée → push sur `/families/{id}/notes` après commit ; Turbo Stream (web) injecte la note ; SSE/JSON (mobile) met à jour le cache.
- [ ] Abonnement Mercure filtré par claims (`children_notes ≥ view`) ; pas de fuite hors famille ; dégradation propre si hub indisponible.
- [ ] **Échec de déchiffrement au rendu du fragment live** (§3.6/§4.6) testé : commit non annulé, aucun fragment corrompu/vide publié (push omis ou placeholder localisé), échec journalisé sans planter le listener `postFlush`/Messenger, note lisible au rechargement une fois le KMS revenu.

### 8.4 Tests (verts en CI)
- [ ] **Unitaires** (PHPUnit) : `calculerAge` (bornes : anniversaire J-1/J/J+1, 29/02, null, futur), `validerNoteDate` (§3.8 : `note_date` futur refusé, antidatée avant `birth_date` refusée, `== birth_date` accepté, `birth_date == null` ⇒ borne basse inapplicable), normalisation `info`, Voter `children_notes` (4 attributs), `peutModifierNoteExistante`, chiffrement/déchiffrement `Note.text`, calcul bloc Accès & droits.
- [ ] **Fonctionnels API** (client de test Symfony/API Platform) : CRUD enfant/notes, scope famille, droits (403/404/422/409), idempotence (`Idempotency-Key`), RFC 7807 localisé.
- [ ] **E2E web** (Playwright) : consulter une fiche enfant, ajouter une note et la voir apparaître en temps réel (Turbo Stream), bandeau anti-santé visible, accès refusé pour droit insuffisant ; responsive desktop/mobile.
- [ ] **Mobile** (Flutter) : tests de widgets (fiche enfant, liste notes, bandeau), mappers OpenAPI, intégration offline (note en outbox rejouée avec `Idempotency-Key`).

### 8.5 Qualité, contrat, doc
- [ ] **Migrations** Doctrine fournies (création `child`, `note`, index, FK, éventuel `version`).
- [ ] **OpenAPI à jour** : endpoints enfants/notes (+ `access` et `/api/notes/{id}` selon décisions §5.4/§6) exposés ; **client Dart régénéré** depuis le schéma.
- [ ] **i18n** câblée (fr-FR) : toutes les chaînes UI externalisées, ICU pour l'âge ; catalogue Symfony + ARB Flutter.
- [ ] **`/infra/INSTALL.md`** mis à jour **si** une dépendance change (a priori aucune nouvelle au Lot 2 ; à vérifier).
- [ ] **Analyse statique propre** : PHPStan (niveau élevé) + php-cs-fixer ; `dart analyze` + `dart format`.
- [ ] Décisions structurantes §5.4 (notes éditables/supprimables non append-only ; auteur+parent seuls habilités à muter une note, §3.7 ; profil = structure → `CHILD_MANAGE` refusé aux `child` ; `Child.first_name` non chiffré mais au registre ; bloc « Accès & droits » en sous-ressource `/access`) **vérifiées comme reflétées** dans le code (Voter, entités), l'OpenAPI (routes `PATCH/DELETE /api/notes/{id}` et `GET /api/children/{id}/access` exposées) et ce document — **aucune décision structurante ne reste ouverte avant codage**.
