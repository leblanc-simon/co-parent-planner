# Lot 4 — Agenda

> Document de conception détaillée. Source de vérité : `specs/cdc.md` (notamment §2.5, §3.4, §5.2, §6.2, §8) et `CLAUDE.md` (invariants verrouillés). Ce document ne remet en cause aucune décision d'architecture (§0 cdc). Toute divergence visuelle est arbitrée par les maquettes HTML du bundle Claude Design.

---

## 0. Objectif & périmètre

### 0.1 Objectif

Doter CoParentPlanner d'un **agenda d'activités** scopé à la famille active, permettant aux membres habilités de planifier et consulter les événements qui touchent aux enfants (sport, musique, école, etc.), avec **récurrence**, **catégories**, **responsable** (parent ou beau-parent), **rattachement multi-enfants ou « toute la famille »**, **filtres**, vue **groupée par jour**, et bloc **« À venir »** sur l'Accueil. Le tout en **web (Symfony UX / Twig / Turbo / Stimulus)** et **mobile (Flutter)**, avec **synchronisation temps réel** via Mercure (Turbo Streams côté web, SSE/JSON côté mobile).

### 0.2 Dans le périmètre (cdc §9 — Lot 4)

- Entité **`Event`** (création, lecture, édition, suppression) et table de liaison **`event_child`** (relation N-N), flag **`whole_family`**.
- **Catégories** d'agenda : `sport`, `musique`, `ecole`, `sante`, `autre` (couleurs §8). *(NB : `sante` est ici une catégorie d'événement — libellé d'agenda — et **non** un champ typé de données de santé ; voir §7.)*
- **Responsable** d'un événement : un **parent** ou un **beau-parent** de la famille (jamais un enfant, jamais un membre d'une autre famille).
- **Rattachement** : un ou plusieurs enfants de la famille active, **ou** le flag « toute la famille » (`whole_family`).
- **Récurrence** : `none` | `weekly` | `biweekly` | `monthly`, avec **`until`** (date de fin) **ou** **`count`** (nombre d'occurrences), et option **« exclure les vacances scolaires »** (`exclude_holidays`).
- **Expansion des occurrences** d'une série sur une fenêtre `[from, to]`.
- **Édition par portée** : `scope=occurrence` (override d'une seule occurrence) vs `scope=series` (toute la série, à partir de l'occurrence éditée).
- **Suppression** par portée (occurrence vs série).
- **Vue agenda groupée par jour** avec **filtres par catégorie**.
- Bloc **« À venir »** sur l'Accueil : **les 4 prochains événements** (US-HOME-1, cdc §3.2).
- **Temps réel agenda** : push création / modification / suppression sur le topic `/families/{id}/agenda` (cdc §4.5).
- **Autorisation par Voter `agenda`** (`none|view|edit`) sur chaque ressource (cdc §2.2).
- **i18n** (fr-FR livrée), fuseau **Europe/Paris** par défaut, formats ICU.

### 0.3 Hors périmètre (traité ailleurs)

- **Overlay des événements de la famille liée** (lecture seule, couleur `#9C6FB0`) : **Lot 5** (US-AG-4). Le paramètre `overlay=` de l'endpoint agenda (§6.2) et la sémantique « famille liée » sont **réservés mais inactifs en Lot 4** ; on prévoit le point d'extension sans l'implémenter.
- **Notifications e-mail** (rappel/création d'événement, cdc §2.9) : **Lot 7**. Le Lot 4 ne déclenche **aucun** e-mail ; il publie seulement les messages Mercure.
- **Push mobile** : **Lot 10** (v2).
- **Offline riche mobile** (Drift + outbox) : la **couche** existe depuis le Lot 0 ; le Lot 4 doit être **compatible** outbox/`Idempotency-Key` mais le parcours offline complet de l'agenda est consolidé/validé au **Lot 8**.
- **Garde** : l'agenda **ne modifie jamais** la garde (lecture éventuelle des `CustodyDay`/`SchoolHoliday` uniquement). La garde est livrée au **Lot 3**.

### 0.4 Contexte v1 / v2

L'agenda est **entièrement v1** (cdc §1.3). Aucune dépendance aux dépenses (v2). La catégorie d'agenda `sante` n'introduit aucun champ médical typé (cf. §7, invariant « pas de données de santé »).

---

## 1. Dépendances

### 1.1 Lots prérequis (avant le Lot 4)

| Lot | Pourquoi il est requis |
|---|---|
| **Lot 0 — Fondations** | Monorepo, Symfony + API Platform + Doctrine + JWT + **squelette des Voters**, i18n (ICU), AssetMapper/Turbo/Stimulus, **Mercure** opérationnel, client Flutter généré depuis l'OpenAPI, couche offline (Drift + outbox), harnais de tests (PHPUnit, Playwright, `flutter test`, PHPStan). Auth complète. |
| **Lot 1 — Familles, membres, droits, invitations, liens** | Scope **`X-Family-Id`**, **`Membership`** + **`Right`** par domaine, **Voter par domaine** (dont `agenda`). Le **responsable** d'un événement est un `Membership` (rôle `parent` ou `step_parent`). Indispensable pour valider l'appartenance famille et les droits. |
| **Lot 2 — Enfants & notes** | Entité **`Child`** : nécessaire pour la relation N-N `event_child` et l'affichage des pastilles enfant. Le rattachement « enfant(s) » exige que les profils enfants existent. |
| **Lot 3 — Garde (moteur + vacances)** | Entités **`SchoolHoliday`** (+ `HolidayRule`) : nécessaires à l'option **`exclude_holidays`** (soustraction des périodes de vacances scolaires lors de l'expansion des occurrences). Sans le Lot 3, l'option d'exclusion serait sans données. |

> **Conséquence de dépendance** : si une famille **n'a pas** de périodes `SchoolHoliday` importées (Lot 3 non configuré, ou famille `intact` sans garde), l'option `exclude_holidays` est **acceptée mais sans effet** (aucune période à soustraire) — voir §4.

### 1.2 Lots débloqués (après le Lot 4)

| Lot | Ce que le Lot 4 lui apporte |
|---|---|
| **Lot 5 — Familles liées & overlay** | L'agenda et son endpoint (paramètre `overlay=`) sont la cible de l'overlay lecture seule (US-AG-4). Le Lot 4 fournit la structure d'événements, l'expansion et le rendu sur lesquels l'overlay se greffe. |
| **Lot 7 — Notifications e-mail** | Les déclencheurs « événement d'agenda (création/rappel) » (cdc §2.9, `NotificationPref.category=agenda`) s'appuient sur les événements et leurs occurrences produits par ce lot. |
| **Lot 8 — PWA / offline mobile** | Le parcours offline riche (cache lecture + outbox + résolution de conflits) sera validé de bout en bout sur, entre autres, l'agenda. |
| **Lot 10 — Push mobile (v2)** | Réutilise les déclencheurs agenda côté notifications. |

---

## 2. User stories complètes

> Rôles : `parent`, `step_parent` (beau-parent), `child` (enfant), `admin` (attribut additionnel d'un parent). Droit pertinent : domaine **`agenda`** ∈ {`none`, `view`, `edit`}. Rappel RBAC (cdc §2.2) : le **parent** a toujours `edit` (forcé) ; le **beau-parent** a un niveau au choix des parents ; l'**enfant** est au mieux `view`, jamais `edit`, et n'a jamais d'édition de structure.

### US-AG-1 — Créer un événement

**En tant que** membre habilité (parent ou beau-parent avec `agenda=edit`), **je veux** créer un événement (titre, catégorie, enfant(s) ou « toute la famille », responsable, date/heure, lieu, description) **afin de** coordonner les activités des enfants.

**Critères d'acceptation**

```gherkin
Scénario : création nominale d'un événement ponctuel
  Étant donné un utilisateur connecté, membre parent de la famille F, avec X-Family-Id = F
  Et un enfant E appartenant à F
  Quand il POST /api/families/F/events avec
       { title:"Cours de tennis", category:"sport", responsibleMembershipId:<parentB>,
         childIds:[E], location:"TC Bellevue", startsAt:"<date>T17:30:00+02:00",
         endsAt:"<date>T18:30:00+02:00", recurrence:{ freq:"none" } }
  Alors la réponse est 201 Created
  Et le corps contient un id, seriesId = null, et les champs renvoyés tels que soumis (localisés)
  Et un message Mercure de type "event.created" est publié sur /families/F/agenda

Scénario : création avec rattachement "toute la famille"
  Étant donné un membre avec agenda=edit sur F
  Quand il crée un événement avec wholeFamily=true et childIds=[]
  Alors la réponse est 201
  Et l'événement a whole_family=true et aucune ligne event_child

Scénario : un beau-parent peut être responsable
  Étant donné un beau-parent SP de F dont le droit agenda=edit
  Quand un parent crée un événement avec responsibleMembershipId = SP
  Alors la réponse est 201
  Et responsibleMembershipId = SP (cdc §3.4 CA : "un beau-parent peut être responsable")

Scénario d'erreur : titre vide
  Quand un membre edit POST un événement avec title=""
  Alors la réponse est 422 application/problem+json
  Et detail mentionne le champ "title" requis (message localisé fr-FR)

Scénario d'erreur : catégorie invalide
  Quand un membre edit POST un événement avec category="vacances"
  Alors la réponse est 422 application/problem+json (catégorie hors enum sport|musique|ecole|sante|autre)

Scénario d'erreur : ni enfant ni toute-la-famille
  Quand un membre edit POST un événement avec childIds=[] et wholeFamily=false
  Alors la réponse est 422 (au moins un enfant OU wholeFamily=true requis)

Scénario d'erreur : enfant d'une autre famille
  Étant donné un enfant E2 appartenant à la famille G (≠ F)
  Quand un membre de F POST un événement childIds=[E2]
  Alors la réponse est 422 (ou 404 selon politique) : E2 hors scope famille F

Scénario d'erreur : responsable invalide (enfant ou autre famille)
  Quand un membre edit désigne responsibleMembershipId = un membre de rôle child, ou un membre hors F
  Alors la réponse est 422 (le responsable doit être un parent/beau-parent de F)

Scénario d'erreur : ends_at avant starts_at
  Quand un membre edit POST endsAt < startsAt
  Alors la réponse est 422 (intervalle invalide)

Scénario : autorisation refusée (droit insuffisant)
  Étant donné un membre dont le droit agenda=view (ou none)
  Quand il POST /api/families/F/events
  Alors la réponse est 403 application/problem+json
  Et detail = "Droit 'agenda:edit' requis." (localisé)

Scénario : autorisation refusée (enfant)
  Étant donné un membre de rôle child (agenda=view au mieux)
  Quand il tente de créer un événement
  Alors la réponse est 403 (un enfant n'a jamais agenda=edit)

Scénario : scope famille manquant / invalide
  Quand un membre POST sans header X-Family-Id, ou avec X-Family-Id pour une famille dont il n'est pas membre
  Alors la réponse est 400 (header manquant) ou 403 (non-membre) — jamais de fuite de données

Scénario temps réel : autre membre voit l'événement apparaître
  Étant donné deux membres A et B de F connectés au web, agenda=view, abonnés à /families/F/agenda
  Quand A crée un événement
  Alors B reçoit un Turbo Stream "event.created" injecté dans la liste sans rechargement (≤ ~1s)
```

### US-AG-2 — Créer une série récurrente

**En tant que** membre habilité (`agenda=edit`), **je veux** créer une série récurrente (hebdomadaire / quinzaine / mensuelle) avec date de fin ou nombre d'occurrences et option d'exclusion des vacances scolaires **afin de** ne pas ressaisir une activité régulière.

**Critères d'acceptation**

```gherkin
Scénario : série hebdomadaire avec date de fin
  Étant donné un membre edit sur F
  Quand il crée un événement avec recurrence={ freq:"weekly", until:"<date_fin>", excludeHolidays:false }
  Alors la réponse est 201, l'événement a un seriesId non nul
  Et GET des events sur [from,to] renvoie une occurrence par semaine de startsAt jusqu'à until inclus

Scénario : série quinzaine avec count
  Quand il crée recurrence={ freq:"biweekly", count:10 }
  Alors la série produit exactement 10 occurrences (la première = starts_at, puis +14 jours × 9)

Scénario : série mensuelle conservant le quantième
  Quand il crée recurrence={ freq:"monthly", count:6 } avec starts_at au 31 du mois
  Alors les mois sans 31 sont gérés selon la règle de §3 (clamp au dernier jour OU saut, choix documenté et constant)

Scénario : exclusion des vacances scolaires
  Étant donné une famille F dont les SchoolHoliday couvrent [V_start, V_end]
  Et un événement weekly avec excludeHolidays=true dont certaines occurrences tombent dans [V_start, V_end]
  Quand on liste les occurrences sur [from,to]
  Alors les occurrences tombant dans une période de vacances de F sont absentes du résultat

Scénario d'erreur : until ET count tous deux fournis
  Quand recurrence contient à la fois until et count
  Alors la réponse est 422 (mutuellement exclusifs)

Scénario d'erreur : until dans le passé / antérieur à starts_at
  Quand recurrence.until < date(starts_at)
  Alors la réponse est 422

Scénario d'erreur : count <= 0 ou hors borne max
  Quand recurrence.count <= 0 ou > MAX_OCCURRENCES (borne définie en §3/§4)
  Alors la réponse est 422

Scénario d'erreur : freq invalide
  Quand recurrence.freq = "daily"
  Alors la réponse est 422 (enum none|weekly|biweekly|monthly)

Scénario : autorisation refusée
  Étant donné un membre agenda=view
  Quand il crée une série
  Alors 403 (droit agenda:edit requis)

Scénario : excludeHolidays sans données de vacances
  Étant donné une famille sans SchoolHoliday (ex. famille intact, ou import non fait)
  Quand on crée une série excludeHolidays=true
  Alors 201 et l'expansion ne soustrait rien (option acceptée, sans effet)
```

### US-AG-3 — Consulter l'agenda groupé par jour et filtrer par catégorie

**En tant que** membre avec `agenda=view` (ou `edit`), **je veux** consulter l'agenda groupé par jour et le filtrer par catégorie **afin de** retrouver rapidement les activités d'une journée ou d'un type donné.

**Critères d'acceptation**

```gherkin
Scénario : liste groupée par jour
  Étant donné un membre view de F et des événements (ponctuels + récurrents) sur [from,to]
  Quand il GET /api/families/F/events?from=<from>&to=<to>
  Alors la réponse liste les occurrences triées par date/heure croissante
  Et les occurrences sont regroupables par jour (date locale Europe/Paris) ; le tri intra-jour place les all_day en tête puis par heure

Scénario : filtre par catégorie
  Quand il GET ...&category=sport
  Alors seules les occurrences de catégorie sport sont renvoyées

Scénario : filtre multi-catégories
  Quand il GET ...&category=sport,musique
  Alors les occurrences sport OU musique sont renvoyées (union)

Scénario : fenêtre vide
  Quand from et to délimitent une période sans aucun événement
  Alors la réponse est 200 avec une liste vide (et l'UI affiche un état vide localisé)

Scénario d'erreur : from > to
  Quand from > to
  Alors la réponse est 422

Scénario d'erreur : fenêtre trop large
  Quand (to - from) dépasse la fenêtre maximale autorisée (§4)
  Alors la réponse est 422 (protection contre expansion non bornée)

Scénario : autorisation refusée (agenda=none)
  Étant donné un membre agenda=none
  Quand il GET les événements
  Alors 403 (droit agenda:view requis)

Scénario : scope famille
  Quand un membre de F demande l'agenda avec X-Family-Id=G (non-membre de G)
  Alors 403, aucune donnée de G renvoyée
```

### US-AG-4 — Activer l'overlay famille liée *(préparé, livré au Lot 5)*

**En tant que** membre habilité, **je veux** activer l'overlay pour voir en lecture seule les événements de la famille liée **afin de** anticiper les activités du second foyer.

> **Lot 4 : hors périmètre fonctionnel.** On documente uniquement le **point d'extension** : le paramètre `overlay=` de l'endpoint et le fait que les événements overlay sont **en lecture seule**, colorés `#9C6FB0`, et **n'altèrent jamais** l'agenda propre. Implémentation effective et CA complets : **Lot 5**.

```gherkin
Scénario (Lot 4) : overlay inactif par défaut
  Quand un membre GET /api/families/F/events sans overlay (ou overlay=false)
  Alors seuls les événements de F sont renvoyés (aucun événement de famille liée)

Scénario (Lot 4) : overlay=true non encore supporté
  Quand un membre GET ...&overlay=true en Lot 4
  Alors le paramètre est ignoré (comportement = overlay inactif) — l'activation réelle est livrée au Lot 5
```

### US-AG-5 — Éditer une occurrence unique d'une série

**En tant que** membre habilité (`agenda=edit`), **je veux** modifier une seule occurrence d'une série (ex. décaler le cours d'une semaine donnée) **afin de** gérer un cas ponctuel sans casser la série.

**Critères d'acceptation**

```gherkin
Scénario : override d'une occurrence (heure)
  Étant donné une série hebdomadaire S et son occurrence du jour J
  Quand un membre edit PATCH /api/events/<S>/occurrences ... ?scope=occurrence avec la date d'origine de J et { startsAt:"...18:30", endsAt:"...19:30" }
  Alors une occurrence override est matérialisée (rattachée à seriesId=S, recurrence_date=J)
  Et seule l'occurrence du jour J est décalée ; les autres occurrences de S restent inchangées
  Et un message Mercure "event.updated" est publié

Scénario : suppression d'une occurrence (exclusion)
  Quand un membre edit DELETE /api/events/<S>?scope=occurrence pour la date J
  Alors l'occurrence J est exclue (EXDATE) : elle n'apparaît plus, la série continue avant/après
  Et un message Mercure "event.deleted" (portée occurrence) est publié

Scénario d'erreur : occurrence inexistante
  Quand on cible une date qui n'est pas une occurrence valide de la série (ex. mauvais jour de semaine)
  Alors 422 (ou 404) : la date ne correspond à aucune occurrence

Scénario d'erreur : occurrence déjà exclue
  Quand on édite une occurrence déjà supprimée (EXDATE)
  Alors 409 (conflit) ou 404 selon politique — comportement documenté et constant

Scénario : autorisation refusée
  Étant donné un membre agenda=view
  Quand il PATCH/DELETE une occurrence
  Alors 403

Scénario : scope famille
  Quand un membre tente d'éditer une occurrence d'une série appartenant à une autre famille
  Alors 403/404 (jamais d'altération cross-famille)
```

### US-AG-6 — Éditer / supprimer toute la série

**En tant que** membre habilité (`agenda=edit`), **je veux** modifier ou supprimer toute la série (à partir de l'occurrence éditée) **afin de** propager un changement durable.

**Critères d'acceptation**

```gherkin
Scénario : édition de la série (this-and-future)
  Étant donné une série S avec des occurrences passées et futures
  Quand un membre edit PATCH /api/events/<S>?scope=series à partir de l'occurrence du jour J avec { location:"Nouveau lieu" }
  Alors les occurrences à partir de J (incluse) adoptent le nouveau lieu
  Et les occurrences antérieures à J sont préservées (scission : la série est éventuellement coupée en S_avant / S_après)
  Et les overrides d'occurrences déjà matérialisés avant J restent intacts
  Et un message Mercure "event.updated" (portée series) est publié

Scénario : changement de récurrence d'une série
  Quand on PATCH scope=series en passant freq=weekly -> biweekly avec un nouveau until
  Alors les occurrences futures sont régénérées selon la nouvelle règle (idempotence : recalcul stable §3)

Scénario : suppression de toute la série
  Quand un membre edit DELETE /api/events/<S>?scope=series
  Alors la série entière (et ses overrides/exclusions) est supprimée
  Et un message Mercure "event.deleted" (portée series) est publié

Scénario : suppression de la série à partir d'une date (this-and-future)
  Quand DELETE scope=series à partir de J
  Alors les occurrences >= J sont supprimées, celles < J conservées

Scénario d'erreur : scope manquant/invalide sur une série
  Quand on PATCH/DELETE un événement appartenant à une série sans paramètre scope (ou scope hors {occurrence,series})
  Alors 422 (scope requis pour les événements récurrents)

Scénario : PATCH sur événement non récurrent
  Étant donné un événement ponctuel (recurrence.freq=none, seriesId=null)
  Quand on PATCH /api/events/<id> sans scope (ou scope=occurrence)
  Alors l'événement est modifié normalement (scope ignoré/équivalent)

Scénario : autorisation refusée
  Étant donné un membre agenda=view
  Quand il PATCH/DELETE scope=series
  Alors 403
```

### US-AG-7 — Voir les prochains événements sur l'Accueil (« À venir »)

**En tant que** membre (au moins `agenda=view`), **je veux** voir sur l'Accueil les **4 prochains événements** **afin d'**avoir un aperçu immédiat de ce qui arrive (cdc §3.2, US-HOME-1).

**Critères d'acceptation**

```gherkin
Scénario : bloc "À venir" nominal
  Étant donné un membre view de F et des occurrences à venir (≥ maintenant, fuseau Europe/Paris)
  Quand l'Accueil se charge
  Alors le bloc "À venir" affiche les 4 prochaines occurrences triées par date/heure croissante
  Et chaque item affiche titre, catégorie (couleur §8), enfant(s)/famille, heure, et responsable

Scénario : moins de 4 événements
  Quand il existe moins de 4 occurrences à venir
  Alors le bloc affiche celles disponibles (0 à 3) avec un état vide localisé si 0

Scénario : occurrences récurrentes incluses
  Quand les 4 prochaines occurrences proviennent (en partie) de séries récurrentes
  Alors elles sont incluses (chaque occurrence comptée individuellement), overrides/exclusions respectés

Scénario : limite "maintenant"
  Étant donné un événement aujourd'hui dont l'heure est déjà passée
  Alors il n'apparaît plus dans "À venir" (borne = instant courant, pas début de journée) — règle documentée §3

Scénario : autorisation
  Étant donné un membre agenda=none
  Alors le bloc "À venir" est masqué (ou affiche un état "non autorisé" sans fuite)

Scénario temps réel
  Quand un événement à venir est créé/modifié/supprimé par un autre membre
  Alors le bloc "À venir" de l'Accueil se met à jour en direct (Turbo Stream) sans rechargement
```

### US-AG-8 — Recevoir les mises à jour d'agenda en temps réel

**En tant que** membre connecté (web ou mobile), **je veux** que la liste d'agenda et le bloc « À venir » reflètent en direct les créations/modifications/suppressions des autres membres **afin de** travailler sur un état partagé cohérent.

**Critères d'acceptation**

```gherkin
Scénario web : Turbo Stream
  Étant donné deux membres de F sur la vue Agenda, abonnés au topic Mercure /families/F/agenda
  Quand l'un crée/modifie/supprime un événement
  Alors l'autre reçoit un fragment Turbo Stream (append/replace/remove) appliqué au DOM (≤ ~1s)

Scénario mobile : SSE/JSON
  Étant donné un membre sur l'app Flutter abonné via SSE au topic agenda
  Quand un événement change côté serveur
  Alors le client reçoit un payload JSON {type, eventId/seriesId, scope} et met à jour le cache local + l'UI

Scénario : autorisation des topics
  Étant donné un JWT Mercure dont les claims listent les topics des familles du membre
  Quand un membre tente de s'abonner au topic agenda d'une famille dont il n'est pas membre
  Alors l'abonnement est refusé (claims absents)

Scénario : Mercure hors-ligne
  Étant donné le hub Mercure indisponible
  Quand un membre charge la vue Agenda
  Alors la lecture initiale (HTTP) fonctionne normalement ; seules les MAJ live sont indisponibles
  Et l'UI tente une reconnexion automatique (EventSource) sans bloquer la consultation

Scénario : portée du push
  Quand une édition scope=series modifie plusieurs occurrences
  Alors le message Mercure indique la portée (series) pour que le client invalide/rafraîchisse la fenêtre concernée
```

### US-AG-9 — Renseigner lieu et description ; gérer les événements « journée entière »

**En tant que** membre habilité, **je veux** préciser un lieu (libellé libre), une description optionnelle, et marquer un événement « journée entière » **afin de** documenter l'activité.

**Critères d'acceptation**

```gherkin
Scénario : événement journée entière
  Quand un membre edit crée un événement avec allDay=true (date sans heure)
  Alors starts_at/ends_at sont normalisés sur la journée locale (Europe/Paris) ; l'UI n'affiche pas d'heure
  Et le tri groupé par jour place les all_day en tête de journée

Scénario : description optionnelle
  Quand description est omise ou vide
  Alors 201/200 (champ optionnel)

Scénario : lieu libre
  Quand location contient un texte libre (≤ longueur max)
  Alors il est stocké tel quel ; au-delà de la longueur max → 422

Scénario : sécurité d'affichage
  Quand title/location/description contiennent du HTML/markup
  Alors le rendu (Twig web / Flutter) échappe le contenu (pas d'injection)
```

---

## 3. Algorithme(s)

> Tous les calculs temporels se font dans le **fuseau de la famille** (`Family.timezone`, défaut `Europe/Paris`). Les instants sont stockés en base en **UTC** (`timestamptz`), convertis au fuseau famille pour le regroupement par jour et l'affichage. **La date « courante » n'est jamais codée en dur** (cdc §10) : on utilise une horloge injectable (`ClockInterface`), testable et figeable en test.

### 3.1 Modèle de représentation des séries

Une **série** est portée par un **événement « maître »** (le premier de la série) :
- `series_id` : identifiant de série (UUID). Le maître porte `series_id = self.id` (ou un UUID dédié partagé par toute la série) ; un événement ponctuel a `series_id = null`.
- `recurrence` (jsonb) : `{ freq, until|count, excludeHolidays }` porté par le maître.
- Les occurrences **ne sont pas matérialisées** en base par défaut (cohérent avec « calculées au choix d'implémentation », cdc §2.5). On matérialise **uniquement** :
  - les **overrides d'occurrence** (`EventOverride` / lignes `Event` rattachées à la série avec un `recurrence_date`), et
  - les **exclusions d'occurrence** (EXDATE : marqueur `cancelled` sur un override, ou table `event_exception_date`).

> Choix verrouillé pour ce lot : **expansion à la volée bornée** sur `[from, to]` (l'horizon est toujours fourni par l'appelant), avec **matérialisation paresseuse** des seuls overrides/exclusions. Cela reste cohérent avec la garde (qui, elle, matérialise, mais sur un domaine différent). On ne matérialise pas chaque occurrence d'agenda pour éviter une explosion de lignes.

### 3.2 Expansion des occurrences d'une série sur `[from, to]`

**Entrées** : maître `M` (`starts_at`, `ends_at`, `recurrence`), fenêtre `[from, to]` (instants), fuseau `tz`, ensemble des overrides `OV[recurrence_date → eventOverride]`, ensemble des EXDATE `EX`, et (si `excludeHolidays`) les périodes `SchoolHoliday` de la famille intersectant `[from, to]`.

**Constantes** : `MAX_OCCURRENCES_PER_SERIES` (ex. 730), `MAX_WINDOW_DAYS` (ex. 420 ≈ horizon garde) — bornes anti-explosion (voir §4).

```
function expandSeries(M, from, to, tz):
    occurrences = []
    freq        = M.recurrence.freq
    duration    = M.ends_at - M.starts_at            # conservée pour chaque occurrence

    if freq == "none":
        if M.starts_at in [from, to]: occurrences += [makeOccurrence(M, M.starts_at)]
        return applyOverridesAndExdates(occurrences, M)

    # 1) Générer les dates candidates à partir de l'ancrage = M.starts_at (en heure locale tz)
    anchorLocal = toLocal(M.starts_at, tz)
    step        = stepFor(freq)        # weekly→+7j, biweekly→+14j, monthly→+1 mois (quantième)
    limit       = recurrence.count ?? +inf
    untilLocal  = recurrence.until ? endOfDay(recurrence.until, tz) : +inf

    n = 0
    cur = anchorLocal
    while n < limit and cur <= untilLocal and n < MAX_OCCURRENCES_PER_SERIES:
        instUtc = toUtc(cur, tz)                      # respecte les transitions DST (§4)
        if instUtc > to: break                        # plus rien d'utile dans la fenêtre
        if instUtc >= from:                           # dans la fenêtre demandée
            occurrences += [makeOccurrence(M, instUtc, duration)]
        n += 1
        cur = advance(anchorLocal, freq, n)           # voir 3.2.1 (calcul depuis l'ancre, pas cumulatif)

    # 2) Appliquer overrides et exclusions
    occurrences = applyOverridesAndExdates(occurrences, M, OV, EX)

    # 3) Exclusion vacances (option)
    if M.recurrence.excludeHolidays:
        occurrences = [o for o in occurrences
                       if not intersectsAnyHoliday(o, holidaysOf(M.family, from, to), tz)]
    return occurrences
```

#### 3.2.1 Calcul des dates récurrentes (depuis l'ancre, non cumulatif)

Pour éviter les dérives d'arrondi/DST, chaque occurrence est calculée **par rapport à l'ancre** et non par addition cumulée :

- `weekly` : `occ_n = anchorLocal + n*7 jours` (même heure locale, même jour de semaine).
- `biweekly` : `occ_n = anchorLocal + n*14 jours`.
- `monthly` : `occ_n = anchorLocal + n mois`, en **conservant le quantième** (`day-of-month`) de l'ancre. **Règle des mois courts (verrouillée) : clamp** — si le quantième n'existe pas (ex. 31 en février), l'occurrence est placée au **dernier jour du mois** (28/29/30). *(Règle constante et documentée ; pas de saut silencieux.)*

L'heure locale est toujours celle de l'ancre (`anchorLocal.time`), reconstruite à chaque occurrence, ce qui fait que **l'heure murale reste stable** à travers les changements d'heure (DST) — voir §4.

**Complexité** : `O(k)` où `k` = nombre d'occurrences candidates dans `[from, to]` (borné par `MAX_OCCURRENCES_PER_SERIES`). L'exclusion vacances est `O(k · h)` avec `h` = nombre de périodes de vacances intersectant la fenêtre (typiquement petit), optimisable par tri/recherche dichotomique → `O(k · log h)`.

### 3.3 Application des overrides et exclusions

```
function applyOverridesAndExdates(occurrences, M, OV, EX):
    result = []
    for occ in occurrences:
        key = recurrenceKey(occ.recurrence_date, M.tz)   # date d'origine (clé d'occurrence)
        if key in EX:                # occurrence supprimée individuellement
            continue
        if key in OV:                # occurrence overridée (déplacée/modifiée)
            result += [OV[key]]      # utilise l'événement override matérialisé (ses propres champs)
        else:
            result += [occ]          # occurrence virtuelle issue du maître
    # + ajouter les overrides "déplacés" dont la nouvelle date tombe dans la fenêtre mais
    #   dont la recurrence_date d'origine était hors fenêtre (cas limite §4)
    return sortByStartThenAllDayFirst(result)
```

> **Clé d'occurrence** : la **date d'origine** (`recurrence_date`, normalisée à la date locale tz du créneau planifié), pas la date affichée après déplacement. Ainsi un override qui décale l'occurrence reste rattaché à son créneau d'origine.

### 3.4 Édition `scope=occurrence` (override / exclusion)

```
PATCH /api/events/{seriesId}  ?scope=occurrence  body:{ recurrenceDate, ...champs }
1. Charger le maître M (Voter agenda:edit, scope famille).
2. Vérifier que recurrenceDate ∈ occurrences(M) (sinon 422).
3. Si EXDATE existe déjà pour recurrenceDate → 409/404 (documenté).
4. UPSERT un override:
   - créer (ou maj) un Event "détaché" rattaché à series_id=M, recurrence_date=recurrenceDate,
     copiant les champs non modifiés du maître puis appliquant le patch.
   - le marquer is_override=true (pas un nouveau maître).
5. Transaction unique ; publier Mercure "event.updated" {scope:occurrence, seriesId, recurrenceDate}.

DELETE /api/events/{seriesId} ?scope=occurrence  body:{ recurrenceDate }
1..2 idem.
3. Insérer un EXDATE (marqueur cancelled) pour recurrenceDate (idempotent : ré-insertion = no-op).
4. Si un override existait pour cette date, le supprimer/neutraliser.
5. Transaction ; publier Mercure "event.deleted" {scope:occurrence, seriesId, recurrenceDate}.
```

### 3.5 Édition `scope=series` (this-and-future + scission)

L'édition de série est **« cette occurrence et les suivantes »** (sémantique standard iCalendar `THISANDFUTURE`), pour ne pas réécrire le passé :

```
PATCH /api/events/{seriesId} ?scope=series body:{ fromOccurrence (recurrenceDate pivot), ...champs }
1. Charger M (Voter agenda:edit, scope famille).
2. Si fromOccurrence == première occurrence de M :
     → modifier M en place (et/ou sa recurrence) ; pas de scission.
3. Sinon (scission) :
   a. Clore l'ancienne série : poser M.recurrence.until = jour précédant fromOccurrence
      (les occurrences < pivot restent générées par M).
   b. Créer une NOUVELLE série M2 :
      - starts_at = fromOccurrence (heure locale conservée), même duration,
      - champs = champs de M + patch,
      - recurrence = recurrence de M (count recalculé : count_restant) + patch éventuel,
      - series_id = nouvel UUID.
   c. Réaffecter à M2 les overrides/EXDATE dont recurrence_date >= pivot ;
      conserver sur M ceux < pivot.
4. Transaction unique (idempotente : rejouer la même requête produit le même découpage).
5. Publier Mercure "event.updated" {scope:series, oldSeriesId:M, newSeriesId:M2}.

DELETE /api/events/{seriesId} ?scope=series body:{ fromOccurrence? }
- Sans fromOccurrence : supprimer M + tous ses overrides/EXDATE (cascade) → "event.deleted"{scope:series}.
- Avec fromOccurrence : poser until = jour précédant pivot, supprimer overrides/EXDATE >= pivot.
```

> **Idempotence** : la scission est déterministe (mêmes entrées → même `until` sur M, même fenêtre sur M2). Avec `Idempotency-Key` (outbox mobile), un rejeu renvoie la même réponse sans double-scission.

### 3.6 Regroupement par jour + tri (vue agenda)

```
1. occ = pour chaque série de F intersectant [from,to] : expandSeries(...)  ∪  événements ponctuels de F dans [from,to]
2. (filtre catégorie) si category fourni : ne garder que occ.category ∈ set(category)
3. (filtre overlay) Lot 4 : ignorer overlay (Lot 5)
4. tri global : clé = (date_locale, all_day desc, starts_at, title, id)
   → all_day en tête de journée, puis par heure, tie-break stable par title puis id
5. groupBy(date_locale(occ.starts_at, tz))   # date murale dans le fuseau famille
6. rendu : une section par jour (web : Turbo Frame ; mobile : sections de liste)
```

**Complexité** : `O(S·k + N + M log M)` avec `S` séries, `k` occurrences/série dans la fenêtre, `N` ponctuels, `M` total trié.

### 3.7 « 4 prochains événements » (Accueil)

```
function upcoming(F, member, now, tz, limit=4):
    require agenda:view (Voter)            # sinon bloc masqué
    from = now                              # borne = instant courant (pas début de jour)
    to   = now + UPCOMING_LOOKAHEAD (ex. +90 jours, borne pour éviter d'expanser l'infini)
    occ  = expand+ponctuels sur [from, to] de F (overrides/EXDATE/exclusion vacances appliqués)
    occ  = [o for o in occ if o.endInstant >= now or o.startInstant >= now]  # à venir
    occ  = sortByStart(occ)
    return occ[0:limit]                      # 0..4
```

> Si moins de 4 occurrences sont trouvées dans `UPCOMING_LOOKAHEAD`, on **étend** la fenêtre par paliers (ex. +90j → +180j → +365j) jusqu'à `limit` ou un plafond, pour ne pas masquer un événement lointain isolé. Plafond documenté (§4).

### 3.8 Exclusion des vacances (`exclude_holidays`)

```
function intersectsAnyHoliday(occ, holidays, tz):
    # une occurrence est exclue si sa journée locale (ou son intervalle) chevauche une période de vacances
    occDayStart = startOfDay(occ.starts_at, tz)
    occDayEnd   = occ.all_day ? endOfDay(occ.starts_at, tz) : occ.ends_at
    for h in holidays:                       # h: [start_date, end_date] inclusifs (dates scolaires)
        hStart = startOfDay(h.start_date, tz)
        hEnd   = endOfDay(h.end_date, tz)
        if overlaps([occDayStart, occDayEnd], [hStart, hEnd]): return true
    return false
```

- Les **périodes** proviennent de `SchoolHoliday` de la **famille** (importées au Lot 3), pas d'un appel externe à la volée.
- L'exclusion s'applique **à l'expansion** : une occurrence exclue n'est ni listée, ni comptée, ni poussée en « À venir ». Elle **n'est pas** matérialisée comme EXDATE (c'est une exclusion dérivée, recalculable). Conséquence : modifier les périodes de vacances (Lot 3) change automatiquement le rendu de l'agenda au prochain calcul.
- **Priorité** : l'exclusion vacances **n'écrase pas** un override d'occurrence explicite. Décision verrouillée pour ce lot : un **override explicite** (occurrence déplacée/éditée par un humain) est **conservé** même s'il tombe en vacances (l'humain a sciemment posé cette date). Seules les occurrences **virtuelles** sont filtrées par `exclude_holidays`.

### 3.9 Publication temps réel (Mercure)

```
À chaque mutation committée (création / override / exclusion / édition série / suppression) :
1. Calculer le payload :
   - web  : Turbo Stream (fragment HTML : append | replace target=event_<id> | remove)
   - mobile: JSON { type: created|updated|deleted, scope: occurrence|series|single,
                    eventId?, seriesId?, recurrenceDate?, occurrenceWindowHint? }
2. Publier sur le topic /families/{familyId}/agenda APRÈS commit (cohérence lecture).
   → via Symfony Messenger (dispatch post-commit) pour ne pas pousser un état non persistant.
3. L'autorisation d'abonnement est portée par le JWT Mercure (claims = topics des familles du membre).
```

> **Ordre & cohérence** : publication **post-commit** uniquement (jamais dans la transaction). En cas d'échec de publication Mercure, la mutation reste valide (le temps réel est best-effort ; la lecture HTTP reste la source de vérité).

---

## 4. Cas limites (edge cases)

### 4.1 Dates, heures, fuseau & calendrier

- **Fuseau famille** : tout regroupement par jour et tout affichage utilisent `Family.timezone` (défaut `Europe/Paris`). Deux membres dans des fuseaux différents voient la **même** journée logique (celle de la famille). Comportement : conversion systématique au fuseau famille.
- **Stockage UTC** : `starts_at`/`ends_at` en `timestamptz` (UTC). Aucune date n'est codée en dur ; l'instant courant vient de `ClockInterface`.
- **Passage à l'heure d'été (DST, dernier dimanche de mars)** : l'heure locale 02:30 n'existe pas ce jour-là. Pour une occurrence récurrente tombant sur une heure inexistante : on **avance** au premier instant valide (politique « gap → forward shift »), documentée et constante. L'heure murale des autres occurrences reste stable.
- **Passage à l'heure d'hiver (dernier dimanche d'octobre)** : l'heure locale 02:30 existe deux fois. On choisit la **première** occurrence (avant le recul d'horloge). Documenté.
- **Stabilité de l'heure murale** : une série « tous les mardis 17:30 » reste à 17:30 **heure locale** des deux côtés du changement d'heure (l'instant UTC se décale, pas l'heure murale).
- **Année bissextile** : `monthly` avec ancre au 29 février → mois sans 29 → clamp au 28 (règle des mois courts §3.2.1). Une série mensuelle ancrée au 31 → clamp 30/29/28 selon le mois.
- **Quantième mensuel** : règle de clamp (jamais de saut silencieux). Test dédié sur 31 janvier → 28/29 février → 31 mars.
- **`all_day`** : normalisé sur la journée locale (00:00 → 23:59:59.999 fuseau famille). Un événement all_day chevauchant minuit reste sur **sa** journée d'ancrage pour le regroupement.
- **Événement à cheval sur deux jours** (non all_day, `ends_at` le lendemain) : il est rattaché à la journée de `starts_at` pour le regroupement ; l'UI peut indiquer « → lendemain ».
- **`until` exactement égal à une occurrence** : `until` est **inclusif** (interprété en fin de journée locale). Une occurrence dont la date == `until` est incluse.
- **`count`** : la première occurrence (= ancre) compte comme 1. `count=1` → uniquement l'ancre.
- **Fenêtre `[from,to]`** : `from` exclusif/inclusif → on prend `instant >= from && instant <= to` (bornes inclusives). `from > to` → 422.

### 4.2 Bornes & limites de récurrence (anti-explosion)

- **`MAX_OCCURRENCES_PER_SERIES`** (ex. 730) : si une série non bornée (ni `until` ni `count`) est soumise → **422** (au moins `until` ou `count` requis pour les séries, sauf si une politique d'« infini borné par l'horizon » est retenue ; **décision verrouillée : exiger `until` ou `count`**, sinon 422).
- **`MAX_WINDOW_DAYS`** (ex. 420) : `to - from` au-delà → 422 (protection lecture).
- **`UPCOMING_LOOKAHEAD`** : fenêtre initiale du bloc « À venir » (ex. 90j), étendue par paliers jusqu'à un plafond (ex. 365j). Au-delà : on renvoie ce qu'on a (≤ 4).
- **`until` & `count` tous deux fournis** → 422 (exclusifs).
- **`count <= 0`** → 422.
- **`until` < date(`starts_at`)** → 422.

### 4.3 Concurrence & idempotence

- **Double création (outbox mobile)** : `Idempotency-Key` sur POST → la 2ᵉ requête renvoie la **même** réponse (201 rejoué), pas un doublon.
- **Édition concurrente d'une même série** : transaction + verrouillage optimiste (`updated_at`/version) ; en cas de conflit → 409 (le mobile résout selon §4.4 cdc : autorité serveur, last-write-wins sur champs simples ; pour l'agenda, last-write-wins acceptable).
- **Override puis suppression de la même occurrence** : ordre déterministe ; la suppression (EXDATE) prime et neutralise l'override.
- **Scission idempotente** : rejouer un `PATCH scope=series` identique ne re-scinde pas (clé d'idempotence + détection que `until` est déjà posé au pivot).
- **Suppression d'une série déjà supprimée** : 404 (idempotent côté client : traiter 404 comme succès de suppression).
- **Création d'occurrence override sur une série supprimée entre-temps** : 404/409.

### 4.4 Autorisations (Voter `agenda`)

- **`agenda=none`** : aucune lecture ni écriture → 403 ; bloc « À venir » masqué.
- **`agenda=view`** : lecture OK ; toute écriture (POST/PATCH/DELETE) → 403.
- **`agenda=edit`** : lecture + écriture.
- **Parent** : `edit` **forcé** (RBAC §2.2), même si une donnée `Right` indiquait autre chose. Le Voter doit forcer `edit` pour le rôle parent.
- **Beau-parent** : niveau au choix (none/view/edit). Peut être **responsable** d'un événement même si son droit est `view` ? → **Décision verrouillée** : être désigné responsable est indépendant du droit d'édition (un parent peut désigner un beau-parent `view` comme responsable) ; en revanche **créer/éditer** exige `edit`.
- **Enfant** : jamais `edit` (jamais responsable, jamais d'édition de structure §2.2). Au mieux `view`.
- **Admin** : l'attribut admin ne donne pas en soi le droit `agenda` ; c'est le rôle (parent → edit) et les `Right` (beau-parent) qui décident. Admin ≠ agenda:edit automatique pour un beau-parent admin… mais un admin est nécessairement habilité par les réglages famille — **règle appliquée** : le droit `agenda` est celui résolu par le Voter, indépendamment de `is_admin`.
- **Dernier admin** : non concerné directement par l'agenda (pas de retrait/rétrogradation ici), mais l'invariant reste : aucune action d'agenda ne doit permettre de contourner la protection du dernier admin (sans objet en lecture/écriture d'événement).
- **Responsable retiré de la famille** : si le `Membership` responsable d'un événement est ensuite supprimé (Lot 1), l'événement doit rester valide → `responsible_membership_id` devient « membre supprimé/anonymisé » (cohérent RGPD §7) ou nullable avec affichage « responsable indisponible » ; **décision : on n'efface pas l'événement**, on neutralise la référence (FK `ON DELETE SET NULL` ou réassignation), comportement documenté.

### 4.5 Scope famille (`X-Family-Id`)

- **Header absent** sur une route scopée → 400.
- **Header pour une famille dont l'utilisateur n'est pas membre** → 403.
- **Mismatch** entre `X-Family-Id` et la famille de la ressource ciblée (ex. PATCH d'un event de F avec header G) → 403/404, jamais d'altération.
- **Enfant/responsable cross-famille** : un `childId` ou `responsibleMembershipId` n'appartenant pas à la famille active → 422.
- **Aucune requête métier non scopée** : l'agenda n'expose jamais d'événements hors famille active (overlay famille liée = Lot 5, et reste RO).

### 4.6 Valeurs vides / limites de champs

- **`title`** : requis, non vide après trim, longueur max (ex. 200) → sinon 422.
- **`location`/`description`** : optionnels ; longueurs max (ex. 255 / 2000) → 422 si dépassées.
- **`category`** : enum strict ; absente/invalide → 422.
- **`childIds`** vide **et** `wholeFamily=false` → 422 (au moins une cible).
- **`childIds`** non vide **et** `wholeFamily=true` → décision : 422 (mutuellement exclusifs) **ou** `wholeFamily` prime — **verrouillé : 422** (cible ambiguë).
- **`childIds`** contenant des doublons → dédoublonnés silencieusement (ou 422 ; **verrouillé : dédoublonnage silencieux**).
- **`responsibleMembershipId`** : requis, doit être parent/beau-parent de F.
- **`startsAt`** : requis ; format ISO 8601 avec offset ; sinon 422.
- **`endsAt`** : optionnel ; si fourni, `>= startsAt` ; si absent → durée par défaut (ex. 60 min) ou `ends_at = starts_at` selon politique (**verrouillé : si absent et non all_day, durée 0 / ponctuel**).
- **`recurrence`** absent → traité comme `{freq:"none"}`.

### 4.7 Échecs des dépendances externes

- **API vacances (Opendatasoft) indisponible** : sans objet en lecture/écriture d'agenda — l'agenda lit les `SchoolHoliday` **déjà importés** (Lot 3), pas l'API en direct. Si aucune période n'a pu être importée, `exclude_holidays` est sans effet (option acceptée).
- **Mercure hors-ligne** : lecture HTTP non affectée ; MAJ live indisponibles ; reconnexion auto côté client ; aucune perte de donnée (la persistance est en base).
- **E-mail** : **non concerné** au Lot 4 (notifications = Lot 7). Aucune création d'événement ne doit échouer faute d'e-mail.
- **PostgreSQL indisponible** : 503/erreur ; aucune publication Mercure (post-commit only).

### 4.8 Conflits offline (mobile)

- **Création offline** rejouée à la reconnexion via outbox + `Idempotency-Key` → pas de doublon.
- **Édition d'une occurrence supprimée côté serveur entre-temps** → 409/404 ; l'app signale le conflit (autorité serveur, §4.4 cdc) et resynchronise l'état canonique.
- **Last-write-wins** sur champs simples d'un événement ; pas de fusion de champs.
- **Statut « en attente de synchro »** visible pour les événements non confirmés.

### 4.9 Sécurité / RGPD

- **Pas de données de santé** : la catégorie `sante` est un **libellé d'agenda**, pas un champ de données de santé. Aucune information médicale typée n'est introduite. Les champs libres (`title`, `location`, `description`) ne sont **pas** des champs santé typés ; aucun champ chiffré 🔒 n'est ajouté par ce lot (cf. §5). *(Si l'utilisateur saisit du contenu sensible en texte libre, c'est hors périmètre de typage ; l'invariant « aucun champ typé santé » est respecté.)*
- **Échappement** : `title`/`location`/`description` rendus échappés (anti-XSS) côté web (Twig autoescape) et mobile.
- **Journalisation** : les créations/modifications/suppressions d'agenda **ne sont pas** listées explicitement comme « accès sensibles » dans `AuditLog` (cdc §5.4 cible droits/garde/notes/RGPD). On **n'ajoute pas** d'entrée AuditLog pour l'agenda en Lot 4 (sauf décision contraire), pour rester fidèle au cdc.
- **Fuite cross-famille** : impossible (scope strict §4.5) ; l'overlay (Lot 5) reste RO et limité à l'agenda.
- **Mineurs** : un compte enfant ne voit que ce que ses parents autorisent (`agenda=view`/`none`) ; aucune donnée d'enfant exposée hors famille (overlay RO = Lot 5).

### 4.10 i18n / localisation

- **Toutes les chaînes** (UI web + Flutter) externalisées, format ICU ; seule `fr-FR` livrée. Ajouter une langue = nouveau fichier de ressources, sans changement de code.
- **Catégories** : les **valeurs** stockées sont des codes stables (`sport`,`musique`,`ecole`,`sante`,`autre`) ; les **libellés** affichés sont localisés.
- **Dates/heures** : formatées selon la locale et le fuseau famille (ICU). Pas de format codé en dur.
- **Pluriels** : « 4 prochains événements », « N occurrences » → ICU plural.
- **Messages d'erreur** (RFC 7807) localisés (`Accept-Language` ou préférence utilisateur).
- **Jour de la semaine / regroupement** : libellés de jour localisés ; premier jour de semaine selon locale (lundi en fr-FR).

### 4.11 Cas spécifiques aux séries

- **Override déplacé hors fenêtre** : un override dont la date affichée tombe dans `[from,to]` mais dont la `recurrence_date` d'origine est hors fenêtre doit quand même apparaître (et inversement, ne pas apparaître si déplacé hors fenêtre). Géré par §3.3 (ajout des overrides dont la date effective ∈ fenêtre).
- **Édition `scope=occurrence` sur un événement non récurrent** : équivalent à édition simple (pas de série) ; `recurrenceDate` ignoré.
- **`scope=series` sur la première occurrence** : pas de scission, modification en place du maître.
- **Série vidée par exclusions** : si tous les overrides/EXDATE annulent toutes les occurrences, la série n'affiche rien (état vide) mais reste éditable (le maître subsiste).
- **Changement de `freq` qui réduit le nombre d'occurrences** : les overrides/EXDATE pointant sur des dates qui ne sont plus des occurrences valides deviennent **orphelins** → ignorés au rendu, purgés à l'édition (documenté).

---

## 5. Modèle de données touché

> Convention globale (cdc §5.2) : tous les entités ont `id` (UUID), `created_at`, `updated_at`. **Aucun champ marqué 🔒 (chiffré au repos) n'est introduit par ce lot** — l'agenda ne porte ni identité ni contenu privé typé (les textes libres ne sont pas listés comme champs 🔒 dans §5.2). En conséquence, **pas de nouvelle entrée obligatoire au registre des données personnelles** au titre du chiffrement ; voir §7 pour la vérification CI.

### 5.1 Entité `Event` (créée par ce lot — cdc §5.2)

| Champ | Type | Contraintes / notes |
|---|---|---|
| `id` | UUID | PK |
| `family_id` | UUID (FK Family) | NOT NULL ; scope famille ; index |
| `title` | varchar(200) | NOT NULL, non vide |
| `category` | enum | `sport` \| `musique` \| `ecole` \| `sante` \| `autre` ; NOT NULL |
| `responsible_membership_id` | UUID (FK Membership) | NOT NULL à la création ; **doit être parent/beau-parent de la famille** ; `ON DELETE SET NULL` (responsable retiré → §4.4) |
| `location` | varchar(255) | NULL |
| `description` | text (≤2000) | NULL |
| `starts_at` | timestamptz | NOT NULL (UTC) |
| `ends_at` | timestamptz | NULL ; si non null `>= starts_at` |
| `all_day` | bool | NOT NULL default false |
| `recurrence` | jsonb | `{ freq: none\|weekly\|biweekly\|monthly, until?: date, count?: int, excludeHolidays: bool }` ; default `{freq:"none"}` |
| `series_id` | UUID | NULL pour ponctuel ; égal pour toutes les occurrences/overrides d'une même série ; index |
| `is_override` | bool | NOT NULL default false ; true = override d'une occurrence (rattaché à `series_id`) |
| `recurrence_date` | date (locale famille) | NULL ; renseigné sur un override/EXDATE = date d'origine de l'occurrence |
| `cancelled` | bool | NOT NULL default false ; true = EXDATE (occurrence exclue) |
| `whole_family` | bool | NOT NULL default false |
| `created_by_membership_id` | UUID (FK Membership) | NOT NULL ; auteur de la création (audit léger applicatif) |
| `version` | int | verrou optimiste (concurrence §4.3) |

> **Note d'implémentation** : la matérialisation des overrides/EXDATE peut, au choix, vivre dans la table `Event` (lignes `is_override`/`cancelled` rattachées à `series_id`) **ou** dans une table dédiée `EventOverride`/`event_exception_date`. Le cdc autorise « matérialisées ou calculées » (§2.5). **Décision verrouillée pour ce lot** : overrides et EXDATE portés par des lignes `Event` rattachées à `series_id` (champs `is_override`, `cancelled`, `recurrence_date`) — un seul modèle, requêtes simples. Les occurrences **virtuelles** ne sont jamais persistées.

### 5.2 Table de liaison `event_child` (créée par ce lot — cdc §5.2)

| Champ | Type | Contraintes |
|---|---|---|
| `event_id` | UUID (FK Event) | NOT NULL ; `ON DELETE CASCADE` |
| `child_id` | UUID (FK Child) | NOT NULL ; doit appartenir à `event.family_id` |
| PK | (`event_id`, `child_id`) | **unicité** (pas de doublon enfant sur un event) |

> Relation **N-N `Event` ↔ `Child`** (cdc ERD : `EVENT }o--o{ CHILD`). Mutuellement exclusive avec `whole_family=true` (§4.6).

### 5.3 Contraintes & invariants de schéma

- **Unicité** : pas d'unicité métier sur `(family_id, starts_at, title)` (deux activités peuvent coïncider) ; unicité technique sur PK uniquement. Pour les overrides : au plus **un** override **non annulé** par `(series_id, recurrence_date)` → **index unique partiel** `UNIQUE(series_id, recurrence_date) WHERE is_override = true`. Pour les EXDATE : au plus un par `(series_id, recurrence_date)` → index unique partiel `WHERE cancelled = true`.
- **Cohérence cible** : contrainte applicative (validation) `whole_family XOR (count(event_child) > 0)`.
- **FK scope** : `family_id`, `responsible_membership_id`, `created_by_membership_id`, `child_id` doivent tous référencer des entités de la **même famille** (validation applicative + tests).
- **Index** : `(family_id, starts_at)` pour la fenêtre temporelle ; `(family_id, category)` pour les filtres ; `(series_id)` ; `(family_id, starts_at) WHERE series_id IS NULL` (ponctuels) — index réels arbitrés par EXPLAIN au Lot 4.

### 5.4 Entités lues (non modifiées) par ce lot

- **`Family`** : `timezone` (regroupement par jour), `id` (scope). Lecture seule.
- **`Membership`** + **`Right`** : résolution du droit `agenda` (Voter), validation responsable. Lecture seule.
- **`Child`** : cible des événements (pastilles). Lecture seule.
- **`SchoolHoliday`** : périodes pour `exclude_holidays`. Lecture seule (entité du Lot 3).

> **Dépenses (`Expense`/`ExpenseShare`)** : non touchées (v2). **Aucune** interaction.

---

## 6. Endpoints API concernés

> Base : REST/JSON sous `/api`, OpenAPI exporté (`/api/docs.json`). Auth : `Authorization: Bearer <jwt>`. **Contexte famille** : header **`X-Family-Id`** obligatoire (validé contre les memberships). Erreurs : **RFC 7807** (`application/problem+json`), messages localisés. **Idempotence** : header `Idempotency-Key` accepté sur les POST (outbox offline). Pagination API Platform (`?page=&itemsPerPage=`) le cas échéant.

### 6.1 Endpoints du lot (cdc §6.2 — section Agenda)

| Méthode | Chemin | Droit requis | Description |
|---|---|---|---|
| `GET` | `/api/families/{id}/events?from=&to=&category=&overlay=` | `agenda:view` | Liste des occurrences sur `[from,to]`, filtrables par `category` (CSV), groupables par jour. `overlay=` **réservé** (Lot 5, ignoré en Lot 4). |
| `POST` | `/api/families/{id}/events` | `agenda:edit` | Crée un événement ponctuel ou une série (champ `recurrence`). Retourne 201 + `id`/`seriesId`. Accepte `Idempotency-Key`. |
| `PATCH` | `/api/events/{id}?scope=occurrence\|series` | `agenda:edit` | Édite une occurrence (override) ou la série (this-and-future + scission §3.5). `scope` requis pour les événements récurrents. |
| `DELETE` | `/api/events/{id}?scope=occurrence\|series` | `agenda:edit` | Supprime une occurrence (EXDATE) ou la série (cascade / this-and-future). |

> **Précision sur l'identifiant** : pour l'édition d'occurrence d'une série, le corps fournit `recurrenceDate` (date d'origine de l'occurrence). `{id}` désigne le maître/`seriesId`. (Détail d'API arbitré à l'implémentation, cohérent avec §3.4/§3.5.)

### 6.2 Endpoint consommé (lecture) hors section agenda

| Méthode | Chemin | Droit | Usage par le Lot 4 |
|---|---|---|---|
| `GET` | (Accueil, rendu serveur web) bloc « À venir » | `agenda:view` | Réutilise la logique §3.7 (les 4 prochains) ; côté API, exploitable via `GET /events?from=now&to=…&itemsPerPage=4` ou un endpoint Accueil agrégé (US-HOME-1, défini au Lot 1/Accueil). |

### 6.3 Exemple — créer une série (cf. cdc §6.3)

```http
POST /api/families/{id}/events
X-Family-Id: {id}
Authorization: Bearer <jwt>
Idempotency-Key: <uuid-outbox>
Content-Type: application/json
{
  "title": "Cours de tennis",
  "category": "sport",
  "responsibleMembershipId": "…",
  "childIds": ["…"],
  "wholeFamily": false,
  "location": "TC Bellevue",
  "startsAt": "<date>T17:30:00+02:00",
  "endsAt":   "<date>T18:30:00+02:00",
  "recurrence": { "freq": "weekly", "until": "<date_fin>", "excludeHolidays": true }
}
→ 201 Created  { "id": "…", "seriesId": "…", … }
```

Erreur droit insuffisant (RFC 7807) :
```http
403  application/problem+json
{ "type":"/errors/forbidden", "title":"Action non autorisée",
  "detail":"Droit 'agenda:edit' requis." }
```

### 6.4 Topic Mercure (cdc §4.5)

| Topic | Publié quand |
|---|---|
| `/families/{id}/agenda` | événement créé / modifié / supprimé (occurrence ou série) — payload Turbo Stream (web) ou JSON (mobile), post-commit. |

---

## 7. Invariants & règles métier (pertinents pour ce lot)

1. **Scope famille obligatoire** : toute requête agenda est scopée par `X-Family-Id`, validé contre les memberships. Aucun événement hors famille active n'est jamais retourné ni modifié (cdc §4.2 ; CLAUDE.md).
2. **Autorisation par Voter `agenda`** : niveaux `none|view|edit`, vérifiés sur **chaque** ressource (liste, création, édition, suppression). Le rôle **parent** force `edit` ; l'**enfant** n'a jamais `edit` (jamais responsable, jamais d'édition de structure). Le beau-parent suit ses `Right`.
3. **Responsable = parent ou beau-parent** de la famille (cdc §2.5, §3.4 CA). Jamais un enfant, jamais un membre d'une autre famille.
4. **Cible enfant(s) ou « toute la famille »** : un événement référence des enfants **de la famille active** ou le flag `whole_family` (exclusifs).
5. **L'agenda ne modifie jamais la garde** (CLAUDE.md, priorité de la garde). Le Lot 4 lit éventuellement `SchoolHoliday` pour `exclude_holidays`, mais n'écrit aucun `CustodyDay`/`CustodyException`/`HolidayRule`.
6. **Pas de données de santé** (cdc §1.4, §7 ; CLAUDE.md) : la catégorie `sante` est un libellé d'agenda, **pas** un champ typé de santé. Aucun champ médical typé n'est introduit. *(Pas de bandeau requis ici : le bandeau anti-santé est sur l'écran d'édition **enfant**, Lot 2 — non rouvert par ce lot.)*
7. **Champs chiffrés 🔒** : ce lot **n'introduit aucun** champ marqué 🔒 (l'agenda ne figure pas dans la liste §5.2 des champs chiffrés). Donc rien à chiffrer côté agenda.
8. **Registre des données personnelles** (CLAUDE.md, cdc §7.1) : aucun **nouveau champ personnel** (au sens identité/contact/contenu privé typé) n'est introduit → en principe pas de nouvelle entrée obligatoire. **Néanmoins**, par prudence et pour passer le test de cohérence CI, **documenter** `Event` (et `event_child`) dans le registre comme « données de coordination familiale, non sensibles, scope famille », avec base légale, finalité, durée de conservation et destinataires (membres de la famille ; famille liée en RO **uniquement à partir du Lot 5**). À faire partie de la DoD.
9. **i18n** : toutes les chaînes UI + (futurs) e-mails externalisées en ICU dès le départ ; seule `fr-FR` livrée ; ajout de langue sans changement de code (cdc §4.6).
10. **Fuseau & dates** : fuseau famille (défaut `Europe/Paris`), instants UTC en base, **aucune date codée en dur** (cdc §10) ; horloge injectable.
11. **Temps réel via Mercure / Turbo Streams** : push HTML (web) / JSON (mobile) post-commit ; abonnements autorisés par claims JWT Mercure ; **aucun JS dédié** côté web (Turbo Streams), conformément à l'architecture verrouillée (CLAUDE.md, cdc §0/§4.3).
12. **Idempotence** : POST tolère `Idempotency-Key` (outbox mobile) ; les opérations de scission/override sont idempotentes (§3, §4.3).
13. **Dépenses masquées en v1** : aucun lien avec l'agenda ; non concerné.
14. **`/infra/INSTALL.md`** : mettre à jour si le lot introduit une dépendance (a priori non — la récurrence est codée maison ; si une lib RRULE est ajoutée, documenter). DoD.
15. **Design system §8** : couleurs de catégories agenda respectées (Sport `#5E7CB8`/`#E4EAF5` · Musique `#9C6FB0`/`#EFE6F4` · École `#C99A3F`/`#F6EDDA` · Santé `#C75D5D`/`#F6E3E3` · Autre `#857E71`/`#ECE5D9`) ; typographies, rayons, surfaces, sidebar (web) et style iOS « liquid glass » (mobile) conformes aux maquettes. La couleur overlay `#9C6FB0` est **réservée** au Lot 5.

---

## 8. Definition of Done (DoD)

### 8.1 Tests

- **Unitaires backend (PHPUnit)** :
  - Moteur d'**expansion** : `weekly`/`biweekly`/`monthly` ; `until` inclusif ; `count` (dont `count=1`) ; clamp mensuel (31 → fin de mois ; 29 fév. bissextile/non) ; bornes `MAX_*`.
  - **DST** : heure murale stable, gap (printemps) → forward shift, overlap (automne) → première occurrence ; années bissextiles.
  - **Overrides/EXDATE** : application, override déplacé hors/dans fenêtre, exclusion, orphelins après changement de `freq`.
  - **Scission `scope=series`** : this-and-future, réaffectation des overrides/EXDATE au pivot, idempotence.
  - **`exclude_holidays`** : soustraction des `SchoolHoliday` ; sans données → sans effet ; override explicite conservé.
  - **« 4 prochains »** : borne = instant courant, extension par paliers, < 4 disponibles.
  - **Voter `agenda`** : matrice none/view/edit × {parent, step_parent, child, admin} × {list, create, patch occurrence, patch series, delete}.
- **Fonctionnels / API (client de test Symfony / API Platform)** :
  - Scope `X-Family-Id` (absent → 400, non-membre → 403, mismatch → 403/404).
  - CRUD complet, codes 201/200/403/404/409/422 et corps RFC 7807 localisés.
  - `Idempotency-Key` : pas de doublon.
  - Validation : titre/catégorie/cible/responsable/intervalle/récurrence.
- **E2E web (Playwright)** :
  - Création d'un événement, création d'une série, filtre par catégorie, vue groupée par jour, édition occurrence vs série, suppression, bloc « À venir » sur l'Accueil.
  - **Temps réel** : deux contextes navigateur → création par l'un visible chez l'autre via Turbo Stream (≤ ~1s).
  - Responsive (desktop/mobile viewports) + accessibilité de base.
- **Mobile (Flutter)** : tests unitaires (mappers occurrences, expansion côté client si applicable), tests de widgets (écran Agenda, formulaire événement, bloc « À venir »), test d'intégration création offline → resynchro outbox (`Idempotency-Key`).

### 8.2 Migrations & schéma

- Migration Doctrine versionnée créant `Event`, `event_child`, index et contraintes (§5). Réversible (`down`). Fixtures agenda reproduisant la famille séparée multi-enfants de la maquette (cdc §4.9) incluant au moins une série hebdo, une mensuelle, un override et un EXDATE.

### 8.3 OpenAPI & client

- OpenAPI à jour (paramètres `from/to/category/overlay`, `scope`, corps `recurrence`, exemples). Client Flutter **régénéré** depuis l'OpenAPI et compilant.

### 8.4 i18n

- Toutes les chaînes agenda (UI web Twig + Flutter ARB) externalisées en ICU, locale `fr-FR` complète (catégories, états vides, erreurs, libellés de jours, pluriels « N prochains événements »). Aucune chaîne en dur.

### 8.5 Registre des données personnelles

- Entrée de registre pour `Event`/`event_child` (catégorie, sensibilité, base légale, finalité, durée de conservation, destinataires : membres de la famille) ajoutée dans `/backend/docs/registre-donnees.md` **et** via attributs déclaratifs sur l'entité. Le **test de cohérence CI** passe (aucun champ personnel sans entrée).

### 8.6 INSTALL.md

- `/infra/INSTALL.md` mis à jour **si** une dépendance est ajoutée (lib de récurrence, etc.). Si aucune dépendance n'est ajoutée, l'absence de changement est confirmée.

### 8.7 Analyse statique & format

- **PHPStan** (niveau élevé) propre, **php-cs-fixer** appliqué ; **`dart analyze`** propre, **`dart format`** appliqué.

### 8.8 Conformité design system (§8)

- Couleurs de catégories agenda exactes ; typographies (`Bricolage Grotesque` titres / `Hanken Grotesk` corps) ; rayons/surfaces ; sidebar repliable (web desktop) ; style iOS « liquid glass » (mobile), navigation par onglets en bas. Vue groupée par jour, filtres et bloc « À venir » fidèles aux maquettes (qui font foi).

### 8.9 Critères d'acceptation du lot (cdc §9 — Lot 4)

- §3.4 couvert (US-AG-1/2/3, +US-AG-5/6/7/8/9 ajoutées) ; **récurrences correctes** (expansion, overrides, scission, exclusion vacances) ; **droits `agenda`** appliqués par le Voter ; **temps réel** agenda fonctionnel (≤ ~1s) ; overlay (US-AG-4) **préparé mais reporté au Lot 5**.

---

*Fin de la spécification — Lot 4 (Agenda).*
