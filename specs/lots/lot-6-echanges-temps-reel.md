# Lot 6 — Échanges (temps réel)

> Document de conception détaillé. Source de vérité : `specs/cdc.md` (notamment §2.6, §3.6, §4.4, §4.5, §5.2, §6.2) et les invariants verrouillés de `CLAUDE.md`. En cas d'ambiguïté de rendu visuel, les maquettes HTML du bundle Claude Design font foi. Ce lot est livré **à la fois en web (Symfony UX / Twig / Turbo / Stimulus)** et **en mobile (Flutter)**, conformément à la DoD générale (cdc §9).

---

## 0. Objectif & périmètre

### 0.1 Objectif
Permettre aux membres habilités d'une famille de **coordonner les sujets relatifs aux enfants** via deux types d'échanges (cdc §2.6) :
- **`discussion`** : un sujet ouvert avec un fil de messages, sans choix figé.
- **`decision`** : un sujet proposant **2 options ou plus** à trancher ; chaque membre habilité **choisit puis valide** une option ; le statut bascule en `decided` avec l'option retenue (`decided_option_id`).

Le lot ajoute la **propagation temps réel ≤ 1 s** (cdc §9, Lot 6) via **Mercure** : nouveaux messages, nouvelles options validées et changements de statut sont poussés en direct — en **Turbo Streams** sur le web (fragments HTML injectés dans le DOM, sans JS dédié) et en **JSON via SSE** sur le mobile (cdc §4.4, §4.5).

### 0.2 Dans le périmètre
- Entité **`Exchange`** (CRUD : création, lecture, liste, clôture), rattachement à un enfant **ou** à « toute la famille ».
- Entité **`ExchangeOption`** (options d'une décision : libellé, sous-libellé, position).
- Entité **`Message`** (fil chronologique, texte **chiffré 🔒**, auteur, horodatage).
- Mécanisme de **vote/validation** d'option (table `option_vote` ou champ `validated_by`, cf. cdc §5.2) et **cycle de vie de la décision** (`open` → `decided`).
- Détection de l'**échéance dépassée** (`deadline` < maintenant, fuseau famille) → signalement « en retard ».
- **Badge de comptage** des échanges ouverts (par famille, dans la navigation).
- **Fan-out temps réel** via Mercure sur les topics `/families/{id}/exchanges` et `/families/{id}/exchanges/{exchangeId}` (cdc §4.4).
- **Autorisation** par le Voter du domaine `exchanges` (`none|view|edit`), sur chaque ressource (cdc §2.2).
- **Endpoints API** de cdc §6.2 (Échanges) + rendu web Twig/Turbo/Stimulus + écran Flutter.
- **i18n fr-FR** de toutes les chaînes UI et notifications visuelles.
- Mise à jour du **registre des données personnelles** pour `Message.text` 🔒 (champ contenu personnel).

### 0.3 Hors périmètre
- **Notifications e-mail** sur message / décision / échéance : c'est le **Lot 7** (NotificationPref + déclencheurs Messenger + rappels d'échéance planifiés). Le présent lot **émet** néanmoins les événements applicatifs (ex. `MessagePosted`, `ExchangeDecided`, `ExchangeOpened`) qui seront consommés au Lot 7 ; il ne câble aucun envoi e-mail.
- **Push mobile** : Lot 10 (v2).
- **Offline riche mobile** (Drift + outbox + résolution de conflits) : l'infrastructure est posée au Lot 0 ; le **parcours hors-ligne validé de bout en bout** est la DoD du **Lot 8**. Le présent lot respecte les conventions outbox (`Idempotency-Key` sur POST, autorité serveur, cf. §3 et §4) mais ne livre pas la validation E2E offline.
- **Dépenses** (v2, Lot 9) : sans rapport.
- **RGPD Manager complet** (anonymiseur, purge, export) : Lot 8. Ce lot se contente de **déclarer** le champ personnel `Message.text` au registre et de respecter le chiffrement applicatif posé au Lot 0/8 ; l'anonymisation effective des messages (« auteur anonymisé ») est implémentée au Lot 8 (cdc §7.1).
- **Overlay des familles liées** : les échanges ne font **pas** partie de l'overlay (qui se limite à l'agenda en lecture seule, cdc §2.1, §5/§6 Lot 5). Aucune fuite d'échange hors de la famille.

### 0.4 Contexte v1 / v2
Les échanges sont une fonctionnalité **v1** (cdc §1.3). Aucune dépendance v2. Le module n'est pas conditionné à un flag d'activation famille (contrairement aux dépenses) : il est disponible pour toute famille, **séparée comme unie** (cdc §1.3 — la ligne « Échanges » est ✅ en v1 sans restriction de type).

---

## 1. Dépendances

### 1.1 Lots prérequis (avant)
| Lot | Raison de la dépendance |
|---|---|
| **Lot 0 — Fondations** | Monorepo, Symfony + API Platform + Doctrine + PostgreSQL, JWT + refresh, squelette Voters, i18n (ICU), **Mercure configuré** (binaire + JWT Mercure), AssetMapper/Turbo/Stimulus, client Flutter généré + abonnement SSE, harnais de tests (PHPUnit, Playwright, `flutter test`), chiffrement applicatif AES-256-GCM des champs 🔒 (mécanisme posé), squelette RGPD Manager + `registre-donnees.md`. **Sans Mercure et sans chiffrement, ce lot ne peut être livré.** |
| **Lot 1 — Familles, membres, rôles & droits, invitations** | Fournit `Family`, `Membership`, `Right`, le **scope `X-Family-Id`** validé contre les memberships, et le **Voter par domaine** (ici `exchanges`). Un échange a un `author_membership_id` et est scopé famille ; le vote/validation s'opère sur `(Membre, Exchange)`. Indispensable. |
| **Lot 2 — Enfants & notes** | Fournit l'entité **`Child`** : un échange peut être rattaché à `child_id` (ou « toute la famille »). Le rattachement enfant exige que `Child` existe pour valider l'appartenance à la famille. Indispensable pour le rattachement à un enfant. |

> Lots 3 (Garde), 4 (Agenda), 5 (Overlay) ne sont **pas** des prérequis fonctionnels stricts des échanges. L'ordre du cdc §9 les place avant, mais le présent lot ne référence ni `CustodyDay` ni `Event`. (Note : la maquette de garde propose une action « Échanger ce jour » qui ouvrirait une exception de garde, cf. cdc §2.4 — c'est un **échange de garde**, pas un `Exchange` au sens de ce lot ; aucun couplage.)

### 1.2 Lots débloqués (après)
| Lot | Ce que ce lot lui apporte |
|---|---|
| **Lot 7 — Notifications e-mail** | Consomme les événements applicatifs émis ici (`MessagePosted`, `ExchangeOpened`, `ExchangeDecided`, échéance approchante/dépassée) pour déclencher les e-mails localisés et les **rappels d'échéance planifiés** (catégorie de notif `exchanges`, cdc §2.9, §5.2 NotificationPref). |
| **Lot 8 — PWA / offline / RGPD** | Valide le parcours **offline mobile** des échanges (poster un message hors-ligne → outbox → rejeu idempotent). Implémente l'**anonymisation** des `Message` (« auteur anonymisé ») préservant l'intégrité du fil (cdc §7.1) et l'inclusion des messages dans l'**export RGPD**. |
| **Lot 10 — Push mobile** | Étend les déclencheurs « échanges » au canal push. |

---

## 2. User stories complètes

