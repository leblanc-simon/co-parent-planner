# Lot 5 — Familles liées & overlay

> Document de conception détaillée. Source de vérité : `specs/cdc.md` (notamment §2.1, §3.3 US-CUST-5, §3.4 US-AG-4, §4.5, §5.2 `FamilyLink`, §6.2 paramètre `overlay=`) et les invariants verrouillés de `CLAUDE.md`. En cas d'ambiguïté de rendu visuel, les maquettes HTML du bundle Claude Design font foi. Ce document ne remet en cause aucune décision d'architecture verrouillée (cdc §0).

---

## 0. Objectif & périmètre

### 0.1 Objectif

Permettre à un membre **habilité** d'une famille active d'**activer un overlay en lecture seule** affichant les événements d'agenda et l'occupation de garde d'une **famille liée** (typiquement le second foyer recomposé de l'un des parents), afin de coordonner les activités des enfants entre les deux foyers sans jamais modifier ni les données ni la garde de la famille liée.

L'overlay matérialise visuellement la présence d'activités/événements de la famille liée :
- **Couleur dédiée** `#9C6FB0` (token « Famille liée (overlay) » du design system §8) pour distinguer ces éléments de ceux de la famille active.
- **Pastilles d'activité** dans le calendrier (Garde) et la vue agenda, signalant qu'un ou plusieurs événements de la famille liée existent un jour donné.
- **Lecture seule stricte (RO)** : aucune mutation possible sur les données de la famille liée depuis la famille active ; aucun bouton d'édition ; clic sur un élément overlay ouvre un détail RO sans action.

### 0.2 Dans le périmètre

- **Activation / désactivation de l'overlay** par le membre, **par lien de famille**, dans les vues Agenda et Garde (toggle), et persistance de cette préférence (par membre, par lien).
- **Habilitation** : règle déterminant qui peut activer l'overlay (lien `active` + droit `agenda` ≥ `view` dans la famille active ; voir §3.2). L'overlay n'est jamais accessible à un membre sans droit de lecture agenda.
- **Fusion en lecture seule** des **événements d'agenda** de la famille liée dans la vue Agenda de la famille active (`GET /events?overlay=…`), filtrée sur l'intervalle `[from, to]`.
- **Fusion en lecture seule** de l'**occupation de garde** (pastilles d'activité) de la famille liée dans le calendrier de Garde (US-CUST-5), sans jamais altérer les `CustodyDay` de la famille active.
- **Filtrage des champs exposés** via l'overlay : projection minimale (titre dépersonnalisé/horaires/catégorie/jour) ; aucune fuite d'information enfant, de note, de responsable, de description, de message, de profil hors agenda.
- **Couleur `#9C6FB0`** et pastilles appliquées en web (Twig/CSS) et mobile (Flutter).
- Endpoints : extension de `GET /api/families/{id}/events` au paramètre `overlay=` (cdc §6.2) ; lecture des liens via les endpoints de Lot 1 (`/api/families/{id}/links`).
- i18n des libellés overlay (étiquettes « Famille liée », « Activités du second foyer », états vides).

### 0.3 Hors périmètre

- **Création / gestion du lien de famille lui-même** (créer, accepter, supprimer un `FamilyLink`) : c'est le **Lot 1** (cdc §9 « liens entre familles, sans overlay encore »). Le Lot 5 **consomme** un lien `active` mais ne le crée pas.
- **Édition de quoi que ce soit dans la famille liée** : interdit par construction (RO strict).
- **Overlay des Échanges, des Notes, des Enfants, des Dépenses** : hors périmètre. L'overlay est limité à l'**agenda** (et aux **pastilles d'occupation de garde**, dérivées de la garde de la famille liée). Aucune fuite hors agenda (cdc §9 Lot 5 « aucune fuite hors agenda »).
- **Temps réel de l'overlay** : non requis par le cdc pour ce lot (les topics Mercure §4.5 restent scopés par famille). Voir §3.5 pour le comportement attendu (rafraîchissement à la navigation/refresh ; pas d'abonnement croisé poussé). Le Lot 6 traite le temps réel des échanges.
- **Notifications** liées à l'overlay : hors périmètre (Lot 7).
- **Modification de la garde** par l'overlay : explicitement interdit (cdc §2.1 « La garde n'est jamais modifiée par l'overlay »).

### 0.4 Contexte v1 / v2

Les familles liées + overlay sont **v1** (cdc §1.3, ligne « Familles (séparée + unie), multi-famille, **familles liées + overlay** » ✅ v1). Les dépenses restant v2 et masquées, l'overlay ne touche jamais les dépenses. Aucune donnée de santé n'est introduite ni exposée (cdc §1.4.1, invariant verrouillé).

---

## 1. Dépendances

### 1.1 Lots prérequis (avant — bloquants)

| Lot | Raison de la dépendance |
|---|---|
| **Lot 0 — Fondations** | Monorepo, Symfony + API Platform + Doctrine + JWT + Voters (squelette), scope `X-Family-Id`, AssetMapper/Turbo/Stimulus (web), client Flutter généré, harnais de tests, i18n, RGPD Manager (registre). Indispensable. |
| **Lot 1 — Familles, membres, rôles & droits, invitations, liens** | Fournit l'entité `FamilyLink` (création/acceptation/suppression d'un lien, statut `pending`/`active`), le scope `X-Family-Id`, les `Membership`/`Right` et les **Voters par domaine** (dont `agenda` et `custody`). Le Lot 5 **suppose un lien `active` existant** et s'appuie sur le Voter `agenda` pour l'habilitation. Bloquant. |
| **Lot 3 — Garde (moteur + vacances)** | Fournit `CustodyDay` (occupation effective par jour) et le calendrier mensuel (US-CUST-5) sur lequel se greffent les pastilles d'activité de la famille liée. Sans Lot 3, pas de calendrier de garde ni de jours effectifs à surfacer. Bloquant pour la partie Garde de l'overlay. |
| **Lot 4 — Agenda** | Fournit l'entité `Event` (catégories, récurrence, multi-enfants/famille, responsable), la vue agenda groupée par jour et la liste « À venir », ainsi que l'endpoint `GET /api/families/{id}/events?from=&to=&category=&overlay=` dont le Lot 5 active le paramètre `overlay=`. Bloquant pour la partie Agenda de l'overlay. |

> Conséquence : le Lot 5 vient **après** Lots 0, 1, 3 et 4 (cohérent avec l'ordre séquentiel cdc §9). Les Lots 2 (Enfants/notes) et 6+ ne sont pas prérequis mais Lot 2 sera en pratique déjà livré (ordre séquentiel) ; aucune donnée du Lot 2 n'est exposée par l'overlay.

### 1.2 Lots débloqués (après)

| Lot | Apport débloqué |
|---|---|
| **Lot 7 — Notifications e-mail** | Pourrait à terme notifier d'un changement dans la famille liée (hors périmètre v1 du Lot 5, mais la projection overlay est réutilisable). Non bloquant. |
| **Lot 8 — PWA / offline / RGPD Manager** | Le RGPD Manager doit recenser les **familles liées comme destinataires** des données personnelles (cdc §7.1.1 « destinataires (dont familles liées, sous-traitants) »). Le Lot 5 alimente cette obligation : tout champ surfacé via overlay doit avoir son destinataire « famille liée » documenté au registre. L'offline mobile (Drift) devra décider du cache RO de l'overlay. |

> En pratique l'overlay est une **fonctionnalité feuille** côté UX (ne débloque pas d'autre fonctionnalité majeure), mais il **impose une obligation de registre RGPD** consommée plus tard par le Lot 8.

---

## 2. User stories complètes

> Convention IDs : on réutilise les IDs du cdc (US-CUST-5, US-AG-4). Les US complémentaires nécessaires à la couverture du périmètre sont préfixées **US-LINK-** (familles liées / overlay).

### US-AG-4 — Activer l'overlay agenda de la famille liée (lecture seule)

> **En tant que** parent ou beau-parent habilité de la famille active, **je veux** activer l'overlay pour voir les événements de la famille liée en lecture seule, **afin de** coordonner les activités des enfants entre les deux foyers sans risque de modifier l'autre famille.

**Critères d'acceptation**

- **Scénario nominal — activation**
  - **Étant donné** une famille active A liée (`FamilyLink.status = active`) à une famille B, et un membre connecté disposant du droit `agenda ≥ view` sur A,
  - **Quand** il active le toggle « Overlay famille liée » dans la vue Agenda,
  - **Alors** les événements de B sur l'intervalle affiché sont fusionnés en lecture seule, rendus avec la couleur `#9C6FB0`, marqués comme « Famille liée », non éditables, et triés/groupés par jour avec les événements de A.

- **Scénario nominal — désactivation**
  - **Étant donné** l'overlay activé,
  - **Quand** le membre désactive le toggle,
  - **Alors** les événements de B disparaissent de la vue, seuls ceux de A restent, et la préférence (overlay off) est persistée pour ce membre et ce lien.

- **Scénario — lecture seule stricte**
  - **Étant donné** l'overlay activé et un événement de B affiché,
  - **Quand** le membre ouvre le détail de cet événement,
  - **Alors** aucun bouton d'édition/suppression n'est proposé, et toute tentative d'appel `PATCH/DELETE /api/events/{idB}` avec `X-Family-Id: A` renvoie **403** RFC 7807 (`/errors/forbidden`), car l'événement n'appartient pas au scope de la famille active.

- **Scénario — champs filtrés (anti-fuite)**
  - **Étant donné** un événement de B avec enfants concernés, responsable, description et catégorie,
  - **Quand** il est exposé via l'overlay à A,
  - **Alors** seuls les champs de **projection minimale** sont visibles (titre, jour, créneau horaire, catégorie, drapeau « famille liée ») ; **aucun identifiant ni prénom d'enfant**, **aucun responsable**, **aucune description**, **aucune note** de B n'est transmis (voir §3.6).

- **Scénario — autorisation refusée (droit agenda insuffisant)**
  - **Étant donné** un membre dont le droit `agenda = none` sur A (ex. enfant sans accès agenda),
  - **Quand** il tente d'activer l'overlay ou d'appeler `GET /events?overlay=...`,
  - **Alors** le toggle overlay n'est pas proposé (UI) et l'API renvoie **403** (`/errors/forbidden`, `Droit 'agenda:view' requis.`).

- **Scénario — lien inexistant ou non actif**
  - **Étant donné** une famille A **sans** lien `active` (aucun lien, ou lien `pending`),
  - **Quand** un membre habilité ouvre l'agenda,
  - **Alors** le toggle overlay est **absent ou désactivé** (état « aucune famille liée ») et `GET /events?overlay=<id>` renvoie **422** (`/errors/overlay-link-invalid`) sans fuite d'existence (voir §3.2 et §4).

- **Scénario — overlay sur famille liée non scopée à l'utilisateur**
  - **Étant donné** A liée à B, mais l'utilisateur connecté **n'est membre que de A** (pas de B),
  - **Quand** il active l'overlay,
  - **Alors** il voit la **projection minimale** RO de B (autorisée parce que le lien `active` constitue la base de partage), mais ne peut ni naviguer vers B, ni basculer son `X-Family-Id` sur B, ni récupérer plus que la projection.

### US-CUST-5 — Visualiser le calendrier de garde avec pastilles d'activité de la famille liée

> **En tant que** parent ou beau-parent habilité, **je veux** voir, sur le calendrier mensuel de garde, des **pastilles d'activité** indiquant les événements de la famille liée, **afin de** anticiper les jours chargés de l'autre foyer sans que ma garde soit modifiée.

**Critères d'acceptation**

- **Scénario nominal — pastilles**
  - **Étant donné** une famille séparée A liée `active` à B, overlay garde activé, et des événements de B aux dates D1 et D2 dans le mois affiché,
  - **Quand** le membre consulte le calendrier mensuel (US-CUST-5),
  - **Alors** une **pastille** de couleur `#9C6FB0` apparaît sur D1 et D2 (sans recouvrir la couleur du parent attribué), indiquant la présence d'activité de la famille liée, **sans** changer le parent attribué ni la coloration de garde de A.

- **Scénario — garde jamais modifiée (invariant)**
  - **Étant donné** l'overlay garde activé,
  - **Quand** le rendu du calendrier est produit,
  - **Alors** aucun `CustodyDay`, `CustodyException` ni `HolidayRule` de A n'est créé, modifié ou supprimé ; les statistiques de jours par parent de A sont **inchangées** par l'overlay (calculées uniquement sur les `CustodyDay` effectifs de A, cf. invariant priorité garde).

- **Scénario — désactivation**
  - **Étant donné** l'overlay garde activé,
  - **Quand** le membre le désactive,
  - **Alors** les pastilles disparaissent et la préférence est persistée.

- **Scénario — autorisation refusée (droit custody)**
  - **Étant donné** un membre dont le droit `custody = none` sur A,
  - **Quand** il consulte le calendrier de garde,
  - **Alors** il n'a pas accès au calendrier de garde de A (déjà géré par le Voter `custody`), donc aucune pastille overlay n'est rendue.