> Légende rôles : **parent** (tous droits, `exchanges=edit` forcé), **beau-parent** (`step_parent`, droit `exchanges` au choix `none|view|edit`), **enfant** (`child`, `exchanges` ∈ `view|none`, **jamais `edit`**), **admin** (attribut additionnel d'un parent — sans effet propre sur `exchanges`, déjà `edit` via le rôle parent). Les CA sont en **Étant donné / Quand / Alors**.

### US-EX-1 — Ouvrir un échange (discussion ou décision)
**En tant que** membre habilité (`exchanges=edit`), **je veux** ouvrir une discussion ou une décision rattachée à un enfant ou à toute la famille, avec une échéance optionnelle, **afin de** lancer une coordination tracée.

**Scénario — création nominale d'une discussion**
- **Étant donné** un membre avec `exchanges=edit` sur la famille active F et un header `X-Family-Id = F`,
- **Quand** il poste `POST /api/families/F/exchanges` avec `type=discussion`, `title` non vide, `body` optionnel, `childId` nul (toute la famille), `deadline` optionnelle,
- **Alors** un `Exchange` est créé avec `status=open`, `author_membership_id` = membership de l'appelant, `created_at` horodaté (fuseau famille), réponse `201 Created` avec l'`id`,
- **Et** un événement est publié sur le topic Mercure `/families/F/exchanges` (mise à jour du badge + insertion dans la liste).

**Scénario — création nominale d'une décision (≥ 2 options)**
- **Étant donné** un membre avec `exchanges=edit`,
- **Quand** il crée un `Exchange` `type=decision` avec une liste de **2 options ou plus** (`label` requis, `sub` optionnel, `position` ordonnée),
- **Alors** l'échange est créé `status=open`, `decided_option_id=null`, et les `ExchangeOption` sont persistées dans la transaction,
- **Et** la réponse `201` renvoie l'échange avec ses options ordonnées par `position`.

**Scénario — décision invalide (moins de 2 options)**
- **Étant donné** un membre avec `exchanges=edit`,
- **Quand** il crée un `Exchange` `type=decision` avec 0 ou 1 option,
- **Alors** la requête échoue en `422 Unprocessable Entity` (`application/problem+json`, RFC 7807) avec un message localisé « Une décision requiert au moins deux options. »,
- **Et** aucun échange n'est persisté (transaction annulée).

**Scénario — rattachement à un enfant hors famille**
- **Étant donné** un membre avec `exchanges=edit` sur F,
- **Quand** il crée un échange avec un `childId` qui n'appartient pas à F,
- **Alors** la requête échoue en `422` (ou `404` si l'enfant n'existe pas) avec message localisé, sans fuite d'existence d'un enfant d'une autre famille.

**Scénario — titre vide**
- **Étant donné** un membre habilité,
- **Quand** il crée un échange avec `title` vide ou uniquement des espaces,
- **Alors** `422` avec message localisé « Le titre est obligatoire. ».

**Scénario — autorisation refusée (droit `view` seulement)**
- **Étant donné** un membre avec `exchanges=view` (ex. beau-parent en lecture seule, ou enfant),
- **Quand** il tente `POST /api/families/F/exchanges`,
- **Alors** `403 Forbidden` (`application/problem+json`, `detail` « Droit 'exchanges:edit' requis. ») via le Voter `exchanges`, aucun échange créé.

**Scénario — autorisation refusée (droit `none`)**
- **Étant donné** un membre avec `exchanges=none`,
- **Quand** il liste ou crée un échange,
- **Alors** `403` (le domaine est invisible : la liste renvoie `403`, et l'onglet/le badge ne sont pas rendus côté web).

**Scénario — enfant tente de créer**
- **Étant donné** un membre `child` (au mieux `exchanges=view`),
- **Quand** il tente de créer un échange,
- **Alors** `403` : un enfant n'a **jamais** `edit` (cdc §2.2).

**Scénario — scope famille manquant ou incohérent**
- **Étant donné** un appelant authentifié,
- **Quand** il poste sans `X-Family-Id`, ou avec un `X-Family-Id` ne correspondant pas à un membership actif,
- **Alors** `400` (header manquant) ou `403` (membership absent), conformément au scope famille obligatoire (cdc §4.2, §6.1).

---

### US-EX-2 — Échanger des messages en fil, en temps réel
**En tant que** membre habilité (`exchanges=edit`), **je veux** poster des messages dans le fil d'un échange et voir ceux des autres apparaître en direct, **afin de** discuter sans rafraîchir.

**Scénario — poster un message (nominal)**
- **Étant donné** un membre `exchanges=edit` et un échange E de la famille F,
- **Quand** il poste `POST /api/exchanges/E/messages` avec `text` non vide,
- **Alors** un `Message` est créé : `text` **chiffré 🔒** (AES-256-GCM), `author_membership_id`, `sent_at` horodaté (UTC en base, rendu fuseau famille),
- **Et** la réponse `201` renvoie le message (texte déchiffré pour l'appelant),
- **Et** un événement est publié sur `/families/F/exchanges/E` : le web reçoit un **Turbo Stream** `append` du fragment HTML du message ; le mobile reçoit le JSON via SSE et l'insère dans la liste — propagation **≤ 1 s**.

**Scénario — message vide ou trop long**
- **Étant donné** un membre habilité,
- **Quand** il poste un `text` vide/espaces ou dépassant la longueur maximale configurée (ex. 5000 caractères),
- **Alors** `422` avec message localisé ; aucun message persisté ; aucun fan-out Mercure.

**Scénario — ordre et horodatage du fil**
- **Étant donné** plusieurs messages postés par différents membres,
- **Quand** un membre ouvre le fil,
- **Alors** les messages sont triés par `sent_at` croissant puis par `id` (départage déterministe en cas d'égalité d'horodatage), chacun affichant auteur (couleur du membre, cdc §8) et heure localisée.

**Scénario — temps réel multi-clients**
- **Étant donné** deux membres habilités A et B ayant l'échange E ouvert (web et/ou mobile),
- **Quand** A poste un message,
- **Alors** B voit le message apparaître **sans action**, en ≤ 1 s, via Mercure ; le **compteur de messages** et l'horodatage « dernier message » se mettent à jour.

**Scénario — autorisation refusée (lecture seule)**
- **Étant donné** un membre `exchanges=view`,
- **Quand** il tente de poster un message,
- **Alors** `403` via le Voter ; il peut néanmoins **lire** le fil et **recevoir** les mises à jour temps réel (un abonnement Mercure est autorisé pour `view`).

**Scénario — abonnement Mercure non autorisé**
- **Étant donné** un membre dont le JWT Mercure ne liste pas le topic `/families/F/exchanges/E` (ex. `exchanges=none`, ou membre d'une autre famille),
- **Quand** il tente de s'abonner au topic,
- **Alors** Mercure refuse l'abonnement (le claim ne couvre pas le topic) ; aucune donnée n'est reçue.

**Scénario — Mercure hors-ligne (dégradation web)**
- **Étant donné** que le hub Mercure est indisponible,
- **Quand** un membre est sur l'écran d'un échange,
- **Alors** la lecture et la pose de messages via l'API HTTP restent fonctionnelles (le POST n'échoue pas si le fan-out échoue, cf. §3.7) ; le fil ne se met simplement pas à jour en direct, et un rechargement (Turbo Drive) le réaligne.

---

### US-EX-3 — Prendre une décision (choisir + valider une option)
**En tant que** membre habilité (`exchanges=edit`), **je veux** choisir une option d'une décision puis la valider, et voir le statut passer à « décidé », **afin de** trancher un sujet à plusieurs.

**Scénario — choix + validation (nominal)**
- **Étant donné** une décision E `status=open` avec options O1, O2,
- **Quand** un membre `exchanges=edit` poste `POST /api/exchanges/E/decide` avec `optionId=O1`,
- **Alors** un `option_vote (option_id=O1, membership_id=X)` est enregistré (ou mis à jour, cf. changement de vote),
- **Et** l'événement est publié sur `/families/F/exchanges/E` (« X a validé O1 ») → fan-out temps réel,
- **Et** la règle de bascule (cf. §3.3) est évaluée.

**Scénario — bascule en `decided`**
- **Étant donné** une décision dont la règle de bascule est satisfaite (cf. §3.3 — quorum atteint sur une option),
- **Quand** le dernier vote requis est posé,
- **Alors** `status` passe à `decided`, `decided_option_id` est fixé sur l'option gagnante, dans la **même transaction**,
- **Et** un événement « décision tranchée » est publié sur `/families/F/exchanges/E` **et** `/families/F/exchanges` (le badge des échanges ouverts décrémente),
- **Et** un événement applicatif `ExchangeDecided` est émis (consommé par le Lot 7).

**Scénario — décision déjà tranchée (idempotence / conflit)**
- **Étant donné** une décision E déjà `decided`,
- **Quand** un membre poste `POST /api/exchanges/E/decide`,
- **Alors** `409 Conflict` (`application/problem+json`, « Cette décision est déjà tranchée. ») ; aucun vote enregistré ; l'état canonique renvoyé permet au client (notamment mobile/outbox) de se réaligner.

**Scénario — changement de vote avant bascule**
- **Étant donné** une décision `open` où un membre a déjà voté O1,
- **Quand** ce même membre re-poste `decide` avec `optionId=O2`,
- **Alors** son vote est **déplacé** de O1 vers O2 (un seul vote actif par membre et par décision — unicité `(exchange_id, membership_id)`), fan-out temps réel, la règle de bascule est ré-évaluée.

**Scénario — option inexistante / hors décision**
- **Étant donné** une décision E,
- **Quand** un membre poste `decide` avec un `optionId` qui n'appartient pas à E,
- **Alors** `422` (ou `404`) avec message localisé ; aucun vote.

**Scénario — voter sur une discussion**
- **Étant donné** un échange `type=discussion`,
- **Quand** un membre poste `decide`,
- **Alors** `422` « Cet échange n'est pas une décision. ».

**Scénario — autorisation refusée**
- **Étant donné** un membre `exchanges=view` (ou enfant),
- **Quand** il tente `decide`,
- **Alors** `403` via le Voter ; un enfant ne peut jamais voter.

---

### US-EX-4 — Lister et filtrer les échanges
**En tant que** membre habilité (`exchanges` ∈ `view|edit`), **je veux** lister les échanges de la famille active, filtrés par statut, **afin de** retrouver ce qui est ouvert ou archivé.

**Scénario — liste filtrée par statut**
- **Étant donné** un membre `exchanges=view`,
- **Quand** il appelle `GET /api/families/F/exchanges?status=open` (ou `decided`, ou sans filtre),
- **Alors** la réponse paginée (API Platform : `?page=&itemsPerPage=`) ne contient que les échanges de F correspondant au filtre, triés par défaut **les ouverts d'abord, échéance la plus proche en tête, puis date de création décroissante** (cf. §3 tri),
- **Et** chaque item indique le `type`, le `title`, le rattachement (enfant/famille), l'`author`, la `deadline`, le `status`, le **marqueur « en retard »** si échéance dépassée, le nombre de messages, l'horodatage du dernier message.

**Scénario — liste vide**
- **Étant donné** une famille sans échange,
- **Quand** un membre liste,
- **Alors** `200` avec une collection vide ; côté web, un état vide localisé (« Aucun échange pour le moment »).

**Scénario — autorisation refusée**
- **Étant donné** un membre `exchanges=none`,
- **Quand** il liste,
- **Alors** `403`.

**Scénario — isolation de scope famille**
- **Étant donné** un utilisateur membre de deux familles F1 et F2,
- **Quand** il liste avec `X-Family-Id=F1`,
- **Alors** aucun échange de F2 n'apparaît, même via manipulation d'URL.

---

### US-EX-5 — Consulter le détail d'un échange
**En tant que** membre habilité, **je veux** ouvrir un échange et voir son corps, son fil de messages, ses options (si décision) et l'option retenue, **afin de** comprendre le contexte et l'historique.

**Scénario — détail nominal (décision)**
- **Étant donné** un membre `exchanges=view`,
- **Quand** il appelle `GET /api/exchanges/E`,
- **Alors** la réponse contient l'échange, ses options ordonnées avec le **décompte des validations** par option, l'`decided_option_id` si tranché, le fil de messages (textes déchiffrés), l'auteur, la `deadline` et le marqueur « en retard » le cas échéant.

**Scénario — détail d'un échange d'une autre famille**
- **Étant donné** un membre de F1,
- **Quand** il appelle `GET /api/exchanges/E` où E appartient à F2,
- **Alors** `403` (ou `404` pour ne pas révéler l'existence) ; jamais de fuite inter-familles.

**Scénario — autorisation refusée**
- **Étant donné** un membre `exchanges=none`,
- **Quand** il appelle le détail,
- **Alors** `403`.

---

### US-EX-6 — Clôturer / éditer un échange
**En tant que** membre habilité (`exchanges=edit`), **je veux** clôturer un échange (ou éditer ses méta-données), **afin de** marquer un sujet comme traité.

> Le cdc §6.2 expose `GET/PATCH /api/exchanges/{id}` avec la description « détail/clôture ». La « clôture » d'une **discussion** la fait passer en `status=decided` (terme générique « clos/tranché ») ; une **décision** ne se clôt normalement que via `decide` (bascule) — mais un membre `edit` peut forcer la clôture (cf. §3.3, qui tranche en dernier ressort).

**Scénario — clôture d'une discussion**
- **Étant donné** une discussion `open`,
- **Quand** un membre `exchanges=edit` poste `PATCH /api/exchanges/E` avec `status=decided`,
- **Alors** l'échange est marqué clos, fan-out sur `/families/F/exchanges` (badge décrémente) et `/families/F/exchanges/E`,
- **Et** pour une discussion `decided_option_id` reste `null`.

**Scénario — édition d'échéance**
- **Étant donné** un échange `open`,
- **Quand** un membre `edit` modifie `deadline` (ajout, modification, suppression),
- **Alors** la nouvelle échéance est persistée ; le marqueur « en retard » est ré-évalué ; fan-out temps réel.

**Scénario — réouverture interdite par défaut**
- **Étant donné** un échange `decided`,
- **Quand** un membre tente de le repasser `open`,
- **Alors** comportement par défaut : `409`/`422` « Un échange tranché ne peut être rouvert. » (décision de conception conservatrice ; à confirmer produit — non requise par le cdc).

**Scénario — autorisation refusée**
- **Étant donné** un membre `exchanges=view`,
- **Quand** il tente `PATCH`,
- **Alors** `403`.

---

### US-EX-7 — Badge de comptage des échanges ouverts
**En tant que** membre habilité, **je veux** voir un badge indiquant le nombre d'échanges ouverts de la famille active, **afin de** repérer ce qui reste à traiter (cdc §2.6, §3.6).

**Scénario — affichage initial**
- **Étant donné** une famille F avec N échanges `open` visibles par le membre,
- **Quand** le membre charge l'application,
- **Alors** le badge de navigation « Échanges » affiche N (formaté i18n ; éventuel « 9+ » au-delà d'un seuil d'affichage).

**Scénario — mise à jour temps réel à l'ouverture**
- **Étant donné** un membre avec le badge affichant N,
- **Quand** un autre membre ouvre un nouvel échange,
- **Alors** le badge passe à N+1 en ≤ 1 s via le topic `/families/F/exchanges` (Turbo Stream `update` du fragment badge côté web ; recalcul côté mobile).

**Scénario — décrément à la clôture/décision**
- **Étant donné** un badge à N,
- **Quand** un échange est tranché ou clos,
- **Alors** le badge passe à N-1 en ≤ 1 s.

**Scénario — badge par famille active**
- **Étant donné** un membre de F1 et F2,
- **Quand** il bascule de F1 (badge 3) vers F2 (badge 0),
- **Alors** le badge reflète la **famille active** sélectionnée ; pas de mélange inter-familles.

**Scénario — masquage si non habilité**
- **Étant donné** un membre `exchanges=none`,
- **Quand** il charge l'app,
- **Alors** ni l'onglet « Échanges » ni le badge ne sont rendus.

---

### US-EX-8 — Signalement d'échéance dépassée (« en retard »)
**En tant que** membre habilité, **je veux** voir clairement quels échanges (notamment décisions) ont dépassé leur échéance sans être tranchés, **afin de** les prioriser (cdc §2.6, §3.6).

**Scénario — décision en retard**
- **Étant donné** une décision `open` avec `deadline` dans le passé (instant courant > fin de journée d'échéance, fuseau famille),
- **Quand** un membre consulte la liste ou le détail,
- **Alors** l'échange porte un marqueur visuel « en retard » (texte + style accent, cf. §8) et un libellé localisé.

**Scénario — échéance non dépassée**
- **Étant donné** une décision `open` avec `deadline` aujourd'hui ou future,
- **Quand** un membre consulte,
- **Alors** aucun marqueur « en retard ».

**Scénario — décision tranchée même si échéance passée**
- **Étant donné** une décision `decided` dont l'échéance est passée,
- **Quand** un membre consulte,
- **Alors** pas de marqueur « en retard » (tranchée prime).

**Scénario — passage en retard sans action (bord d'échéance)**
- **Étant donné** une décision `open` dont l'échéance arrive à expiration,
- **Quand** l'instant courant franchit la fin de l'échéance,
- **Alors** au prochain rendu (chargement / Turbo refresh / requête API) le marqueur « en retard » apparaît. *(Le passage temps réel sans interaction côté Lot 6 n'est pas garanti à la seconde près — il s'évalue au rendu ; le rappel actif d'échéance est un job du Lot 7.)*

---

## 3. Algorithme(s)

> Conventions transverses : tous les instants sont **stockés en UTC** (Doctrine `timestamptz`), affichés/évalués dans le **fuseau de la famille** (`Family.timezone`, défaut `Europe/Paris`, cdc §2.1, §10). La date de référence des maquettes (23/06/2026) **n'est jamais codée en dur** (cdc §10) — on utilise une horloge injectable (`ClockInterface`) pour la testabilité. Toute mutation est **transactionnelle** ; le fan-out Mercure est **post-commit** (cf. §3.7).

### 3.1 Création d'un échange
```
POST /api/families/{F}/exchanges  (Voter exchanges:edit sur F)
1. Valider X-Family-Id == {F} et membership actif de l'appelant sur F.    // scope famille
2. Voter::vote(exchanges, EDIT, (membre, F))  → sinon 403.
3. Valider la charge utile :
   - title non vide (trim).
   - type ∈ {discussion, decision}.
   - childId : nul OU appartient à F (sinon 422/404, sans fuite).
   - deadline : nulle OU date valide (normalisée fin de journée fuseau famille).
   - si type=decision : options.length >= 2, chaque label non vide.
4. BEGIN TRANSACTION
   - INSERT Exchange { family=F, type, title, body, author=membership(appelant),
                       child_id, deadline, status=open, decided_option_id=null }.
   - si decision : INSERT ExchangeOption[] avec position = index (0..n-1).
   COMMIT
5. POST-COMMIT : publier Mercure sur /families/{F}/exchanges
   (Turbo Stream: prepend de la carte + update du badge ; JSON SSE mobile).
6. Émettre l'événement applicatif ExchangeOpened (consommé Lot 7).
7. 201 Created { exchange + options }.
```
Complexité : O(k) pour k options. Idempotence : si `Idempotency-Key` fourni (POST issu de l'outbox mobile), la clé est mémorisée (clé → réponse) ; un rejeu renvoie la réponse initiale sans recréer (cf. cdc §6.1, §4.4).

### 3.2 Poster un message
```
POST /api/exchanges/{E}/messages  (Voter exchanges:edit sur la famille de E)
1. Charger E ; en déduire family F ; valider X-Family-Id == F et membership actif.
2. Voter::vote(exchanges, EDIT, (membre, E))  → sinon 403.
3. Valider text : non vide (trim), longueur <= MAX (ex. 5000).
4. (Optionnel produit) refuser si E.status=decided ? → décision de conception :
   on AUTORISE les messages après décision (un fil reste consultable/commentable).
   // Ne pas bloquer : le cdc ne ferme pas le fil après décision.
5. BEGIN TRANSACTION
   - text_chiffré = AES-256-GCM(text)            // 🔒 chiffrement applicatif
   - INSERT Message { exchange=E, author=membership(appelant),
                      text=text_chiffré, sent_at=clock.now() (UTC) }.
   COMMIT
6. POST-COMMIT : publier Mercure sur /families/F/exchanges/{E}
   (Turbo Stream: append du fragment message au fil ; JSON SSE mobile).
   // Le payload Mercure NE contient PAS le texte chiffré brut : le serveur rend
   // le fragment HTML (web) ou le JSON (mobile) avec le texte EN CLAIR, déchiffré,
   // poussé uniquement aux abonnés autorisés du topic (JWT Mercure).
7. Émettre MessagePosted (consommé Lot 7).
8. 201 Created { message (texte en clair) }.
```
Complexité : O(1). Le déchiffrement n'a lieu que pour produire le fragment/JSON destiné aux abonnés autorisés.

### 3.3 Cycle de vie d'une décision (open → choix → validation → decided)

**Modèle de vote.** Chaque membre habilité a **au plus un vote actif** par décision (unicité `(exchange_id, membership_id)` sur `option_vote`). « Choisir puis valider » (cdc §2.6) est modélisé en **une opération atomique `decide`** : l'enregistrement (ou le déplacement) du vote vaut validation du choix de ce membre. Le sous-libellé UI « choisir » puis « valider » peut être présenté en deux temps côté client, mais l'API n'enregistre que la validation finale (POST `decide`).

**Population « habilitée à trancher » (électorat).** Sont comptés les memberships **actifs** de la famille ayant `exchanges=edit` (parents — `edit` forcé — et beaux-parents habilités). Les membres `view`/`none` et les enfants n'entrent pas dans l'électorat. On exclut les memberships `status != active`.

**Règle de bascule (quorum) — décision de conception conforme au cdc.**
Le cdc §2.6 dit : « chaque membre habilité choisit puis valide ; statut passe à `decided` avec l'option retenue ». L'interprétation retenue, conservatrice et explicite :

> **Bascule par consensus des votants habilités sur une même option.** La décision passe `decided` dès que **tous les membres habilités ayant le droit de trancher** ont voté **et** qu'une option recueille la **majorité** des votes ; en cas d'égalité, la décision **reste `open`** (pas de tie-break automatique). Pour le cas usuel d'une famille séparée à deux parents, cela revient à « les deux parents ont validé la même option ».

Détail du cas à 2 parents (le plus fréquent) : la décision bascule quand **les deux** ont voté la **même** option (consensus). S'ils votent des options différentes, elle **reste ouverte** et signale le désaccord (les deux votes visibles). Un membre `edit` peut alors trancher manuellement via la **clôture explicite** en désignant l'option (cf. ci-dessous) — c'est le « qui tranche en dernier ressort ».

**Clôture explicite (qui tranche en dernier ressort).** Pour éviter le blocage (égalité, ou électorat > 2 sans consensus), un membre `exchanges=edit` peut **forcer** la décision via `PATCH /api/exchanges/E { status: decided, decidedOptionId: O }`. Cette voie est tracée (`AuditLog`) et exige `decidedOptionId` ∈ options de E.

```
POST /api/exchanges/{E}/decide { optionId: O }   (Voter exchanges:edit)
1. Charger E (SELECT ... FOR UPDATE sur la ligne Exchange → verrou anti-concurrence).
2. Garde : E.type == decision sinon 422 ; E.status == open sinon 409 (déjà tranchée).
3. Garde : O ∈ options(E) sinon 422/404.
4. BEGIN TRANSACTION
   - UPSERT option_vote (exchange=E, membership=appelant) → option_id = O
     (déplace le vote existant le cas échéant ; unicité (exchange, membership)).
   - electorate = memberships actifs de F avec exchanges=edit (calcul effectif droits).
   - votes = option_votes(E) ; tally = comptage par option.
   - SI |votes| == |electorate| ET ∃ option Omax avec tally[Omax] strictement maximal :
        E.status = decided ; E.decided_option_id = Omax.
     SINON : E reste open.
   COMMIT  (libère le verrou)
5. POST-COMMIT : publier Mercure sur /families/F/exchanges/{E}
   ("X a validé O" ; si bascule : "Décision tranchée : Omax").
   SI bascule : publier AUSSI sur /families/F/exchanges (badge --).
6. SI bascule : émettre ExchangeDecided (consommé Lot 7).
7. 200 OK { exchange (status, decided_option_id), tally par option }.
```
**Idempotence & concurrence.** Le `SELECT ... FOR UPDATE` (verrou pessimiste ligne `Exchange`) sérialise deux `decide` concurrents : le premier qui obtient le verrou peut faire basculer la décision ; le second, en relisant `status`, voit `decided` → `409`. Avec `Idempotency-Key` (outbox mobile), un rejeu du même `decide` renvoie la réponse mémorisée. Le re-vote du **même** membre pour la **même** option est idempotent (UPSERT sans changement d'état). Complexité : O(e) pour e = taille électorat (petite, typiquement 2).

### 3.4 Détection de l'échéance dépassée (« en retard »)
```
estEnRetard(exchange, now, tzFamille) :
  si exchange.deadline == null      → false
  si exchange.status   == decided   → false
  // deadline est une DATE ; l'échéance court jusqu'à la FIN de cette journée,
  // dans le fuseau de la famille.
  finEcheance = endOfDay(exchange.deadline, tzFamille)   // 23:59:59.999 tz
  retourner now > finEcheance
```
- Évaluation **au rendu** (liste, détail, badge éventuel) et côté client à l'affichage. Pas de matérialisation d'un flag « en retard » en base (dérivé, recalculé). Le **rappel actif** (e-mail / planifié) relève du Lot 7.
- `now` provient de l'horloge injectable (UTC), comparé après conversion cohérente. Gérer les transitions heure été/hiver via la bibliothèque de fuseau (cf. §4 edge cases).

### 3.5 Comptage des échanges ouverts (badge)
```
compterOuverts(famille F, membre M) :
  // ne compter que ce que M peut voir (exchanges >= view) ; sinon badge non rendu.
  retourner COUNT(Exchange WHERE family=F AND status=open)
```
- **Lecture web** : valeur rendue dans le layout au chargement (Twig), puis maintenue par Turbo Stream sur le topic `/families/F/exchanges` (fragment badge ciblé par `id` DOM stable).
- **Mobile** : valeur initiale par `GET .../exchanges?status=open` (ou champ agrégé exposé) puis recalcul à réception d'événements SSE (création/clôture).
- Complexité : O(1) en base avec un index sur `(family_id, status)`. Optionnellement, exposer le compteur dans un en-tête/attribut de la collection pour éviter une requête séparée.
- **Cohérence du compte** : le compteur est dérivé de `status` ; pas de double comptage car `status` est binaire (`open`/`decided`).

### 3.6 Tri & pagination des listes
- **Fil de messages** (détail) : `ORDER BY sent_at ASC, id ASC` (déterministe). Pagination possible si long fil (charger les N derniers, « voir plus » vers le haut).
- **Liste d'échanges** : par défaut **ouverts d'abord** (`status=open` avant `decided`), puis **échéance la plus proche en tête** (`deadline ASC NULLS LAST`), puis `created_at DESC`. Le filtre `?status=` restreint l'ensemble. Pagination API Platform (`page`, `itemsPerPage`).

### 3.7 Fan-out temps réel via Mercure (post-commit)
**Principe.** La publication Mercure se fait **après commit** de la transaction métier (via un listener sur l'événement Doctrine post-commit / un middleware Messenger, ou un `kernel.terminate`/`Mercure` update dispatché après flush). On ne publie **jamais** avant que l'état soit durable, pour éviter d'annoncer un message/une décision qui serait ensuite annulé.

**Topics (cdc §4.4) et déclencheurs :**
| Topic | Déclencheur | Charge web (Turbo Stream) | Charge mobile (JSON SSE) |
|---|---|---|---|
| `/families/{F}/exchanges/{E}` | nouveau message | `turbo-stream action=append target="messages-E"` + fragment message (texte **en clair**) | `{ type:"message", message:{...,text en clair} }` |
| `/families/{F}/exchanges/{E}` | vote/validation d'option | `turbo-stream action=update target="options-E"` (décomptes) | `{ type:"vote", optionId, tally }` |
| `/families/{F}/exchanges/{E}` | changement de statut (decided) | `update target="status-E"` + bandeau décision | `{ type:"status", status:"decided", decidedOptionId }` |
| `/families/{F}/exchanges` | création / clôture / décision | `update target="exchanges-badge"` (+ `prepend`/`remove` carte liste) | `{ type:"exchange_list", op:"created|closed", openCount }` |

**Autorisation des abonnements (cdc §4.5).** Le **JWT Mercure** délivré au client liste, dans ses *claims*, les topics correspondant aux familles du membre, restreints au domaine `exchanges` selon le droit : un membre `exchanges>=view` reçoit les topics de ses familles ; un membre `none` ne les a pas. Le hub Mercure n'autorise que les abonnements couverts par le claim → pas de fuite inter-familles ni vers un membre non habilité. **Le texte des messages n'est poussé qu'en clair, exclusivement aux abonnés autorisés** ; il n'est jamais publié sur un topic accessible à un non-habilité.

**Robustesse.** Si la publication Mercure échoue (hub indisponible), l'API **n'échoue pas** (le fan-out est best-effort post-commit) : l'état est durable, et les clients se réaligneront au prochain chargement/refresh. On journalise l'échec de publication pour observabilité.

### 3.8 Chiffrement de `Message.text` 🔒
- Chiffrement **applicatif AES-256-GCM** via l'enveloppe de clé gérée par le coffre/KMS UE (mécanisme posé au Lot 0/8, cdc §5.3) — type Doctrine custom / transformer sur le champ `text`.
- En base : `text` stocké chiffré (nonce + tag + ciphertext) → **un dump SQL brut ne révèle aucun contenu** (invariant CLAUDE.md).
- Déchiffrement uniquement en mémoire applicative, pour : (a) la réponse API à un appelant autorisé, (b) le rendu du fragment Turbo / JSON SSE poussé aux abonnés autorisés.
- **Recherche plein-texte non supportée** sur les messages (champ chiffré) — non requis par le cdc.
- Le **registre des données personnelles** reçoit une entrée pour `Message.text` (catégorie « contenu », sensibilité 🔒, finalité « coordination co-parentale », base légale, durée de conservation, destinataires = membres habilités de la famille) — DoD du lot.

---

## 4. Cas limites (edge cases)

### 4.1 Dates, fuseaux, heure été/hiver, années bissextiles
- **Échéance = date sans heure.** `deadline` est une **date** (cdc §5.2). L'« en retard » court jusqu'à **fin de journée** (`23:59:59.999`) dans le fuseau famille → un échange dont l'échéance est « aujourd'hui » n'est pas en retard avant minuit local.
- **Fuseau famille ≠ fuseau serveur.** Toujours évaluer « en retard » et afficher les horodatages dans `Family.timezone` (défaut `Europe/Paris`), pas dans le fuseau serveur (UTC). Stockage UTC, conversion à l'affichage.
- **Passage heure d'été/hiver.** `endOfDay` calculé via la bibliothèque de fuseau (gère le décalage DST) — un jour de bascule a 23 h ou 25 h mais « fin de journée » reste l'instant 23:59:59.999 local converti en UTC. Tester autour des dates de changement d'heure (dernier dimanche de mars / octobre).
- **Année bissextile / 29 février.** Une échéance au 29 février d'une année bissextile est valide ; la création d'une échéance sur une date inexistante (saisie aberrante) est rejetée à la validation.
- **`sent_at` au franchissement de minuit.** Le regroupement visuel du fil par jour (si la maquette le prévoit) utilise la date locale famille, pas UTC.
- **Horloge.** Aucune date codée en dur (pas de 23/06/2026) ; `ClockInterface` injectée pour les tests (gel du temps autour des bornes).

### 4.2 Concurrence & idempotence
- **Deux `decide` simultanés** : sérialisés par `SELECT ... FOR UPDATE` sur l'`Exchange` ; le second voit `decided` → `409`.
- **Deux messages simultanés** : aucun verrou nécessaire (insertions indépendantes) ; l'ordre du fil est garanti par `sent_at, id`.
- **Re-vote identique** (même membre, même option) : idempotent (UPSERT no-op).
- **Rejeu d'un POST avec `Idempotency-Key`** (outbox mobile) : renvoie la réponse mémorisée, ne recrée pas (message, échange, vote).
- **Création concurrente du même échange depuis l'outbox** (double rejeu sans clé) : sans `Idempotency-Key`, deux échanges peuvent être créés → recommander la clé sur les POST issus de l'outbox (cf. §6 conventions).
- **Bascule décision pendant qu'un message est posté** : indépendants ; les deux fan-outs partent post-commit ; cohérence finale assurée.

### 4.3 Autorisations (parent / beau-parent / enfant / admin, dernier admin)
- **Parent** : `exchanges=edit` **forcé** (cdc §2.2) — peut tout faire (créer, poster, voter, clôturer, forcer décision).
- **Beau-parent `edit`** : idem parent sur le domaine `exchanges` ; **compte dans l'électorat** d'une décision.
- **Beau-parent `view`** : lit, reçoit le temps réel, **ne poste pas / ne vote pas** (`403` sur edit) ; **hors électorat**.
- **Beau-parent `none`** : domaine invisible (`403`, pas d'onglet/badge, pas de topic Mercure).
- **Enfant `view`** : lit et reçoit le temps réel ; **jamais** `edit` (poster/voter/créer/clôturer → `403`) ; hors électorat.
- **Enfant `none`** : domaine invisible.
- **Admin** : l'attribut admin ne change rien à `exchanges` (déjà `edit` car admin ⇒ parent). Aucune règle « dernier admin » spécifique aux échanges — **mais** la **protection du dernier admin** (cdc §2.2, CLAUDE.md) reste un invariant global non violé par ce lot (le lot ne retire ni ne rétrograde de membership).
- **Auteur vs autres** : l'auteur d'un échange n'a **pas** de privilège supplémentaire ; tout membre `edit` peut poster/voter/clôturer (coordination co-parentale). (Optionnel produit : restreindre la clôture à l'auteur — non requis ; on n'ajoute pas cette restriction.)
- **Voter sur chaque ressource** : l'autorisation est vérifiée sur **chaque** endpoint (liste, détail, message, decide, patch), jamais seulement au niveau famille.

### 4.4 Scope famille (`X-Family-Id`)
- **Header absent** sur une route scopée famille → `400`.
- **Header présent mais membership inactif/inexistant** → `403`.
- **`X-Family-Id` ≠ famille de la ressource** (ex. `POST /api/exchanges/E/messages` où E ∈ F2 mais header = F1) → `403`/`404` (ne pas révéler).
- **Bascule de famille active** : le badge, la liste et les abonnements Mercure suivent la famille active ; aucun mélange.
- **Utilisateur multi-familles** : isolation stricte ; un échange n'est jamais visible hors de sa famille (pas d'overlay pour les échanges).

### 4.5 Valeurs vides / limites
- **Titre vide / espaces** → `422`.
- **Body** optionnel (peut être vide) pour discussion **et** décision.
- **Décision avec 0/1 option** → `422`.
- **Décision avec options de libellé vide** → `422`.
- **Option `sub` vide** : autorisé (optionnel).
- **Doublons d'options** (mêmes `label`) : autorisés techniquement, mais on **avertit** ou interdit selon décision produit ; par défaut on n'interdit pas (un même libellé peut être volontaire) — à confirmer.
- **`deadline` nulle** : autorisée (échange sans échéance, jamais « en retard »).
- **`deadline` dans le passé à la création** : autorisée mais immédiatement « en retard » (signalé).
- **Message vide** → `422` ; **message très long** (> MAX) → `422`.
- **Fil vide** (échange sans message) : rendu d'un état vide localisé.
- **Décision sans aucun vote** : reste `open` ; affichage « aucune validation ».
- **Électorat de taille 1** (famille mono-parent habilité, cas dégénéré) : un seul vote fait basculer (|votes|==|electorate|==1, option max trivialement atteinte).
- **Électorat vide** (aucun membre `edit` — situation théorique, un parent a toujours `edit`) : la décision ne peut jamais basculer par quorum ; seule la clôture explicite par un `edit` est possible (mais s'il n'y a aucun `edit`, personne ne peut décider — situation impossible car parent ⇒ edit).

### 4.6 Échecs externes (e-mail, API vacances, Mercure hors-ligne)
- **Mercure indisponible** : voir §3.7 — l'API reste fonctionnelle, fan-out best-effort, journalisation de l'échec, réalignement au refresh. Le web **dégrade** (pas de live), conforme à l'offline web dégradé (cdc §4.3).
- **E-mail** : hors périmètre du lot (Lot 7) ; aucun couplage ici.
- **API vacances** : sans rapport avec ce lot.
- **JWT Mercure expiré côté client** : le client renouvelle son JWT (mécanisme Lot 0) puis se réabonne ; pendant l'expiration, pas de live (dégradation), réalignement au refresh.

### 4.7 Conflits offline (mobile)
- **Poster un message hors-ligne** : mis en outbox avec `Idempotency-Key` ; rejoué à la reconnexion (cdc §4.4). Le serveur fait autorité ; un message posté hors-ligne arrive avec son `sent_at` **serveur** à la réception (ou conserve l'horodatage client si le produit le décide — par défaut **autorité serveur** : `sent_at` = réception serveur). Statut « en attente de synchro » visible côté mobile.
- **Voter/décider hors-ligne puis conflit** : si la décision a été tranchée entre-temps par d'autres, le rejeu `decide` reçoit `409` ; le mobile **réaligne** sur l'état canonique renvoyé et **signale** à l'utilisateur (cdc §4.4 : « refus possible si conflit, signalé à l'utilisateur »).
- **Créer un échange hors-ligne** : outbox + `Idempotency-Key` pour éviter les doublons au rejeu.
- **Lecture hors-ligne** : le cache Drift permet de relire les échanges/messages déjà synchronisés (lecture seule), les mutations attendent la connexion.

### 4.8 Sécurité / RGPD
- **Chiffrement `Message.text` 🔒** : jamais en clair en base ni dans un dump (invariant). Le payload Mercure poussé contient le clair **uniquement** vers les abonnés autorisés (topic protégé par JWT).
- **Pas de données de santé** : un échange/message est du **texte libre** ; aucun champ typé santé n'est introduit. (Aucun bandeau anti-santé requis spécifiquement ici — il concerne l'édition d'enfant — mais on n'incite pas à saisir des données sensibles.)
- **Journalisation `AuditLog`** : la **clôture/forçage de décision** et le **changement de droits** (hors lot) sont sensibles ; on journalise la **clôture explicite** d'un échange (qui tranche) et la **bascule en decided** (action, entité, acteur, famille). Les messages individuels ne sont pas nécessairement audités (volume), mais la création/clôture d'échange l'est selon la politique (cdc §5.4 — append-only).
- **Anonymisation future (Lot 8)** : à la suppression d'un compte, l'auteur d'un message devient « auteur anonymisé » sans effacer le fil (cdc §7.1). Le modèle doit donc tolérer un `author_membership_id` pointant vers un membership anonymisé (ne pas casser sur `NULL`/membership neutre) — **prévoir dès maintenant** que l'affichage gère « membre supprimé ».
- **Tokens / fuites** : pas de fuite d'existence d'un échange/enfant d'une autre famille (`404` plutôt que `403` quand révéler l'existence est en soi une fuite).

### 4.9 i18n / localisation
- Toutes les chaînes UI (labels, états vides, « en retard », « décidé », pluriels du badge, libellés d'erreurs RFC 7807) **externalisées** en ICU, locale `fr-FR` livrée (cdc §4.6). Ajouter une langue = nouveau fichier, **aucun changement de code**.
- **Pluriels ICU** sur le badge (« 1 échange », « N échanges ») et les décomptes de validations.
- **Dates/heures localisées** dans le fuseau famille et la locale (format français).
- **Données indépendantes de la langue** : `type`, `status`, `category` (n/a ici) stockés en valeurs canoniques, traduits à l'affichage.
- **E-mails localisés** : Lot 7 (templates Twig localisés) — pas dans ce lot.

### 4.10 Cohérence du fan-out et du badge
- **Double comptage** : éviter qu'une même bascule publie deux fois le décrément du badge (un seul événement `/families/F/exchanges` par bascule).
- **Désynchronisation badge** : si un client a manqué un événement (déconnexion brève), le **rechargement** (Turbo Drive / requête mobile) réaligne ; le compteur n'est jamais source de vérité, il dérive de `COUNT(status=open)`.
- **Cibles DOM stables** : les fragments Turbo ciblent des `id` stables (`messages-{E}`, `options-{E}`, `status-{E}`, `exchanges-badge`) pour des `append/update/remove` fiables.

---

## 5. Modèle de données touché

> Base : cdc §5.2. Tous les entités ont `id` (UUID), `created_at`, `updated_at`. Champ 🔒 = chiffré au repos (AES-256-GCM applicatif, cdc §5.3).

### 5.1 Entités créées par ce lot
**`Exchange`** (échange : discussion ou décision)
| Champ | Type | Notes |
|---|---|---|
| `id` | UUID | PK |
| `family_id` | FK → Family | **scope famille** ; index `(family_id, status)` |
| `type` | enum `discussion` \| `decision` | |
| `title` | string non vide | |
| `body` | text | optionnel |
| `author_membership_id` | FK → Membership | auteur ; tolère membership anonymisé (Lot 8) |
| `child_id` | FK → Child, nullable | rattachement à un enfant **ou** « toute la famille » si `null` |
| `deadline` | date, nullable | échéance ; « en retard » dérivé (fuseau famille) |
| `status` | enum `open` \| `decided` | défaut `open` |
| `decided_option_id` | FK → ExchangeOption, nullable | renseigné uniquement pour une décision tranchée |
| `created_at`, `updated_at` | timestamptz | UTC |

**`ExchangeOption`** (option d'une décision)
| Champ | Type | Notes |
|---|---|---|
| `id` | UUID | PK |
| `exchange_id` | FK → Exchange | cascade delete avec l'échange ; options ≥ 2 si decision |
| `label` | string non vide | |
| `sub` | string | sous-libellé optionnel |
| `position` | int | ordre d'affichage ; index `(exchange_id, position)` |

**`Message`** (fil de l'échange)
| Champ | Type | Notes |
|---|---|---|
| `id` | UUID | PK |
| `exchange_id` | FK → Exchange | index `(exchange_id, sent_at)` |
| `author_membership_id` | FK → Membership | tolère membership anonymisé (Lot 8) |
| `text` 🔒 | text chiffré | **AES-256-GCM** ; jamais en clair en base |
| `sent_at` | timestamptz | UTC ; ordre du fil |
| `created_at`, `updated_at` | timestamptz | |

**`option_vote`** (validation d'une option par un membre — cf. cdc §5.2 « table `option_vote(option_id, membership_id)` ou champ `validated_by` »). **Choix retenu : table `option_vote`** (plus expressif pour le décompte et le re-vote).
| Champ | Type | Notes |
|---|---|---|
| `id` | UUID | PK |
| `exchange_id` | FK → Exchange | redondance utile pour l'unicité « un vote par membre par décision » et le décompte |
| `option_id` | FK → ExchangeOption | option choisie |
| `membership_id` | FK → Membership | votant (doit être `exchanges=edit`) |
| `created_at`, `updated_at` | timestamptz | |

> Décision de conception : on stocke `exchange_id` **et** `option_id` dans `option_vote` pour porter l'**unicité `(exchange_id, membership_id)`** (un seul vote actif par membre et par décision) tout en permettant le décompte par option. Le changement de vote = UPDATE de `option_id` sur la ligne existante.

### 5.2 Contraintes d'unicité & intégrité
- `option_vote` : **UNIQUE `(exchange_id, membership_id)`** → un membre = un vote actif par décision.
- `ExchangeOption` : unicité logique `(exchange_id, position)` recommandée (ordre stable).
- `Exchange.decided_option_id` : doit référencer une option de **ce** `exchange_id` (contrainte applicative + FK) ; `NULL` si `status=open` ou `type=discussion`.
- `Exchange` : si `type=decision`, **≥ 2 options** (contrainte applicative, vérifiée en service/validation, non exprimable simplement en SQL).
- `Message.text` : longueur max applicative (ex. 5000) ; non vide.
- Suppression d'un `Exchange` (si jamais exposée — non requise par le cdc) : cascade sur options, messages, votes.

### 5.3 Relations (ERD cdc §5.1, périmètre lot)
```
FAMILY ||--o{ EXCHANGE
EXCHANGE ||--o{ EXCHANGE_OPTION
EXCHANGE ||--o{ MESSAGE
EXCHANGE }o--o| CHILD            (child_id nullable = "toute la famille")
EXCHANGE }o--|| MEMBERSHIP       (author)
MESSAGE  }o--|| MEMBERSHIP       (author)
EXCHANGE ||--o{ OPTION_VOTE      (votes)
OPTION_VOTE }o--|| EXCHANGE_OPTION
OPTION_VOTE }o--|| MEMBERSHIP    (votant)
EXCHANGE }o--o| EXCHANGE_OPTION  (decided_option_id, nullable)
```

### 5.4 Champs personnels / registre
- **`Message.text`** 🔒 → **nouvelle entrée** au registre des données personnelles (`/backend/docs/registre-donnees.md` + attribut déclaratif sur l'entité) : catégorie « contenu », sensibilité chiffrée 🔒, finalité « coordination co-parentale (échanges) », base légale, durée de conservation (politique RGPD), destinataires (membres habilités de la famille). **DoD du lot** (test CI de cohérence du registre, cdc §7.1).
- Les autres champs (`title`, `body`, `label`, `sub`) sont du **texte métier libre** non marqué 🔒 dans le cdc §5.2 — on **n'introduit aucun champ santé typé**. (Si le produit jugeait `title`/`body` personnels, ajouter les entrées de registre correspondantes ; par défaut on suit strictement le cdc qui ne marque que `Message.text` 🔒 pour ce lot.)

### 5.5 Migrations
- Migration Doctrine versionnée créant `exchange`, `exchange_option`, `message`, `option_vote` avec FK, index (`exchange(family_id, status)`, `message(exchange_id, sent_at)`, `exchange_option(exchange_id, position)`), contrainte unique `option_vote(exchange_id, membership_id)`.
- Type Doctrine chiffré appliqué à `message.text`.

---

## 6. Endpoints API concernés

> Conventions transverses (cdc §6.1) : REST/JSON sous `/api` ; `Authorization: Bearer <jwt>` ; **`X-Family-Id`** obligatoire sur les routes scopées famille (validé contre les memberships) ; `Accept-Language` (sinon préférence utilisateur) ; pagination `?page=&itemsPerPage=` ; **erreurs RFC 7807** (`application/problem+json`, localisées) ; **`Idempotency-Key`** accepté sur les POST issus de l'outbox offline. OpenAPI exporté par API Platform (source du client Dart).

### 6.1 Tableau des endpoints (cdc §6.2 — section Échanges)
| Méthode | Chemin | Droit requis (Voter `exchanges`) | Scope | Description |
|---|---|---|---|---|
| GET | `/api/families/{id}/exchanges?status=` | `view` | `X-Family-Id` | Lister les échanges de la famille, filtrés par `status` (`open`/`decided`), paginés. |
| POST | `/api/families/{id}/exchanges` | `edit` | `X-Family-Id` | Créer une discussion ou une décision (avec options si décision). Idempotency-Key supporté. → fan-out `/families/{id}/exchanges`. |
| GET | `/api/exchanges/{id}` | `view` | (déduit famille) | Détail : corps, options + décomptes, fil de messages (textes en clair), statut, échéance, marqueur « en retard ». |
| PATCH | `/api/exchanges/{id}` | `edit` | (déduit famille) | Clôture (`status=decided`) / édition méta (échéance) / forçage de décision (`decidedOptionId`). → fan-out. |
| POST | `/api/exchanges/{id}/messages` | `edit` | (déduit famille) | Poster un message (texte chiffré 🔒). Idempotency-Key supporté. → fan-out `/families/{id}/exchanges/{id}`. |
| POST | `/api/exchanges/{id}/decide` | `edit` | (déduit famille) | Choisir + valider une option (`optionId`). Évalue la bascule en `decided`. Idempotency-Key supporté. → fan-out. |

> Les routes `/api/exchanges/{id}/...` déduisent la famille depuis l'échange et **revérifient** la cohérence avec `X-Family-Id` (rejet si incohérent). Le Voter `exchanges` s'applique sur **chaque** appel.

### 6.2 Codes de réponse normalisés
- `200` : lecture / decide / patch réussis.
- `201` : création d'échange / de message.
- `400` : `X-Family-Id` manquant / payload malformé.
- `403` : droit `exchanges` insuffisant (RFC 7807, `detail` « Droit 'exchanges:edit' requis. ») ou membership absent.
- `404` : ressource d'une autre famille / inexistante (sans fuite d'existence).
- `409` : décision déjà tranchée / réouverture interdite (conflit, utile pour réalignement outbox).
- `422` : validation (titre vide, < 2 options, option hors décision, message vide/long, `decide` sur discussion, etc.).

### 6.3 Exemples
**Créer une décision**
```http
POST /api/families/{F}/exchanges
X-Family-Id: {F}
Idempotency-Key: 7b1f…   (si issu de l'outbox)
Content-Type: application/json
{
  "type": "decision",
  "title": "Choisir le centre aéré de juillet",
  "body": "Deux options à comparer.",
  "childId": "…",                      // ou null = toute la famille
  "deadline": "…",                     // date, optionnelle ; jamais codée en dur
  "options": [
    { "label": "Centre A", "sub": "proche école", "position": 0 },
    { "label": "Centre B", "sub": "horaires étendus", "position": 1 }
  ]
}
→ 201 Created { "id":"…", "status":"open", "options":[…] }
```
**Décider (choisir + valider)**
```http
POST /api/exchanges/{E}/decide
X-Family-Id: {F}
{ "optionId": "…" }
→ 200 OK { "status":"decided", "decidedOptionId":"…", "tally":{ "optA":2, "optB":0 } }
// ou si pas de bascule : { "status":"open", "tally":{ … } }
// ou si déjà tranchée :
409 application/problem+json
{ "type":"/errors/conflict", "title":"Conflit",
  "detail":"Cette décision est déjà tranchée." }
```
**Erreur d'autorisation**
```http
403 application/problem+json
{ "type":"/errors/forbidden", "title":"Action non autorisée",
  "detail":"Droit 'exchanges:edit' requis." }
```

---

## 7. Invariants & règles métier

Invariants du cdc / CLAUDE.md pertinents pour ce lot (à ne pas violer) :

1. **Scope famille obligatoire.** Toute opération sur un échange/message/option/vote est scopée à la famille active via `X-Family-Id`, validé contre les memberships. Jamais de requête métier non scopée ; pas de fuite inter-familles (cdc §4.2, §6.1).
2. **Autorisation par Voters.** Un seul domaine ici : **`exchanges`** (`none|view|edit`), vérifié sur **chaque** ressource (liste, détail, message, decide, patch) sur le couple (Membre, Ressource). `parent` ⇒ `edit` forcé ; `child` ⇒ `view|none`, **jamais `edit`** ; `step_parent` au choix (cdc §2.2).
3. **Dernier admin protégé.** Invariant global non concerné directement (ce lot ne gère ni rôles ni admins), mais **ne doit pas être contourné** : aucune opération du lot ne retire/rétrograde un membership.
4. **Champs chiffrés au repos.** `Message.text` 🔒 chiffré AES-256-GCM applicatif ; un dump SQL brut ne révèle aucun contenu (CLAUDE.md, cdc §5.2/§5.3).
5. **Registre des données personnelles.** Ajout de `Message.text` ⇒ **entrée de registre** (attribut déclaratif + `registre-donnees.md`) ; test CI de cohérence vert (DoD, cdc §7.1).
6. **Pas de données de santé.** Aucun champ typé santé ; échanges/messages = texte libre uniquement (cdc §1.4, CLAUDE.md).
7. **i18n.** Toutes les chaînes (UI, erreurs RFC 7807) externalisées en ICU ; `fr-FR` livrée ; ajouter une langue ne demande aucun changement de code (cdc §4.6).
8. **Dates non codées en dur ; fuseau Europe/Paris par défaut.** Pas de 23/06/2026 en dur ; horloge injectable ; « en retard » et horodatages évalués/affichés dans `Family.timezone` (cdc §2.1, §10).
8bis. **Temps réel unifié via Mercure.** Web = Turbo Streams over Mercure (aucun framework JS) ; mobile = JSON via SSE. Abonnements autorisés par **JWT Mercure** dont les claims listent les topics des familles du membre (cdc §0, §4.4, §4.5). Latence cible **≤ 1 s** (cdc §9, Lot 6).
9. **Priorité de garde / overlay** : sans objet pour ce lot, mais rappel — les échanges **ne sont jamais** exposés via l'overlay famille liée (overlay = agenda RO uniquement, cdc §2.1, Lot 5).
10. **Fan-out post-commit & best-effort** : on ne publie jamais un état non durable ; un échec Mercure ne fait pas échouer la mutation (dégradation conforme à l'offline web dégradé).
11. **Autorité serveur** (cohérent avec l'outbox mobile) : en cas de conflit (décision déjà tranchée), le serveur renvoie l'état canonique (`409`) et le client se réaligne (cdc §4.4).

---

## 8. Definition of Done (DoD)

> Reprend la DoD générale (cdc §9) et la décline pour le Lot 6. Tous les points sont **bloquants** en CI.

### 8.1 Backend — tests unitaires (PHPUnit)
- Voter `exchanges` : matrice complète (parent `edit` forcé ; step_parent `none|view|edit` ; child `view|none`, jamais `edit`) sur (Membre, Exchange) pour chaque action (lire, créer, poster, voter, clôturer).
- **Cycle de vie de la décision** : choix, changement de vote, bascule consensus à 2 parents, non-bascule sur désaccord/égalité, électorat (exclusion `view`/`none`/enfants/memberships inactifs), clôture explicite/forçage.
- **Détection « en retard »** : bornes (échéance aujourd'hui vs hier en fuseau famille), `deadline` nulle, échange `decided`, **transitions heure été/hiver**, 29 février, horloge gelée.
- Validation : titre vide, < 2 options, option hors décision, `decide` sur discussion, message vide/trop long.
- Chiffrement `Message.text` : aller-retour chiffrer/déchiffrer ; **assertion qu'un accès brut au stockage ne contient pas le clair**.

### 8.2 Backend — tests fonctionnels / API (client de test Symfony / API Platform)
- Scoping `X-Family-Id` : header manquant (`400`), incohérent (`403`/`404`), isolation multi-familles.
- Tous les endpoints §6.1 : codes `200/201/400/403/404/409/422` couverts.
- **Idempotence** : rejeu POST message / decide / création avec même `Idempotency-Key`.
- **Concurrence** : deux `decide` concurrents → un `decided`, l'autre `409`.
- Fixtures : famille séparée à 2 parents (+ beau-parent habilité/non, + enfant) reproduisant la maquette (cdc §4.9).

### 8.3 Web — E2E (Playwright)
- Parcours **fil d'échange en temps réel** (cdc §4.9, §9) : deux contextes navigateur (deux membres) ; l'un poste, l'autre voit le message **≤ 1 s** (Turbo Stream over Mercure), badge mis à jour.
- Décision : choix + validation par les deux parents → bascule « décidé » visible des deux côtés en direct.
- Marqueur « en retard » rendu sur une décision à échéance passée.
- Autorisation : un membre `view` ne voit pas les actions d'édition ; un membre `none` n'a ni onglet ni badge.
- Accessibilité de base + responsive (desktop/mobile viewports), conforme au design system §8.

### 8.4 Mobile — Flutter
- Écran Échanges (liste, détail, fil, options, decide) recréé d'après la maquette mobile iOS « liquid glass » (cdc §4.4, §8), Material adapté sur Android.
- Tests unitaires (mappers de l'API générée, logique « en retard », tri du fil), tests de widgets (fil, carte décision, badge), abonnement **SSE Mercure** consommé en JSON et inséré en direct.
- (Validation offline E2E des échanges = Lot 8, non bloquante ici ; mais les POST passent par l'outbox avec `Idempotency-Key`.)

### 8.5 Contrats & migrations
- **Migration Doctrine** versionnée fournie (tables + index + contraintes d'unicité + champ chiffré).
- **OpenAPI à jour** (API Platform) couvrant les 6 endpoints ; client Dart régénérable depuis `/contracts` sans divergence de types.
- Topics Mercure documentés (`/families/{id}/exchanges`, `/families/{id}/exchanges/{exchangeId}`) et JWT Mercure étendu aux claims `exchanges` selon le droit.

### 8.6 i18n
- Toutes les chaînes UI/erreurs externalisées (ICU, `fr-FR`) : labels, états vides, « ouvert/décidé/en retard », pluriels du badge et des validations. Aucune chaîne en dur. Ajout d'une langue sans changement de code.

### 8.7 Registre données & RGPD
- Entrée de registre pour **`Message.text`** 🔒 (attribut + `registre-donnees.md`) ; **test CI de cohérence vert**.
- Vérification que `message.text` est chiffré au repos (test/inspection) ; aucun clair dans un dump SQL.
- Affichage tolérant aux auteurs anonymisés (préparation Lot 8) : le rendu ne casse pas sur un membership neutre.

### 8.8 INSTALL.md & dépendances
- Si une dépendance change (peu probable : Mercure/Turbo/Stimulus posés au Lot 0), mettre à jour **`/infra/INSTALL.md`** (lancement Mercure, workers, etc.). Vérifier que le hub Mercure et les claims `exchanges` sont documentés.

### 8.9 Qualité
- **Analyse statique propre** : PHPStan (niveau élevé) + `php-cs-fixer` côté backend ; `dart analyze` + `dart format` côté mobile — sans erreur.
- Couverture au-dessus du seuil défini au Lot 0.

### 8.10 Conformité design system (§8)
- Couleurs des membres (parent A terracotta `#C2643D` / parent B teal `#2E7D71`), surfaces crème, cartes, pastilles, rayons (modale 22px, items nav 11px), polices (`Bricolage Grotesque` titres / `Hanken Grotesk` corps).
- Badge d'échanges ouverts dans la navigation (pastille `999px`).
- Marqueur « en retard » lisible (accent), bandeau « décision tranchée » sur option retenue.
- Écran maître-détail (desktop) pour Échanges (cdc §8) ; onglets bas + grandes en-têtes (mobile).