- **Scénario — famille unie (intact)**
  - **Étant donné** une famille active A de type `intact` (onglet Garde masqué, cdc §2.1),
  - **Quand** un membre ouvre A,
  - **Alors** il n'existe pas de calendrier de garde pour A, donc l'overlay **garde** est sans objet (l'overlay **agenda** reste disponible si A est liée à B).

- **Scénario — détail d'un jour RO**
  - **Étant donné** une pastille d'activité sur D1,
  - **Quand** le membre ouvre le détail du jour (US-CUST-6),
  - **Alors** la section « famille liée » liste les événements de B en projection minimale RO (titre/créneau/catégorie), sans action d'échange ni d'édition applicable à B.

### US-LINK-1 — Activer/désactiver l'overlay et persister la préférence par lien

> **En tant que** membre habilité, **je veux** que mon choix d'activation d'overlay soit mémorisé par lien et par vue, **afin de** retrouver mon affichage préféré à chaque visite, sans le réactiver manuellement.

**Critères d'acceptation**

- **Scénario nominal**
  - **Étant donné** un membre ayant activé l'overlay pour le lien A→B,
  - **Quand** il revient sur l'agenda plus tard (même session ou nouvelle session),
  - **Alors** l'overlay est rétabli automatiquement dans son dernier état (on/off) pour ce lien et cette vue.

- **Scénario — plusieurs liens**
  - **Étant donné** une famille A liée `active` à B **et** à C (deux parents recomposés),
  - **Quand** le membre active l'overlay pour B mais pas pour C,
  - **Alors** seules les activités de B sont fusionnées ; la préférence est indépendante par lien.

- **Scénario — préférence non partagée entre membres**
  - **Étant donné** deux membres M1 et M2 de A,
  - **Quand** M1 active l'overlay,
  - **Alors** l'état d'overlay de M2 n'est pas affecté (préférence **par membre**).

- **Scénario — valeur par défaut**
  - **Étant donné** un membre sans préférence enregistrée pour un lien,
  - **Quand** il ouvre l'agenda,
  - **Alors** l'overlay est **désactivé par défaut** (opt-in explicite), conformément à la minimisation des données.

### US-LINK-2 — Indiquer visuellement la présence et l'origine des données overlay

> **En tant que** membre, **je veux** que les éléments overlay soient clairement identifiés (couleur `#9C6FB0`, libellé « Famille liée », icône/pastille), **afin de** ne jamais confondre les données de mon foyer avec celles du second foyer.

**Critères d'acceptation**

- **Scénario — couleur & libellé**
  - **Étant donné** l'overlay activé,
  - **Quand** un événement de B est rendu,
  - **Alors** il porte la couleur `#9C6FB0`, un libellé/badge localisé « Famille liée » (clé i18n), et n'est pas comptabilisé dans les filtres par catégorie propres à A de façon ambiguë (les filtres catégorie restent appliqués, mais l'origine overlay est visuellement distincte).

- **Scénario — accessibilité (non couleur-seule)**
  - **Étant donné** un utilisateur ne distinguant pas les couleurs,
  - **Quand** un élément overlay est affiché,
  - **Alors** l'origine « famille liée » est aussi signalée par un texte/icône (pas uniquement par la couleur), pour respecter l'accessibilité de base (cdc §4.9 Playwright a11y).

- **Scénario — état vide**
  - **Étant donné** l'overlay activé mais aucun événement de B sur l'intervalle,
  - **Quand** la vue est rendue,
  - **Alors** un message d'état vide localisé (« Aucune activité de la famille liée sur cette période ») est affiché, sans erreur.

### US-LINK-3 — Désactiver l'overlay automatiquement à la rupture du lien

> **En tant que** membre, **je veux** que l'overlay cesse immédiatement d'afficher des données quand le lien de famille n'est plus `active`, **afin de** garantir qu'aucune donnée de l'autre foyer ne reste visible après délien.

**Critères d'acceptation**

- **Scénario — délien (Lot 1)**
  - **Étant donné** l'overlay A→B activé,
  - **Quand** un admin supprime le lien (`DELETE /api/families/{A}/links`, Lot 1) ou le statut passe hors `active`,
  - **Alors** au prochain rendu/refresh, `GET /events?overlay=<B>` renvoie **422** (`/errors/overlay-link-invalid`), la vue n'affiche plus aucun élément de B, et la préférence overlay devient inopérante (toggle désactivé/masqué).

- **Scénario — passage `active` → `pending`**
  - **Étant donné** un lien repassé `pending`,
  - **Quand** l'overlay est demandé,
  - **Alors** comportement identique au délien : aucune donnée surfacée, **422**.

- **Scénario — sécurité après délien**
  - **Étant donné** un cache web/mobile contenant des données overlay,
  - **Quand** le lien n'est plus actif,
  - **Alors** le cache RO overlay de B doit être purgé/ignoré au prochain chargement (voir §3.5 et §4 sécurité/RGPD).

### US-LINK-4 — Refuser toute mutation et toute lecture hors projection via l'overlay

> **En tant que** responsable de la conformité/sécurité, **je veux** que l'overlay soit RO strict et limité à la projection agenda, **afin de** garantir l'absence de fuite de données enfant et l'inviolabilité de la famille liée.

**Critères d'acceptation**

- **Scénario — mutation refusée**
  - **Étant donné** l'overlay A→B,
  - **Quand** un client tente `POST/PATCH/DELETE` sur une ressource de B avec `X-Family-Id: A`,
  - **Alors** la requête est refusée **403** (scope famille : la ressource n'appartient pas à A) — il n'existe aucun chemin d'écriture overlay.

- **Scénario — pas d'accès cross-domaine**
  - **Étant donné** l'overlay agenda A→B,
  - **Quand** un client tente d'obtenir des notes, enfants, échanges ou dépenses de B via un quelconque paramètre overlay,
  - **Alors** aucune route n'expose ces domaines en overlay (le paramètre `overlay=` n'existe que sur `/events`), et toute tentative est **403/404** selon le scope.

- **Scénario — projection minimale vérifiée**
  - **Étant donné** la réponse `GET /events?overlay=<B>`,
  - **Quand** on inspecte les objets overlay,
  - **Alors** ils ne contiennent que les champs de la projection (§3.6) ; un test fonctionnel échoue si un champ hors projection (enfant, responsable, description) apparaît.

---

## 3. Algorithme(s)

> Conventions transverses : tout calcul de dates se fait dans le **fuseau de la famille active** (par défaut `Europe/Paris`, cdc §10) ; aucune date n'est codée en dur (invariant). Stockage en UTC, conversion à l'affichage. Toutes les lectures sont scopées par `X-Family-Id` (famille active = A).

### 3.1 Vue d'ensemble du flux overlay (lecture seule)

```
Client (web Twig/Turbo ou Flutter)
  └─ active toggle overlay pour le lien A→B (vue Agenda ou Garde)
       └─ persiste préférence (membre, lien, vue)   [US-LINK-1]
  └─ GET /api/families/{A}/events?from&to&category&overlay=<B>   (X-Family-Id: A)
       ▼
Backend
  1. Authentifier + résoudre Membership(user, A)              [Lot 0/1]
  2. Voter AGENDA.view sur (Membre, A)  → sinon 403            [§3.2]
  3. Charger événements PROPRES de A sur [from,to]            [Lot 4]
  4. SI overlay=<B> présent :
        a. Valider habilitation overlay(A, B, Membre)         [§3.2] → sinon 422/403
        b. Charger événements de B sur [from,to]              [scope interne B]
        c. PROJETER chaque event de B en OverlayEvent minimal [§3.6]
        d. MARQUER source=linked, color=#9C6FB0, editable=false
  5. FUSIONNER (A complet) + (B projeté) ; trier par début ; grouper par jour [§3.3]
  6. Sérialiser : la branche overlay est read-only, sans liens d'édition
```

Pour la **Garde** (US-CUST-5), le flux est analogue mais le résultat overlay est réduit à des **indicateurs de présence par jour** (pastilles), dérivés des événements de B (et non de la garde de B) — voir §3.4.

### 3.2 Règle d'habilitation (qui peut activer l'overlay)

L'overlay n'est lisible que si **toutes** les conditions sont réunies. Pseudo-code :

```
function canUseOverlay(viewer: Membership(A), familyA, familyB_id) -> bool|error:
    # 1. Scope famille actif validé : X-Family-Id == A et viewer ∈ memberships(A) actifs
    if not isActiveMember(viewer, familyA): return 403  # scope famille (invariant)

    # 2. Droit de lecture agenda dans A (Voter)
    if not Voter.AGENDA.allows(viewer, view): return 403  # 'agenda:view' requis

    # 3. Il existe un FamilyLink ACTIF entre A et familyB_id (bidirectionnel logique)
    link = findActiveLink(familyA.id, familyB_id)
    if link is null or link.status != 'active':
        return 422  # /errors/overlay-link-invalid (sans révéler l'existence de B)

    return true
```

Notes :
- **Rôle parent** : `agenda` forcé `edit` ⇒ `view` satisfait, donc parent toujours habilité (si lien actif).
- **Beau-parent** : selon `Right(agenda)` accordé (`view`/`edit`/`none`). `none` ⇒ refus.
- **Enfant** : `agenda` ∈ `{view, none}` ; `view` ⇒ peut lire l'overlay (RO), `none` ⇒ refus. L'enfant n'a **jamais** d'accès `expenses` ni d'édition de structure (invariant) — sans objet ici car l'overlay est RO.
- **Admin** : l'overlay ne requiert pas `family_settings` (la **gestion** du lien le requiert, mais c'est le Lot 1). Un membre non admin habilité agenda peut **activer l'affichage** overlay.
- **Le lien est logiquement bidirectionnel** (`FamilyLink` : `family_id`, `linked_family_id`) : depuis A on cible B ; depuis B on ciblerait A. La recherche de lien actif accepte l'orientation (A→B ou B→A) tant que `status = active`.

### 3.3 Fusion en lecture seule des événements (Agenda)

Étapes numérotées (complexité indiquée ; `nA`, `nB` = nombre d'événements de A et B sur l'intervalle) :

1. **Borne temporelle** : normaliser `[from, to]` ; rejeter `from > to` (**422**, voir §4) ; appliquer le fuseau de A pour la conversion d'affichage.
2. **Charger A** : récupérer les événements **propres** de A (matérialisés ou occurrences récurrentes calculées sur l'intervalle, conformément à l'implémentation Lot 4). Coût `O(nA)` + génération occurrences.
3. **Si overlay actif** :
   a. Valider l'habilitation (§3.2).
   b. **Charger B** : récupérer les événements de B sur le **même** intervalle, en appliquant le **fuseau de B** pour le calcul des occurrences récurrentes de B (chaque famille a son propre `timezone`), puis convertir à l'affichage dans le fuseau de A. Coût `O(nB)`.
   c. **Projeter** chaque événement de B → `OverlayEvent` minimal (§3.6).
4. **Fusionner & ordonner** : concaténer `A` (objets complets, éditables selon droit) + `Bproj` (objets RO). Trier par `startsAt` croissant, puis grouper par **jour calendaire dans le fuseau de A**. Tri stable `O((nA+nB) log(nA+nB))`.
5. **Marquage** : chaque `OverlayEvent` porte `source = "linked"`, `color = "#9C6FB0"`, `editable = false`, `linkedFamilyLabel` (libellé court non identifiant, ex. « Famille liée »).
6. **Filtre catégorie** : si `category=` est fourni, il s'applique **aux deux** ensembles (A et overlay) sur la base de la catégorie projetée. Les éléments overlay restent visuellement distincts (couleur/badge).
7. **Sérialisation** : la réponse expose deux natures d'objets distinguables par `source` ; les objets overlay **n'embarquent ni `@id` mutable, ni liens HATEOAS d'édition** (pas d'opération `PATCH/DELETE` exposée).

**Idempotence / pureté** : l'opération est **purement lecture** ; elle ne crée/modifie aucune entité (ni de A ni de B). Rejouer la même requête donne le même résultat (sous réserve d'évolution des données sources). Aucune transaction d'écriture n'est ouverte.

### 3.4 Pastilles d'activité de garde (US-CUST-5)

Les pastilles n'affichent **pas** la garde de B (qui n'est pas pertinente pour A et ne doit pas fuir) : elles signalent la **présence d'événements** de B un jour donné, pour que l'autre foyer sache qu'il s'y passe quelque chose. Pseudo-code :

```
function overlayBadgesForMonth(A, B_id, monthRange, viewer):
    if not canUseOverlay(viewer, A, B_id): return refus(403|422)
    eventsB = projectedEventsOfB_on(monthRange)   # §3.3 étape 3
    badges = map vide  # date -> { count, color:#9C6FB0 }
    for ev in eventsB:
        for day in daysSpanned(ev, timezone=A.timezone):
            badges[day].count += 1
    return badges   # consommé par le rendu calendrier, par-dessus la couleur du parent
```

Règles :
- La pastille **ne remplace pas** la couleur du `CustodyDay` (parent A/B attribué) : elle se superpose (petit point `#9C6FB0`), conformément à « pastilles d'activité, couleur dédiée » (cdc §2.1).
- **La garde de A n'est jamais touchée** : aucune écriture, statistiques inchangées (invariant priorité garde).
- Les jours sont calculés dans le **fuseau de A** (un événement de B à 23h30 heure de B peut tomber un autre jour calendaire ; on rapporte au jour d'affichage de A).
- Sur **famille unie** (A `intact`), pas de calendrier garde → pas de pastilles garde (mais overlay agenda possible).

### 3.5 Persistance de la préférence & cycle de vie

- **Stockage** : préférence d'activation par `(membership_id, family_link_id, view)` où `view ∈ {agenda, custody}`. Implémentation recommandée : entité légère `OverlayPreference` (voir §5) **ou** entrée dans `Membership.settings`/`Family.settings` jsonb. Choix verrouillé au Lot 5 : entité dédiée `OverlayPreference` (testable, indexable, et évite de surcharger les jsonb existants).
- **Défaut** : absent ⇒ overlay **off** (opt-in).
- **Invalidation** : si le `FamilyLink` cible n'est plus `active`, la préférence devient **inopérante** (le rendu ignore et le toggle est masqué) ; on ne supprime pas forcément l'enregistrement (réactivable si le lien redevient actif), mais l'API ne renvoie aucune donnée (**422**).
- **Temps réel** : pas d'abonnement Mercure croisé entre familles (les topics §4.5 restent scopés). Le web rafraîchit l'overlay à la navigation Turbo / refresh ; le mobile au pull-to-refresh ou à la réouverture de l'écran. Un changement d'événement dans B **n'est pas poussé** à A en v1 du Lot 5 (documenté, non bloquant — cdc §9 Lot 5 ne mentionne pas le temps réel overlay).
- **Cache (web/mobile)** : le cache RO overlay est étiqueté par `family_link_id` + `status`. À l'invalidation du lien, le cache est ignoré/purgé (voir §4 sécurité).

### 3.6 Filtrage des champs exposés (projection minimale, anti-fuite)

La projection est le **point de sécurité central** du lot. Un événement de B est réduit à un `OverlayEvent` ne contenant **que** :

| Champ exposé | Origine | Justification |
|---|---|---|
| `startsAt`, `endsAt`, `allDay` | `Event` de B | Nécessaire pour positionner sur l'agenda/calendrier de A. |
| `category` | `Event.category` de B | Permet la coloration de catégorie ET le filtre `category=`. **Mais** la couleur d'origine overlay `#9C6FB0` prime visuellement (badge « famille liée »). |
| `title` | `Event.title` de B | Titre libre. ⚠️ Voir réserve ci-dessous. |
| `source = "linked"`, `color = "#9C6FB0"`, `editable = false` | calculé | Marquage overlay. |
| `linkedFamilyLabel` | libellé court non identifiant | Affichage « Famille liée » (pas le nom réel de B par défaut). |

**Champs explicitement NON exposés** (anti-fuite, RO strict) :
- `id` mutable / `@id` éditable, `seriesId`, liens HATEOAS d'écriture.
- **`childIds` / enfants concernés / prénoms / `whole_family`** de B — interdit (aucune donnée d'enfant hors famille, cdc §7 « aucune donnée d'enfant exposée hors de la famille (et hors famille liée en RO sur l'agenda uniquement) » : seul l'**agenda** est partagé, **pas l'identité des enfants**).
- **`responsibleMembershipId`** / identité du responsable de B.
- **`description`** (texte libre potentiellement personnel).
- **`location`** : exposé **uniquement si** une décision produit le veut ; par défaut **masqué** en projection minimale (un lieu peut être personnel/identifiant). Choix verrouillé Lot 5 : `location` **non exposé** par défaut (minimisation). À réévaluer si UX l'exige, via décision explicite et entrée de registre.
- Toute donnée de notes, messages, dépenses, profils.

**Réserve sur `title`** : le titre étant un champ libre saisi par B, il peut contenir des informations personnelles. La projection l'expose car c'est le minimum utile pour « coordonner les activités », mais :
- aucune transformation/déchiffrement de champ 🔒 n'est faite (les champs 🔒 — `Message.text`, `Note.text`, etc. — ne font pas partie de l'agenda et ne sont jamais touchés) ;
- le titre est exposé tel quel (non chiffré au repos dans le modèle §5.2), conformément au partage agenda RO assumé.

**Garantie testable** : un test fonctionnel sérialise `GET /events?overlay=<B>` et **asserte l'absence** des clés `childIds`, `responsibleMembershipId`, `description`, `location`, `seriesId` et de tout `@id` mutable sur les objets `source = "linked"`.

---

## 4. Cas limites (edge cases)

### 4.1 Dates, fuseaux, calendrier

- **Intervalle inversé** (`from > to`) : **422** RFC 7807, aucun chargement.
- **Intervalle vide** (`from == to`, même instant) : renvoyer un ensemble vide cohérent (pas d'erreur).
- **Intervalle très large** (> horizon raisonnable, ex. > 18 mois) : borner côté serveur à un maximum configurable et/ou paginer ; éviter une explosion d'occurrences récurrentes de A et B. Documenter la borne dans l'OpenAPI.
- **Fuseaux distincts A vs B** : A et B peuvent avoir des `timezone` différents (champ `Family.timezone`). Les occurrences de B sont calculées dans le fuseau de B, puis **rapportées au jour calendaire du fuseau de A** pour le groupement et les pastilles. Test dédié avec A=`Europe/Paris`, B=`Europe/Paris` (cas par défaut) et un cas A≠B.
- **Changement d'heure été/hiver (DST)** : un événement de B le dimanche de bascule (mars/octobre) ne doit pas glisser de jour à l'affichage ; les calculs reposent sur des dates conscientes du fuseau (jamais d'arithmétique naïve sur timestamps). Test sur la nuit de changement d'heure.
- **Année bissextile** : 29 février pris en compte par le calcul calendaire standard ; aucune date codée en dur (notamment pas le 23 juin 2026, invariant). Test sur un février bissextile.
- **Événement « toute la journée » (`all_day`)** de B : la pastille couvre le(s) jour(s) entier(s) dans le fuseau de A ; un all-day chevauchant minuit reste sur ses jours nominaux.
- **Événement multi-jours** de B : pastille sur chaque jour couvert ; comptage par jour (§3.4).

### 4.2 Concurrence & idempotence

- **Lecture pure** : aucune écriture ⇒ pas de risque de double effet ; rejouer `GET ...overlay=` est idempotent par nature.
- **Toggle concurrent** (deux onglets/appareils du même membre activent/désactivent) : la persistance `OverlayPreference` applique *last-write-wins* (champ booléen simple, cohérent avec la stratégie offline cdc §4.4). Aucune corruption possible.
- **Modification de B pendant la lecture de A** : la réponse reflète l'état au moment de la requête ; pas de verrou ; cohérence éventuelle acceptée (pas de temps réel overlay en v1, §3.5).
- **Délien concurrent** (admin de A/B supprime le lien pendant que M consulte l'overlay) : la requête en cours peut renvoyer encore des données (lien lu `active` au début) mais la **suivante** renvoie **422** ; le cache est invalidé (§4.7).

### 4.3 Autorisations (parent / beau-parent / enfant / admin / dernier admin)

- **Parent** : `agenda` forcé `edit` ⇒ overlay autorisé si lien actif. Ne peut **jamais** éditer B via overlay (RO strict, **403** sur mutation).
- **Beau-parent `agenda=view`** : overlay RO autorisé. `agenda=edit` : peut éditer **A** mais **pas** les objets overlay de B (éditables=false ; **403** sur B).
- **Beau-parent `agenda=none`** : pas d'agenda du tout ⇒ pas d'overlay ; toggle masqué ; API **403**.
- **Enfant `agenda=view`** : overlay RO autorisé (lecture seule par essence). `agenda=none` : refus **403**. L'enfant **n'a jamais** d'accès dépenses ni d'édition de structure (invariant) — non concerné ici.
- **Admin** : la **gestion du lien** (créer/supprimer) requiert `family_settings` (Lot 1) ; l'**activation de l'overlay** ne requiert que `agenda:view`. Un admin sans droit agenda (cas théorique) ne verrait pas l'overlay.
- **Dernier admin protégé** : invariant respecté côté Lot 1 (un délien par suppression du dernier admin n'est pas un cas du Lot 5) ; le Lot 5 ne retire/rétrograde aucun admin. Si un changement d'admin/délien intervient (Lot 1), le Lot 5 réagit en invalidant l'overlay (US-LINK-3).
- **Membre de B uniquement** : ne voit pas A via A (il faut être membre de A pour ouvrir A). L'overlay est toujours « vu depuis la famille active dont on est membre ».

### 4.4 Scope famille (`X-Family-Id`)

- **`X-Family-Id` absent/incohérent** : la ressource scopée est rejetée (**400/403** selon convention Lot 0/1) avant tout traitement overlay.
- **`X-Family-Id` = B (la famille liée)** : alors c'est B qui devient la famille active ; l'overlay viserait A. Symétrie correcte ; aucune fuite (chaque côté ne voit que la projection de l'autre).
- **`overlay=<id>` pointant une famille NON liée** (`id` quelconque ou non lié) : **422** `/errors/overlay-link-invalid`, **sans** révéler si `id` existe (réponse uniforme pour « non lié » et « inexistant »), conformément à la non-énumération (cdc §3.1, principe étendu à l'overlay).
- **`overlay=<id>` = la famille active elle-même (A)** : **422** (un overlay sur soi-même n'a pas de sens) ; ne pas dédoubler les événements de A.
- **Plusieurs `overlay=`** (multi-valeurs, si A liée à B et C) : accepter une liste `overlay=B,C` (ou répétition du paramètre) ; valider chaque lien indépendamment ; ignorer (avec avertissement) les non valides plutôt que tout rejeter, **ou** rejeter en bloc — choix verrouillé Lot 5 : **valider chaque id ; ignorer silencieusement les non-actifs** et renvoyer uniquement les valides (l'UI ne propose de toute façon que les liens actifs). Documenter dans l'OpenAPI.

### 4.5 Valeurs vides / limites

- **Aucun lien actif** : toggle masqué, `GET ...overlay=` → **422**.
- **Lien actif mais B n'a aucun événement** sur l'intervalle : réponse overlay vide + message d'état vide localisé (US-LINK-2).
- **B sans `Event` du tout / B vide** : idem, vide propre.
- **B = famille `intact`** : possède un agenda (l'agenda existe pour tous les types) ⇒ overlay agenda OK ; pas de garde de B (mais on n'expose pas la garde de B de toute façon).
- **Titre vide / null côté B** : projeter un placeholder neutre localisé (« Activité ») plutôt que `null`.
- **Catégorie inconnue/obsolète** côté B : retomber sur `autre` pour le filtre/couleur de catégorie (la couleur overlay `#9C6FB0` prime visuellement).

### 4.6 Échecs externes (e-mail, API vacances, Mercure hors-ligne)

- **API vacances scolaires** : sans objet pour le Lot 5 (l'overlay n'importe pas de vacances). Aucune dépendance à `SchoolHolidaysProvider`.
- **E-mail** : sans objet (pas de notification overlay en v1).
- **Mercure hors-ligne** : sans impact direct (pas de push overlay) ; les vues temps réel propres à A (Lot 6) ne sont pas couplées à l'overlay. Si Mercure est indisponible, l'overlay (lecture HTTP) fonctionne quand même.
- **Indisponibilité du backend pendant un toggle** : l'UI signale un échec localisé et conserve l'état précédent ; pas de perte de données (préférence non persistée tant que l'appel échoue, ou mise en outbox côté mobile).

### 4.7 Conflits offline (mobile, cdc §4.4)

- **Cache RO overlay** : le mobile peut mettre en cache la projection overlay (Drift) **étiquetée** par `family_link_id` + dernier `status` connu. À la reconnexion, revalider le lien : si plus `active`, **purger** le cache overlay de ce lien et masquer le toggle.
- **Toggle hors-ligne** : l'activation/désactivation est une préférence locale ; elle peut être appliquée immédiatement à l'affichage du cache, puis synchronisée (outbox) avec `Idempotency-Key`. Comme c'est une préférence (LWW), pas de conflit serveur bloquant.
- **Lecture overlay hors-ligne** : afficher les données overlay en cache avec un marqueur « hors-ligne / dernière synchro », sans laisser croire qu'elles sont temps réel.
- **Aucune écriture overlay** : il n'existe pas de mutation overlay ⇒ pas de conflit d'écriture possible sur les données de B (cohérent avec RO strict).

### 4.8 Sécurité / RGPD

- **Anti-fuite enfant** : aucune donnée d'enfant (id/prénom/couleur/notes) de B n'est exposée (cdc §7). Test asserté (§3.6).
- **Non-énumération de familles** : `overlay=<id>` non lié renvoie une erreur **uniforme** (422) qui ne distingue pas « inexistant » de « non lié », pour ne pas permettre de sonder l'existence d'autres familles.
- **Champs chiffrés 🔒 jamais touchés** : `full_name`, `phone`, `Message.text`, `Note.text`, `Invitation.email` ne font pas partie de l'agenda et ne sont **jamais** déchiffrés/exposés par l'overlay. Un dump SQL reste sans révélation (invariant chiffrement).
- **Registre des données personnelles** : l'overlay introduit la **famille liée comme destinataire** des champs d'événement surfacés (`title`, `category`, horaires). Obligation : **entrée(s) de registre** documentant ce flux de destinataire (cdc §7.1.1, invariant registre) + mise à jour de `/backend/docs/registre-donnees.md`. Le test de cohérence CI doit rester vert.
- **Purge cache après délien** : à la rupture du lien, toute donnée overlay en cache (web SW / mobile Drift) doit être invalidée pour ne pas survivre au consentement (US-LINK-3 / §4.7).
- **Pas de données de santé** : aucun champ santé créé ni exposé (invariant). La catégorie agenda `sante` reste un simple libellé de catégorie, jamais une donnée de santé typée ; sa présence en overlay n'expose qu'un libellé de catégorie, pas un contenu médical.
- **AuditLog** : l'accès overlay est une **lecture**. Le cdc journalise « accès aux notes » et actions sensibles (§5.4) ; l'accès overlay agenda n'est pas listé explicitement comme sensible. Choix verrouillé Lot 5 : **ne pas** journaliser chaque lecture overlay (volume), mais **journaliser l'activation/désactivation de l'overlay** (changement de préférence par membre) comme événement d'audit léger, optionnel et non bloquant. La **création/suppression du lien** est journalisée par le Lot 1.

### 4.9 i18n / localisation

- **Toutes les chaînes** overlay externalisées (ICU) : « Famille liée », « Activités du second foyer », « Aucune activité de la famille liée sur cette période », libellés du toggle, titres de section, badge a11y. Seule `fr-FR` livrée, mais ajout de langue **sans code** (invariant i18n).
- **Dates/heures localisées** dans le fuseau de A (`Europe/Paris` par défaut), format ICU.
- **Pluriels** : « 1 activité » / « N activités » de la famille liée (ICU plural).
- **Placeholder de titre vide** localisé (« Activité »).
- **Aucune date codée en dur** (pas de 23 juin 2026) : l'« aujourd'hui » et le mois affiché viennent du contexte runtime.

---

## 5. Modèle de données touché

> Rappel : tous les entités ont `id` (UUID), `created_at`, `updated_at`. Champs 🔒 = chiffrés au repos (§5.3 cdc). **Le Lot 5 n'introduit aucun nouveau champ personnel sensible 🔒.**

### 5.1 Entités existantes consommées (non modifiées par le Lot 5)

- **`FamilyLink`** (cdc §5.2) — **lu** : `family_id`, `linked_family_id`, `status` (`pending`|`active`), `created_by`. Le Lot 5 lit le statut `active` (orientation A→B ou B→A) pour autoriser l'overlay. **Aucune modification de schéma** ici (créé au Lot 1). Lien bidirectionnel logique.
- **`Event`** (cdc §5.2) — **lu en projection RO** : seuls `starts_at`, `ends_at`, `all_day`, `category`, `title` sont projetés (§3.6). Les champs `responsible_membership_id`, relation `event_child`/`whole_family`, `description`, `location`, `series_id`, `recurrence` **ne sont pas exposés** via l'overlay. Aucune écriture.
- **`CustodyDay`** (cdc §5.2) — **lu** pour le rendu du calendrier de A uniquement ; l'overlay ne lit **pas** la garde de B (les pastilles dérivent des `Event` de B, §3.4). **Jamais modifié** par l'overlay (invariant priorité garde).
- **`Membership`** / **`Right`** — lus pour l'habilitation (Voter `agenda`/`custody`).
- **`Family`** — lu pour `timezone` de A et de B (calcul des occurrences/jours).

### 5.2 Entité ajoutée par le Lot 5

- **`OverlayPreference`** (nouvelle) — persiste l'opt-in d'affichage de l'overlay par membre, par lien et par vue.
  - `id` (UUID), `created_at`, `updated_at`.
  - `membership_id` (FK → `Membership`, **scope famille active** garanti par le membership lui-même).
  - `family_link_id` (FK → `FamilyLink`).
  - `view` (enum : `agenda` | `custody`).
  - `enabled` (bool, défaut `false`).
  - **Unicité** : `(membership_id, family_link_id, view)` unique (une préférence par triplet).
  - **Relations** : `OverlayPreference` *N–1* `Membership` ; *N–1* `FamilyLink`.
  - **Données personnelles** : ce sont des **préférences d'affichage** (donnée technique de paramétrage), non sensibles, **non 🔒**. Ajouter néanmoins une **entrée de registre** (catégorie « technique/préférence ») par cohérence avec l'obligation registre, et déclarer que `OverlayPreference` n'introduit pas de champ identité/contenu.

> Alternative envisagée puis écartée : stocker l'opt-in dans `Membership.settings` (jsonb). Écartée pour la testabilité, l'unicité explicite et l'indexation des requêtes. Décision verrouillée Lot 5 : entité dédiée `OverlayPreference`.

### 5.3 Objet de transport (non persistant)

- **`OverlayEvent`** — DTO de projection (read model), **non** une entité Doctrine, sans table. Champs exposés : `startsAt`, `endsAt`, `allDay`, `category`, `title`, `source = "linked"`, `color = "#9C6FB0"`, `editable = false`, `linkedFamilyLabel`. Sérialisé en lecture seule (aucun groupe de sérialisation d'écriture).

### 5.4 Contraintes & migrations

- Migration Doctrine : création de la table `overlay_preference` avec contrainte d'unicité `(membership_id, family_link_id, view)` et FKs `ON DELETE CASCADE` (si le membership ou le lien disparaît, la préférence disparaît).
- Index sur `(membership_id, view)` pour la lecture rapide à l'ouverture d'une vue.
- Aucune migration de modification sur `FamilyLink`, `Event`, `CustodyDay`.

---

## 6. Endpoints API concernés

> Conventions transverses (cdc §6.1) : REST/JSON sous `/api` ; `Authorization: Bearer <jwt>` ; **`X-Family-Id`** obligatoire sur les ressources scopées (validé contre les memberships) ; `Accept-Language` pour la locale ; erreurs **RFC 7807** (`application/problem+json`) localisées ; **`Idempotency-Key`** accepté sur POST issus de l'outbox (pertinent pour la préférence overlay côté mobile). OpenAPI tenu à jour (DoD).

### 6.1 Endpoint étendu par le Lot 5

| Méthode | Chemin | Droit requis (Voter) | Description |
|---|---|---|---|
| GET | `/api/families/{id}/events?from=&to=&category=&overlay=` | `agenda:view` | Liste des événements de la famille `{id}` (active) sur `[from,to]`. **`overlay=<linkedFamilyId>`** (répétable / liste) inclut, en **lecture seule**, la **projection minimale** des événements des familles liées **actives** désignées, marqués `source=linked`, `color=#9C6FB0`. Champs filtrés (§3.6). Lien non actif/inexistant ⇒ **422** uniforme. |

> C'est l'endpoint normatif du cdc §6.2 (« overlay = inclut famille liée, RO »). Le Lot 4 l'aura déjà introduit ; le Lot 5 **active et sécurise** le paramètre `overlay=`.

### 6.2 Endpoints de préférence overlay (ajoutés par le Lot 5)

| Méthode | Chemin | Droit requis | Description |
|---|---|---|---|
| GET | `/api/families/{id}/overlay-preferences` | `agenda:view` (famille active) | Liste les préférences overlay du **membre courant** pour la famille active (par lien et par vue). |
| PUT | `/api/families/{id}/overlay-preferences` | `agenda:view` | Met à jour l'opt-in du membre courant : `{ familyLinkId, view, enabled }`. Idempotent (upsert sur `(membership, link, view)`). Accepte `Idempotency-Key`. Valide que le lien est `active` (sinon **422**). |

> Ces endpoints sont scopés au **membre courant** uniquement (un membre ne lit/écrit jamais la préférence d'un autre — §US-LINK-1). Le scope famille est imposé par `X-Family-Id = {id}`.

### 6.3 Endpoints de garde concernés (lecture, pastilles)

| Méthode | Chemin | Droit requis | Description |
|---|---|---|---|
| GET | `/api/families/{id}/custody/days?from=&to=` | `custody:view` | Jours effectifs de garde de la famille active (Lot 3, **non modifié**). Le rendu calendrier (web/mobile) compose par-dessus les **pastilles d'activité** issues de `GET /events?overlay=…` ; il n'existe **pas** d'endpoint qui exposerait la garde de B. |

> Aucun nouvel endpoint « custody overlay » : les pastilles dérivent des événements projetés (§3.4). Cela garantit que **la garde de B ne fuit jamais**.

### 6.4 Endpoints explicitement NON exposés en overlay (anti-fuite)

Aucun paramètre `overlay=` n'est ajouté à `/children`, `/children/{id}/notes`, `/exchanges`, `/expenses`, `/custody/days`, `/custody/stats`. Toute tentative d'accès à une ressource de B avec `X-Family-Id` de A est **403/404** (scope famille). Réponses d'erreur conformes RFC 7807 :

```http
403 application/problem+json
{ "type":"/errors/forbidden", "title":"Action non autorisée",
  "detail":"Droit 'agenda:view' requis." }

422 application/problem+json
{ "type":"/errors/overlay-link-invalid", "title":"Overlay indisponible",
  "detail":"Aucune famille liée active pour ce paramètre." }
```

---

## 7. Invariants & règles métier

Invariants du cdc / `CLAUDE.md` **pertinents pour ce lot** et la manière dont le Lot 5 les respecte :

1. **Scope famille obligatoire (`X-Family-Id`)** — toute lecture est scopée à la famille active A ; l'overlay ne contourne jamais le scope : il **demande explicitement** une projection RO de B via un lien `active` validé. Jamais de requête métier non scopée. (cdc §4.2, invariant.)
2. **Autorisation par Voters** — l'habilitation overlay s'appuie sur le **Voter `agenda`** (≥ `view`) pour l'agenda et **`custody`** (≥ `view`) pour les pastilles de garde, opérant sur (Membre, Ressource). Le rôle `parent` force `edit` (donc `view` satisfait) ; l'enfant n'a jamais d'édition de structure ni d'accès dépenses (sans objet, RO). (cdc §2.2 / §4.2, invariant.)
3. **RO strict / aucune mutation overlay** — aucun chemin d'écriture overlay n'existe ; toute mutation sur une ressource de B avec `X-Family-Id: A` est **403**. (cdc §9 Lot 5, §7.)
4. **La garde n'est jamais modifiée par l'overlay** — aucune écriture sur `CustodyDay`/`CustodyException`/`HolidayRule` ; statistiques de garde de A inchangées ; priorité de garde (exception > vacances > base) intacte. (cdc §2.1, §3.3 CA, invariant priorité garde.)
5. **Aucune fuite hors agenda / aucune donnée d'enfant hors famille** — projection minimale (§3.6) ; pas d'enfant, responsable, description, location, notes, messages exposés ; seul l'agenda est partagé en RO. (cdc §7 « aucune donnée d'enfant exposée hors de la famille (et hors famille liée en RO sur l'agenda uniquement) », §9 Lot 5, invariant.)
6. **Champs chiffrés au repos 🔒** — l'overlay ne déchiffre/expose aucun champ 🔒 (`Message.text`, `Note.text`, `full_name`, `phone`, `Invitation.email`) ; ces champs ne font pas partie de l'agenda. Un dump SQL reste sans révélation. (cdc §5.2/§5.3, invariant.)
7. **Registre des données personnelles** — l'overlay crée un **flux de destinataire « famille liée »** pour les champs d'événement surfacés ; obligation d'**ajouter/mettre à jour les entrées de registre** (attributs déclaratifs + `/backend/docs/registre-donnees.md`), test de cohérence CI vert. Fait partie de la DoD. (cdc §7.1.1, invariant.)
8. **Pas de données de santé** — aucun champ santé créé ni exposé ; la catégorie `sante` reste un libellé de catégorie agenda, jamais une donnée médicale. (cdc §1.4.1, invariant.)
9. **Dépenses = v2, masquées** — l'overlay ne touche jamais les dépenses (onglet/API inactifs en v1). (cdc §1.4.4, invariant.)
10. **i18n ICU, fr-FR seule livrée** — toutes les chaînes overlay externalisées dès le départ ; ajout de langue sans code. (cdc §4.6, invariant.)
11. **Fuseau `Europe/Paris` par défaut, dates non codées en dur** — calculs calendaires conscients du fuseau (de A et de B) ; pas de date littérale (pas de 23 juin 2026). (cdc §10, invariant.)
12. **Dernier admin protégé** — non concerné par une mutation du Lot 5 (aucune gestion d'admin ici) ; respecté par le Lot 1. Le Lot 5 réagit aux changements de lien en invalidant l'overlay (US-LINK-3).
13. **`/infra/INSTALL.md`** — mis à jour **uniquement si** une dépendance change (le Lot 5 n'introduit a priori aucune nouvelle dépendance) ; sinon, vérification que rien n'a changé. (cdc §4.8, invariant DoD.)

---

## 8. Definition of Done (DoD)

Reprend la DoD générale (cdc §9) déclinée au Lot 5 :

### 8.1 Tests

- **Backend — unitaires** :
  - Règle d'habilitation `canUseOverlay` (parent / beau-parent view/edit/none / enfant view/none ; lien active/pending/absent ; soi-même ; non lié). (§3.2, §4.3)
  - Projection `OverlayEvent` : **assertion d'absence** des champs hors projection (`childIds`, `responsibleMembershipId`, `description`, `location`, `seriesId`, `@id` mutable). (§3.6, anti-fuite)
  - Calcul des pastilles par jour, multi-jours, all-day, fuseaux A≠B, DST, bissextile. (§3.4, §4.1)
  - Fusion/tri/groupement par jour (ordre stable). (§3.3)
  - **Non-régression garde** : un test prouve qu'aucune écriture `CustodyDay`/`CustodyException` ni recalcul de stats n'est déclenché par l'overlay. (invariant)
- **Backend — fonctionnels / API** :
  - `GET /events?overlay=` : nominal, **403** (droit), **422** (lien non actif / non lié / soi-même), filtre `category`, intervalle inversé. Réponses **RFC 7807** localisées.
  - Tentatives de mutation sur ressources de B avec `X-Family-Id: A` ⇒ **403** (RO strict).
  - `PUT/GET /overlay-preferences` : upsert idempotent, scope membre courant, `Idempotency-Key`, validation lien actif.
  - Scope famille : `X-Family-Id` absent/incohérent rejeté.
  - **Anti-fuite** end-to-end : la réponse overlay ne contient aucune clé hors projection.
- **Web — E2E (Playwright)** :
  - Activation/désactivation du toggle overlay (Agenda et Garde), persistance, couleur `#9C6FB0`, badge « Famille liée », état vide localisé, accessibilité de base (origine non couleur-seule), responsive desktop/mobile.
  - Aucun bouton d'édition sur un élément overlay.
- **Mobile — Flutter** (écrans Agenda/Garde concernés) :
  - Tests de widgets : rendu pastilles/événements overlay, toggle, état vide.
  - Tests d'intégration offline : cache RO overlay étiqueté par lien, **purge à l'invalidation du lien**, toggle hors-ligne (outbox + `Idempotency-Key`).

### 8.2 Livrables techniques

- **Migrations** Doctrine fournies (création `overlay_preference`, unicité `(membership_id, family_link_id, view)`, FKs `ON DELETE CASCADE`, index). Aucune migration destructive sur `FamilyLink`/`Event`/`CustodyDay`.
- **OpenAPI à jour** : documentation du paramètre `overlay=` (sémantique RO, multi-valeurs, erreurs 422), des endpoints `overlay-preferences`, des schémas `OverlayEvent` (lecture seule). Le client Dart régénéré reste aligné.
- **i18n (fr-FR) câblée** : toutes les chaînes overlay externalisées (ICU, pluriels), aucune chaîne en dur ; ARB côté Flutter, fichiers de traduction Symfony.
- **Registre des données personnelles à jour** : entrée(s) déclarant le **destinataire « famille liée »** pour les champs d'événement surfacés (`title`, `category`, horaires) et la nouvelle entité `OverlayPreference` (catégorie technique) ; `/backend/docs/registre-donnees.md` régénéré ; **test de cohérence CI vert**.
- **INSTALL.md** : mis à jour **si** une dépendance a changé (sinon vérifié inchangé ; le Lot 5 ne devrait en ajouter aucune).
- **Analyse statique propre** : PHPStan (niveau élevé) + php-cs-fixer ; `dart analyze` + `dart format` verts.

### 8.3 Conformité produit & design

- **Couleur `#9C6FB0`** appliquée aux éléments et pastilles overlay (web Twig/CSS + Flutter), conforme au token « Famille liée (overlay) » du design system §8.
- **Pastilles d'activité** superposées sans masquer la couleur du parent attribué (Garde), conformes à la maquette.
- **RO strict vérifié** : aucun affordance d'édition sur les éléments overlay ; détail RO.
- **Conformité aux maquettes** (desktop sidebar 252px + zone principale ; mobile iOS « liquid glass » / Material) ; états vides et badges conformes.
- **Invariants §7 respectés et testés** (notamment garde jamais modifiée, anti-fuite, scope famille, Voters).
