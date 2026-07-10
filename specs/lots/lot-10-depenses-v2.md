# Lot 10 — Dépenses (v2)

> Spécification détaillée d'implémentation, dérivée du cahier des charges `specs/cdc.md` (source de vérité) et des invariants verrouillés de `CLAUDE.md`. Référence cdc : §1.3 (périmètre v1/v2), §1.4.4 (masquage v1), §2.2 (RBAC, `expenses` enfant forcé `none`), §2.7 (modèle métier Dépense), §3.7 (US-DEP-*), §5.2 (`Expense`, `ExpenseShare`, `expense_child`), §6.2 (endpoints Dépenses), §8 (couleurs catégories dépenses), §9 (Lot 10).
>
> Locale livrée : `fr-FR`. Fuseau par défaut : `Europe/Paris`. Aucune date n'est codée en dur (la date des maquettes — 23 juin 2026 — n'est jamais inscrite dans le code).

---

## 0. Objectif & périmètre

### 0.1 Objectif

Livrer le module **Dépenses partagées** annoncé en v2 (cdc §1.3), à la fois en **web (Symfony UX / Twig / Turbo / Stimulus)** et en **mobile (Flutter)**. Le module permet aux parents de :

- enregistrer une dépense liée aux enfants (titre, catégorie, payeur, montant, date, enfant(s) concerné(s)) ;
- définir un **partage personnalisé** (50/50 par défaut ; sinon parts en pourcentage ou en montants, voire pondération par enfant) ;
- consulter un **solde « qui doit quoi »** entre les parents, une **répartition par catégorie** et le **total engagé** ;
- **marquer une dépense réglée** et **envoyer un rappel** au parent débiteur.

Le module est **piloté par activation** : il n'existe pour une famille que si `Family.expenses_enabled = true`. L'onglet de navigation, les écrans et l'API sont **conditionnels** à ce drapeau. Pour une famille **unie** (`intact`), l'activation est **optionnelle** (cdc §2.1).

### 0.2 Dans le périmètre

- Entités `Expense` et `ExpenseShare` (modèle déjà réservé en v1, cdc §5.2) + table de liaison `expense_child` ; pleine activation CRUD + calculs dérivés.
- Voter de domaine `expenses` (niveaux `none|view|edit`), avec **interdiction stricte** d'accès pour le rôle `child` (forcé `none`, cdc §2.2).
- Algorithme de **calcul des parts** (percent / amount / défaut 50/50 / pondération par enfant) avec gestion exacte des arrondis au centime.
- Algorithme de **solde par couple parental** (« qui doit quoi ») agrégé sur dépenses réglées / non réglées.
- Agrégats **par catégorie** et **total engagé**.
- Actions « **Marquer réglé** » et « **Envoyer un rappel** » (e-mail, réutilise l'infra Notifications du Lot 7).
- **Activation conditionnelle** par module (`expenses_enabled`) : exposition / masquage de l'onglet web, des écrans Flutter et des endpoints API.
- Écrans web + mobile conformes au design system §8 (couleurs catégories dépenses).
- i18n (fr-FR) de toutes les chaînes UI + e-mail.
- Mise à jour du **registre des données personnelles** (nouveaux champs personnels éventuels — voir §7).
- Couche offline mobile (Drift + outbox + `Idempotency-Key`) pour la création / l'édition de dépenses (cohérent avec l'architecture mobile §4.4 du cdc).

### 0.3 Hors périmètre (explicitement)

- **Notifications push mobile** : Lot 11 (v2). En Lot 10, le rappel passe uniquement par **e-mail** (canal v1, cdc §2.9).
- **Multi-devise réelle** : le champ `currency` existe (cdc §5.2) ; v2 livre **EUR** uniquement (locale `fr-FR`), sans conversion. La présence du champ ne déclenche aucune logique de change.
- **Pièces jointes / justificatifs** (photos de tickets) : non prévus par le cdc → hors périmètre. Ne pas inventer de champ.
- **Données de santé** : la catégorie de dépense `sante` est une **étiquette de classification budgétaire libre**, pas un champ typé santé ; aucune donnée de santé n'est créée ni inférée (cdc §1.4.1).
- **Partage avec un beau-parent comme débiteur/créditeur du solde** : le solde « qui doit quoi » est calculé entre **parents** uniquement (le payeur est un membre `parent`, cdc §2.7). Un beau-parent avec droit `expenses=edit` peut saisir/éditer mais n'entre pas dans le solde inter-parents (voir §3.2 et §4).
- Onglet/dépenses pour une famille avec `expenses_enabled = false` : aucun rendu, aucune route.

### 0.4 Contexte v1 / v2

En v1 (cdc §1.4.4), le modèle de données **réserve la place** (`Expense`, `ExpenseShare`, `expense_child`, `Family.expenses_enabled`, domaine de droit `expenses`, catégorie de notification `expenses`) mais **l'onglet et le module n'apparaissent pas** et l'API est inactive. Le Lot 10 **active** ce module sans rupture de schéma : les migrations Lot 10 créent/complètent les tables si elles n'existaient pas encore matérialisées, mais ne modifient aucune sémantique v1 existante.

---

## 1. Dépendances

### 1.1 Lots prérequis (avant)

| Lot | Pourquoi il est requis |
|---|---|
| **Lot 0 — Fondations** | Monorepo, Symfony + API Platform + Doctrine + JWT + Voters (squelette), Mercure, Messenger, Mailer, i18n, AssetMapper/Turbo/Stimulus, client Flutter généré depuis l'OpenAPI, couche offline (Drift + outbox), harnais de tests (PHPUnit, Playwright, Flutter), squelette RGPD Manager + `registre-donnees.md`. Indispensable à tout. |
| **Lot 1 — Familles, membres, rôles & droits** | `Family` (dont `expenses_enabled`), `Membership` (rôle `parent`/`step_parent`/`child`, `is_admin`), `Right` par domaine, scope `X-Family-Id`, sélecteur de famille, mécanisme des Voters par domaine. Le module dépenses se greffe sur le domaine de droit `expenses` et sur le payeur = membre parent. |
| **Lot 2 — Enfants & notes** | `Child` : nécessaire pour `expense_child` (rattacher une dépense à un ou plusieurs enfants) et pour la pondération par enfant. |
| **Lot 7 — Notifications e-mail** | `NotificationPref` (catégorie `expenses`), déclencheurs e-mail via Messenger, templates Twig localisés, scheduler. L'action « Envoyer un rappel » et la notification « dépense ajoutée/réglée » (cdc §2.9) réutilisent cette infra. |
| **Lot 8 — PWA / offline / RGPD Manager** | RGPD Manager **complet** (registre des données personnelles + test de cohérence CI, anonymiseur, purge, AuditLog branché). Tout nouveau champ personnel du Lot 10 doit s'inscrire dans le registre et passer le test de cohérence ; l'anonymiseur doit savoir traiter une dépense d'un membre supprimé. Offline mobile riche (outbox) est le socle des mutations dépenses hors-ligne. |
| **Lot 9 — CI/CD & build iOS sans Mac** *(dépendance pratique, non fonctionnelle)* | Fournit le pipeline de build iOS signé (TestFlight) permettant de valider le rendu de l'onglet Dépenses sur un device iOS réel, sans dépendre d'un Mac local. Non bloquant pour la compilation, mais requis en pratique pour la recette mobile iOS. |

> Remarque : le Lot 10 est, par le découpage cdc §9, le **dernier lot fonctionnel v2 hors push**. Tous les lots 0→8 sont donc supposés livrés. Les dépendances « dures » (sans lesquelles le code ne compile/fonctionne pas) sont **Lot 0, 1, 2** ; les dépendances « fonctionnelles » (sans lesquelles une fonctionnalité du lot est incomplète) sont **Lot 7** (rappel e-mail) et **Lot 8** (RGPD/anonymisation, offline).

### 1.2 Lots débloqués (après)

| Lot | Ce que le Lot 10 lui apporte |
|---|---|
| **Lot 11 — Push mobile (v2)** | Ajoute le **canal push** à `NotificationPref` et aux déclencheurs. La catégorie de notification `expenses` (rappel/ajout/règlement) introduite/activée ici devient un déclencheur push. Lot 10 doit donc câbler proprement ses notifications via l'abstraction de canaux pour que Lot 11 n'ait qu'à brancher APNs/FCM, sans refonte. |

> Aucun autre lot ne dépend du Lot 10. Le module est en bout de chaîne fonctionnelle.

---

## 2. User stories complètes

> Convention : les CA sont en Gherkin (Étant donné / Quand / Alors). Chaque US couvre au minimum : cas nominal, cas d'erreur (validation), cas d'autorisation refusée (Voter `expenses` / scope famille / rôle enfant), et cas temps réel si applicable. Les IDs `US-DEP-1` / `US-DEP-2` proviennent du cdc §3.7 ; les autres sont créés en cohérence (préfixe `US-DEP-*`).

> ⚠️ **Décisions de spec à faire valider explicitement avec le porteur produit (extensions du contrat cdc)**
> Le cdc §3.7 ne formalise que **US-DEP-1** (ajouter une dépense) et **US-DEP-2** (solde / par catégorie / marquer réglé / rappel) ; le cdc §6.2 ne liste pour les dépenses que `GET`/`POST` (collection), `PATCH` (item) et `GET .../balance`. Les éléments suivants **étendent ce contrat** : ils sont jugés nécessaires à la complétude fonctionnelle et **aucun invariant ne les interdit**, mais ce sont des **décisions de spec** qui doivent être **validées par le porteur produit** avant implémentation. Tant qu'elles ne le sont pas, elles restent activables/désactivables sans toucher au cœur du lot :
>
> | Extension | US / §  | Au-delà de | Justification | Repli si non validé |
> |---|---|---|---|---|
> | `DELETE /api/expenses/{id}` (suppression d'une dépense) | US-DEP-5, §6 | cdc §6.2 (pas de `DELETE`) | Corriger une saisie erronée | Ne pas exposer ; « suppression » = correction + `settled` |
> | `POST /api/expenses/{id}/reminder` (endpoint dédié rappel) | US-DEP-2, US-DEP-7, §6 | cdc §6.2 (action « rappel » non routée) | Matérialiser l'action « Envoyer un rappel » du cdc §2.7 | Déclencher le rappel via un autre canal (ex. `PATCH` ou action UX) |
> | `expense_child.weight` (pondération enfant persistée) | US-DEP-1 (CA pondération), §3.1.D, §5.3 | cdc §5.2 (table `expense_child` sans `weight`) | Matérialiser la « pondération par enfant » du cdc §2.7 | Garder la pondération volatile (saisie non persistée) ou la retirer |
> | US-DEP-3 (lister/filtrer), US-DEP-4 (modifier), US-DEP-6 (activer module), US-DEP-8 (offline) | §2 | cdc §3.7 (2 US seulement) | Couverture CRUD + activation + offline déjà imposés par d'autres §/lots (§6.2 `GET`/`PATCH`, §2.1, §4.4) | — (dérivés directs d'exigences existantes, faible risque) |
>
> Les US-DEP-3/4/6/8 sont des **dérivés directs** d'exigences déjà présentes ailleurs dans le cdc (endpoints `GET`/`PATCH` du §6.2, activation par module §2.1, offline mobile §4.4) : leur risque de divergence est faible. Les trois premières lignes (DELETE, endpoint reminder, colonne `weight`) sont les **vraies extensions de contrat** à arbitrer.

### US-DEP-1 — Ajouter une dépense

**En tant que** parent (ou beau-parent ayant `expenses=edit`), **je veux** ajouter une dépense (titre, catégorie, payeur, montant, date, enfant(s) concerné(s), partage personnalisé) **afin de** tracer ce que chacun a engagé pour les enfants.

**CA — Cas nominal (partage par défaut 50/50)**
- Étant donné une famille dont `expenses_enabled = true` et un membre connecté ayant `expenses=edit` sur cette famille
- Et que le header `X-Family-Id` correspond à cette famille
- Quand il poste une dépense valide (titre non vide, catégorie ∈ {sante, scolarite, activites, vetements, autre}, payeur = un membre parent de la famille, `amountCents` entier > 0, `expenseDate` valide, ≥ 1 enfant concerné, sans bloc `shares`)
- Alors une `Expense` est créée avec `settled=false`, `currency="EUR"`, rattachée aux enfants fournis
- Et un partage **50/50** est matérialisé entre les **deux parents** (`ExpenseShare` par parent, `share_type=percent`, `value=50` chacun) ou selon la convention de défaut retenue (voir §3.1)
- Et la réponse est `201 Created` avec l'identifiant et le détail des parts calculées (montants en centimes par membre, somme = `amountCents`)
- Et un événement Mercure est publié sur le topic dépenses de la famille (voir §3.5) pour rafraîchir la liste et le solde.

**CA — Cas nominal (partage personnalisé en pourcentages)**
- Étant donné un payeur parent et un partage `[{membershipId: A, share_type: percent, value: 70}, {membershipId: B, share_type: percent, value: 30}]`
- Quand la dépense est postée
- Alors la somme des pourcentages = 100 est validée
- Et les montants par membre sont calculés et arrondis au centime sans perte (la somme des centimes = `amountCents`, voir §3.1)
- Et `201 Created`.

**CA — Cas nominal (partage personnalisé en montants)**
- Étant donné un partage `share_type=amount` dont la somme des `value` (en centimes) = `amountCents`
- Quand la dépense est postée
- Alors elle est acceptée telle quelle (pas de re-normalisation)
- Et `201 Created`.

**CA — Cas nominal (pondération par enfant)**
- Étant donné une dépense concernant 2 enfants avec une pondération par enfant (ex. enfant1 = 2 parts, enfant2 = 1 part)
- Quand la dépense est postée avec le bloc de pondération
- Alors la base de partage entre parents est ajustée selon la pondération enfant (voir §3.1, étape « pondération par enfant ») avant répartition entre parents
- Et la somme finale par membre = `amountCents`.

**CA — Cas d'erreur : montant invalide**
- Étant donné un membre `expenses=edit`
- Quand il poste une dépense avec `amountCents ≤ 0` ou non entier, ou un montant dépassant la borne max (voir §4)
- Alors la réponse est `422 Unprocessable Entity` (`application/problem+json`, RFC 7807) avec un message localisé (fr-FR) pointant le champ `amount`
- Et aucune `Expense` n'est créée.

**CA — Cas d'erreur : parts incohérentes**
- Étant donné un partage `percent` dont la somme ≠ 100, ou un partage `amount` dont la somme ≠ `amountCents`, ou un mélange de `share_type` interdit (voir §3.1), ou une part référençant un membre hors famille / non-parent quand le solde l'exige
- Quand la dépense est postée
- Alors la réponse est `422` avec détail localisé sur `shares`
- Et aucune `Expense` n'est créée (transaction annulée).

**CA — Cas d'erreur : payeur invalide**
- Étant donné un `payerMembershipId` qui n'est pas un membre `parent` actif de la famille (ex. beau-parent, enfant, membre d'une autre famille, membership inexistant)
- Quand la dépense est postée
- Alors `422` (ou `404` si le membership n'existe pas dans la famille) avec message localisé
- Et aucune création.

**CA — Cas d'erreur : enfant hors famille**
- Étant donné un `childId` n'appartenant pas à la famille active
- Quand la dépense est postée
- Alors `422`/`404`, aucune création.

**CA — Autorisation refusée : droit insuffisant**
- Étant donné un membre ayant `expenses=view` (ou `none`)
- Quand il tente `POST /api/families/{id}/expenses`
- Alors `403 Forbidden` (`application/problem+json`, `detail: "Droit 'expenses:edit' requis."` localisé)
- Et aucune création.

**CA — Autorisation refusée : rôle enfant (interdiction stricte)**
- Étant donné un membre de rôle `child` (dont le droit `expenses` est **forcé `none`**)
- Quand il tente toute opération sur `/api/families/{id}/expenses*`
- Alors `403 Forbidden`, quel que soit l'état de `Right` en base (le Voter ignore tout niveau autre que `none` pour un enfant)
- Et l'onglet « Dépenses » n'est pas rendu côté web ni mobile pour ce membre.

**CA — Scope famille incohérent**
- Étant donné un membre connecté qui n'est pas membre de la famille ciblée, ou un `X-Family-Id` absent / ne correspondant pas à l'URL
- Quand il appelle l'endpoint
- Alors `403`/`404` (sans révéler l'existence de la famille) ; aucune fuite de données d'une autre famille.

**CA — Module désactivé**
- Étant donné une famille dont `expenses_enabled = false`
- Quand un membre (même parent/admin) appelle `POST /api/families/{id}/expenses`
- Alors `404 Not Found` (la ressource n'existe pas pour cette famille) — l'API est inactive sans le module
- Et l'onglet n'est pas rendu.

---

### US-DEP-2 — Voir les soldes, marquer réglé, envoyer un rappel

**En tant que** parent (ou membre ayant `expenses=view`), **je veux** voir le solde « qui doit quoi », la répartition par catégorie et le total engagé, et (avec `expenses=edit`) marquer une dépense réglée ou envoyer un rappel **afin de** suivre et solder les comptes entre co-parents.

**CA — Cas nominal : consultation du solde**
- Étant donné une famille `expenses_enabled=true` avec plusieurs dépenses (réglées et non réglées) et un membre ayant `expenses=view`
- Quand il appelle `GET /api/families/{id}/expenses/balance`
- Alors la réponse `200` contient : le **solde net inter-parents** (« A doit B X € » ou « comptes équilibrés »), la **répartition par catégorie** (montant engagé par catégorie + part %), le **total engagé**, et la **ventilation réglé / non réglé**
- Et tous les montants sont localisés (€, séparateur fr-FR) à l'affichage, mais transportés en **centimes entiers** dans le JSON.

**CA — Cas nominal : marquer réglé**
- Étant donné une dépense non réglée et un membre `expenses=edit`
- Quand il appelle `PATCH /api/expenses/{id}` avec `{ "settled": true }`
- Alors `settled` passe à `true`, `updated_at` est mis à jour, le solde est recalculé (la dépense réglée sort du solde « dû », voir §3.2)
- Et un événement Mercure est publié (rafraîchit la liste + le solde) ; une notification e-mail « dépense réglée » est éventuellement émise selon `NotificationPref` (catégorie `expenses`).

**CA — Cas nominal : envoyer un rappel**
- Étant donné une dépense non réglée dont le débiteur net est le parent B, et un membre `expenses=edit`
- Quand il déclenche « Envoyer un rappel »
- Alors un e-mail localisé est envoyé (via Messenger, asynchrone) au(x) parent(s) débiteur(s) **qui ont activé** la catégorie `expenses` dans `NotificationPref`
- Et l'action est journalisée (`AuditLog`) ; un toast/Turbo Stream confirme l'envoi
- Et un anti-spam empêche l'envoi répété (cooldown, voir §4).

**CA — Cas d'erreur : marquer réglé une dépense déjà réglée**
- Étant donné une dépense déjà `settled=true`
- Quand on repasse `{ "settled": true }`
- Alors l'opération est **idempotente** : `200` sans changement d'état ni double notification.

**CA — Autorisation refusée : édition par un `view`**
- Étant donné un membre `expenses=view`
- Quand il tente `PATCH /api/expenses/{id}` (marquer réglé) ou « envoyer un rappel »
- Alors `403 Forbidden` localisé ; état inchangé.

**CA — Autorisation refusée : enfant**
- Étant donné un membre `child`
- Quand il tente `GET .../balance` ou tout accès dépenses
- Alors `403 Forbidden`.

**CA — Temps réel**
- Étant donné deux co-parents connectés (web et/ou mobile) regardant l'écran Dépenses de la même famille
- Quand l'un crée/édite/règle une dépense
- Alors l'autre voit la liste, le solde et les agrégats se mettre à jour en direct (≤ ~1s) via Mercure (Turbo Stream sur le web, SSE/JSON sur le mobile), sans rechargement.

---

### US-DEP-3 — Lister, filtrer et consulter les dépenses

**En tant que** membre ayant `expenses=view`, **je veux** lister les dépenses de la famille, filtrées par catégorie / enfant / état (réglé), triées par date **afin de** retrouver une dépense et comprendre l'historique.

**CA — Cas nominal**
- Étant donné une famille `expenses_enabled=true` avec N dépenses et un membre `expenses=view`
- Quand il appelle `GET /api/families/{id}/expenses?category=&childId=&settled=&page=&itemsPerPage=`
- Alors la réponse paginée (Hydra/JSON, API Platform) liste les dépenses triées par `expense_date` décroissant (défaut), avec pour chacune : titre, catégorie (+ couleur §8), payeur, montant, date, enfants, état réglé, et le détail des parts
- Et les filtres optionnels s'appliquent cumulativement.

**CA — Cas d'erreur : filtre invalide**
- Étant donné un `category` hors énumération ou un `childId` hors famille
- Quand l'appel est fait
- Alors `422`/`400` localisé, ou liste vide selon la convention (voir §4) — jamais de fuite cross-famille.

**CA — Autorisation refusée**
- Membre `expenses=none` / `child` → `403`. Module désactivé → `404`.

---

### US-DEP-4 — Modifier une dépense existante

**En tant que** membre `expenses=edit`, **je veux** modifier une dépense (titre, catégorie, montant, date, enfants, partage) **afin de** corriger une saisie.

**CA — Cas nominal**
- Étant donné une dépense existante (réglée ou non) et un membre `expenses=edit`
- Quand il `PATCH /api/expenses/{id}` avec des champs valides (y compris un nouveau bloc `shares`)
- Alors les champs sont mis à jour, les parts re-matérialisées et re-validées (somme cohérente, §3.1), le solde recalculé, `updated_at` mis à jour
- Et un événement Mercure est publié.

**CA — Cas d'erreur : édition incohérente**
- Modifier le montant sans réajuster un partage `amount` qui ne somme plus à `amountCents` → `422`, transaction annulée.

**CA — Cas particulier : modifier une dépense réglée**
- Étant donné une dépense `settled=true`
- Quand un membre `expenses=edit` la modifie (montant/partage)
- Alors la modification est acceptée (pas de gel automatique), `settled` reste `true` sauf si explicitement repassé à `false`, et le solde est recalculé en conséquence ; l'action est journalisée.

**CA — Autorisation refusée**
- `expenses=view` / `none` / `child` → `403`. Scope famille / module désactivé → `403`/`404`.

---

### US-DEP-5 — Supprimer une dépense

**En tant que** membre `expenses=edit`, **je veux** supprimer une dépense saisie par erreur **afin de** garder des comptes justes.

> Note : le cdc §6.2 liste explicitement `GET/POST` (collection) et `PATCH` (item) pour les dépenses, sans `DELETE`. Cette US est ajoutée pour la complétude fonctionnelle (corriger une erreur de saisie). La suppression est exposée si et seulement si elle est jugée nécessaire au Lot 10 ; sinon la « suppression » se fait par mise à `settled` + correction. **Décision de spec : exposer `DELETE /api/expenses/{id}` (droit `expenses:edit`)** car aucun invariant ne l'interdit et l'effacement d'une saisie erronée est attendu.

**CA — Cas nominal**
- Étant donné une dépense et un membre `expenses=edit`
- Quand il `DELETE /api/expenses/{id}`
- Alors la dépense et ses `ExpenseShare` + lignes `expense_child` sont supprimées en cascade, le solde recalculé, un événement Mercure publié, l'action journalisée
- Et `204 No Content`.

**CA — Autorisation / scope / module**
- `view`/`none`/`child` → `403` ; hors famille → `403`/`404` ; module désactivé → `404`.

---

### US-DEP-6 — Activer / désactiver le module Dépenses (réglages famille)

**En tant qu'** administrateur de la famille (`family_settings=edit`), **je veux** activer ou désactiver le module Dépenses **afin de** rendre l'onglet et l'API disponibles selon le besoin de la famille (optionnel pour une famille unie).

**CA — Cas nominal : activation**
- Étant donné un admin et une famille `expenses_enabled=false`
- Quand il `PATCH /api/families/{id}` avec `{ "expensesEnabled": true }`
- Alors le drapeau passe à `true`, l'onglet « Dépenses » apparaît (web + mobile) pour les membres habilités, et les endpoints dépenses deviennent actifs
- Et l'action est journalisée.

**CA — Cas nominal : désactivation**
- Étant donné une famille `expenses_enabled=true` avec des dépenses existantes
- Quand l'admin désactive le module
- Alors l'onglet et l'API redeviennent inactifs ; les **données existantes sont conservées** (non supprimées) et réapparaissent à une éventuelle réactivation (voir §4 — décision sur la rétention)
- Et l'action est journalisée.

**CA — Famille unie (optionnel)**
- Étant donné une famille `intact`
- Quand l'admin active le module
- Alors le module fonctionne (cdc §2.1 : « partage des dépenses optionnel ») ; le solde inter-parents s'applique entre les deux parents de la famille unie le cas échéant, ou affiche « comptes équilibrés / partagés » selon la configuration (voir §4).

**CA — Autorisation refusée**
- Étant donné un membre non admin (parent simple, beau-parent, enfant)
- Quand il tente de changer `expensesEnabled`
- Alors `403` (Voter `family_settings`), drapeau inchangé.

**CA — Dernier admin**
- Cette US ne touche pas la composition admin ; l'invariant « dernier admin protégé » reste géré par le Lot 1 et n'est pas régressé par le Lot 10.

---

### US-DEP-7 — Notification « dépense ajoutée / réglée »

**En tant que** parent, **je veux** être notifié par e-mail quand une dépense est ajoutée ou réglée (selon mes préférences) **afin de** rester informé sans surveiller l'écran.

> Dérive de cdc §2.9 (« (v2) dépense ajoutée/remboursée »).
>
> **Note de terminologie (i18n)** : le cdc §2.9 nomme l'événement « dépense **remboursée** », tandis que ce lot emploie partout l'état technique `settled` traduit par « **réglée** ». Les deux termes sont sémantiquement équivalents (*settled* = soldée/remboursée). **Décision de spec** : l'**état** du modèle et de l'API reste `settled` (clé stable, non traduite) ; la **chaîne d'affichage et le sujet/corps de l'e-mail** utilisent le libellé localisé fr-FR « réglée » (clé i18n `expense.notification.settled.*`). L'écart de vocabulaire avec le cdc est ainsi cantonné à la couche de présentation/traduction : changer le libellé (« réglée » ↔ « remboursée ») n'impacte que le fichier de traduction, jamais le code ni le contrat API. Le gabarit e-mail doit donc référencer une clé ICU, pas une chaîne en dur.

**CA — Cas nominal : dépense ajoutée**
- Étant donné un parent B ayant activé la catégorie `expenses` (canal `email`) dans `NotificationPref`
- Quand un parent A crée une dépense impliquant B
- Alors un e-mail localisé (fr-FR, fuseau Europe/Paris pour la mise en forme de la date) est envoyé à B de façon asynchrone (Messenger)
- Et l'acteur A n'est pas notifié de sa propre action (anti-écho).

**CA — Cas nominal : dépense réglée**
- Étant donné un parent ayant activé la catégorie `expenses`
- Quand une dépense le concernant passe `settled=true`
- Alors une notification « dépense réglée » localisée est émise selon ses préférences.

**CA — Préférence désactivée**
- Étant donné un parent ayant désactivé la catégorie `expenses`
- Quand une dépense est ajoutée/réglée
- Alors **aucun** e-mail ne lui est envoyé.

**CA — Quiet hours / locale**
- Le respect des quiet-hours optionnels et de la locale (cdc §2.9) est délégué à l'infra Notifications du Lot 7 ; le Lot 10 fournit le **déclencheur** et le **gabarit** mais ne réimplémente pas la logique de fenêtre.

---

### US-DEP-8 — Création/édition hors-ligne (mobile)

**En tant qu'** utilisateur mobile, **je veux** créer ou modifier une dépense hors-ligne et la voir se synchroniser à la reconnexion **afin de** saisir une dépense au moment où elle a lieu, sans réseau.

> Dérive de l'architecture offline mobile cdc §4.4 + §6.1 (`Idempotency-Key`).

**CA — Cas nominal : création hors-ligne puis resynchro**
- Étant donné un mobile hors-ligne et un membre `expenses=edit`
- Quand il crée une dépense
- Alors elle est stockée localement (Drift), marquée « en attente de synchro », et placée dans l'outbox avec un `Idempotency-Key`
- Et à la reconnexion, la mutation est rejouée (`POST` avec `Idempotency-Key`) ; le serveur crée la dépense **une seule fois** même si la requête est rejouée
- Et l'état local passe à « synchronisé », le solde local est réconcilié avec l'état serveur canonique.

**CA — Conflit / autorité serveur**
- Étant donné une dépense éditée hors-ligne entre-temps modifiée côté serveur par l'autre parent
- Quand l'outbox rejoue la mutation
- Alors l'autorité serveur s'applique (last-write-wins sur champs simples) ; en cas de refus (ex. dépense supprimée côté serveur), l'utilisateur est informé clairement et la mutation locale est résolue (abandonnée ou recréée selon §4).

**CA — Idempotence**
- Étant donné un `Idempotency-Key` déjà traité
- Quand la même requête est rejouée
- Alors le serveur renvoie la **même** réponse que la première fois (pas de doublon de dépense).

**CA — Module désactivé pendant l'offline**
- Si le module a été désactivé côté serveur pendant que le client était hors-ligne, la mutation rejouée échoue proprement (`404`) et l'utilisateur est informé ; aucune dépense fantôme.

---

## 3. Algorithme(s)

> Toutes les sommes monétaires sont manipulées en **centimes entiers** (`int`), jamais en flottant, pour éviter toute dérive d'arrondi. Devise = EUR en v2. Le seul moment où des décimales apparaissent est l'affichage localisé (formatage ICU fr-FR).

### 3.1 Calcul et matérialisation des parts (`ExpenseShare`)

**Entrées**
- `amount_cents` (int > 0) — montant total de la dépense.
- `payer_membership_id` — un membre `parent` actif.
- `participating_parents` — l'ensemble des **parents** de la famille (en pratique 2 ; le modèle ne suppose pas plus de 2, voir §4 famille avec un seul parent).
- `expense_child[]` — enfants concernés (≥ 1).
- Bloc `shares` optionnel : liste de `{membership_id, share_type ∈ {percent, amount}, value}`.
- Bloc `child_weights` optionnel : pondération par enfant `{child_id → poids (int ≥ 1)}` (pondération par enfant, cdc §2.7).

**Règles de cohérence (validation préalable, sinon `422`)**
1. Si `shares` est absent → **partage par défaut 50/50** entre les deux parents (voir étape A).
2. Si `shares` est présent : tous les `share_type` sont **homogènes** (tous `percent` OU tous `amount`) ; le mélange est refusé.
   - `percent` : chaque `value` ∈ [0, 100] ; somme = **100** (tolérance 0).
   - `amount` : chaque `value` ≥ 0 (centimes int) ; somme = **`amount_cents`** exactement.
3. Les `membership_id` des parts doivent être des **parents** de la famille (le solde « qui doit quoi » est inter-parents). Un beau-parent saisit/édite mais n'apparaît pas dans `shares` du solde inter-parents (voir §4 pour le cas où l'on souhaite l'inclure — non requis par le cdc).
4. `child_weights`, s'il est fourni, ne référence que des enfants présents dans `expense_child` ; chaque poids est un entier ≥ 1.

**Algorithme — répartition exacte au centime (méthode du plus grand reste / largest remainder)**

```
fonction repartir(amount_cents, poids[]):              # poids[] = liste de poids relatifs (int ou ratio)
    total_poids = somme(poids)
    si total_poids == 0: erreur 422
    # 1) part théorique en centièmes de centime pour précision
    base[i]     = (amount_cents * poids[i]) div total_poids       # division entière
    reste[i]    = (amount_cents * poids[i]) mod total_poids        # reste de la division
    attribue    = somme(base)
    manquant    = amount_cents - attribue                          # 0 ≤ manquant < n
    # 2) distribuer les centimes manquants aux plus grands restes (ordre déterministe)
    indices = trier(indices_par(reste décroissant, puis membership_id croissant))
    pour k de 0 à manquant-1:
        base[indices[k]] += 1
    retourner base   # somme(base) == amount_cents garantie
```

**Étapes globales**

- **A. Partage par défaut (50/50)** : `poids = [1, 1]` sur les deux parents → `repartir(amount_cents, [1,1])`. Pour un montant impair, le centime résiduel est attribué de façon déterministe (plus grand reste, puis ordre `membership_id` croissant), garantissant la reproductibilité et la somme exacte.

- **B. Partage en pourcentage** : convertir chaque `value%` en poids entier (`value` directement comme poids relatif sur base 100) → `repartir(amount_cents, [v_A, v_B, ...])`. La somme des pourcentages est déjà validée = 100 ; l'algorithme du plus grand reste absorbe l'arrondi pour garantir `somme = amount_cents`.

- **C. Partage en montants** : aucune redistribution — les `value` (centimes) sont pris tels quels (somme déjà validée = `amount_cents`). On matérialise un `ExpenseShare` par membre avec `share_type=amount` et `value` = centimes.

- **D. Pondération par enfant** : si `child_weights` est fourni, il sert **deux finalités distinctes qu'il faut tenir séparées** :
  1. **Dimension d'attribution / reporting (toujours)** : ventiler `amount_cents` entre les enfants concernés au prorata de leurs poids (via `repartir(amount_cents, [poids_enfant_i])`, mêmes garanties d'arrondi et de centime résiduel). Cette ventilation par enfant alimente d'éventuels agrégats « combien dépensé pour chaque enfant » ; elle **n'intervient pas** dans le calcul du solde inter-parents (qui ne dépend que de `payer` et des parts par parent).
  2. **Base du partage entre parents (cas percent / défaut 50/50 uniquement)** : quand le partage parent est exprimé en **pourcentage** ou laissé au **défaut 50/50**, le montant effectif par parent est, par linéarité, identique qu'on applique le pourcentage à `amount_cents` directement ou à chaque tranche-enfant puis qu'on somme. La pondération enfant **ne modifie donc pas** le montant dû par parent dans ces cas (le modèle §5.2 ne porte **qu'un seul** bloc `shares` par dépense, jamais un partage parent différent par enfant). La ventilation par enfant reste néanmoins matérialisée pour le reporting (finalité 1).

  **Cas `share_type=amount` + `child_weights` (levée d'ambiguïté)** : les deux mécanismes sont **orthogonaux et non composables** quand les parts parent sont des **montants fixes**. Des parts en montants figent déjà, au centime près, ce que chaque parent doit (somme = `amount_cents`) ; il n'existe aucune information pour redériver ces montants à partir de tranches-enfant sans contredire les valeurs saisies. **Décision de spec** : avec `share_type=amount`, `child_weights` est **ignoré pour le partage entre parents** — le montant effectif par parent reste exactement la `value` saisie. `child_weights` n'agit alors **que** comme dimension d'attribution par enfant (finalité 1, reporting). Cette règle est documentée à l'utilisateur côté UI (la saisie de montants fixes désactive l'effet « pondération » sur le partage parent) et un test unitaire vérifie que `montant_effectif(parent) == value` indépendamment de `child_weights` dans ce cas.

  **Invariants communs à D** : `Σ ventilation_enfant == amount_cents` ; `Σ montant_effectif(parent) == amount_cents` ; aucune des deux ventilations n'introduit de fraction de centime (plus grand reste, ordre déterministe). (Le cas « cet achat profite surtout à l'aîné » est couvert par la finalité 1 ; il n'altère le partage parent que lorsque celui-ci est en percent/50-50, et de façon neutre par linéarité.)

**Sortie / matérialisation**
- On **persiste** un `ExpenseShare` par membre concerné : on conserve le `share_type` et la `value` **tels que saisis** (percent ou amount), pour pouvoir ré-éditer fidèlement. Le **montant effectif en centimes** par membre est soit recalculé à la volée par `repartir_effectif(...)` (§3.2) lors des agrégats, soit mémoïsé (champ dérivé non normatif). La source de vérité reste `(share_type, value)` + `amount_cents` (et, pour le reporting par enfant, `child_weights` matérialisé via `expense_child.weight`, §5.3).
- **Invariant d'arrondi** : pour toute dépense, `Σ montant_effectif(membre) == amount_cents`. Un test unitaire vérifie cet invariant sur un balayage de montants (1 à N centimes) et de répartitions.

**Complexité** : `O(n log n)` par dépense (tri des restes), `n` = nombre de membres dans le partage (typiquement 2). Négligeable.

**Idempotence / transaction** : la création/édition d'une dépense + ses `ExpenseShare` + liaisons `expense_child` se fait dans **une seule transaction Doctrine**. En cas d'erreur de validation, rollback complet (aucune `Expense` orpheline). Avec `Idempotency-Key`, un rejeu renvoie la réponse mémorisée sans réinsertion.

### 3.2 Calcul du solde par couple parental (« qui doit quoi »)

**Objectif** : déterminer, sur l'ensemble des dépenses **non réglées** d'une famille, le solde net entre les deux parents (qui doit combien à qui), et l'exposer dans `/expenses/balance`.

**Principe** : pour chaque dépense non réglée, le **payeur a avancé** `amount_cents` ; chaque parent **doit sa part** (montant effectif §3.1). Le solde net d'un parent X = (somme des montants qu'il a avancés en payant) − (somme de ses parts dues sur l'ensemble des dépenses). Un solde positif = on lui doit ; négatif = il doit.

**Fonction `repartir_effectif(d)` — montant effectif par parent (définition normative)** : elle ne dépend **jamais** de `child_weights` (la pondération enfant est une dimension d'attribution, pas de partage parent — voir §3.1.D), mais uniquement du `share_type` des parts persistées :

```
fonction repartir_effectif(d):                   # → map(parent -> centimes dus), somme == d.amount_cents
    parts_parents = ExpenseShares(d) restreints aux memberships de rôle parent
    selon le share_type (homogène, garanti par §3.1) :
        cas absent (défaut)  : retourner repartir(d.amount_cents, [1, 1])     # 50/50, plus grand reste
        cas percent          : retourner repartir(d.amount_cents, [value_p pour chaque parent p])
        cas amount           : retourner { p -> value_p }                      # montants figés, pris tels quels
                               # child_weights ignoré ici (cf. §3.1.D, décision de spec)
    # dans tous les cas : somme(résultat) == d.amount_cents (invariant testé)
```

- En `percent` et défaut 50/50, l'arrondi au centime est résolu par `repartir` (plus grand reste, ordre `membership_id` croissant), reproductible.
- En `amount`, les parts sont déjà des centimes entiers dont la somme = `amount_cents` (validé §3.1) : aucun ré-arrondi, aucune influence de `child_weights`. Le montant effectif d'un parent **est** sa `value`.

```
fonction solde_inter_parents(family, {inclure_reglees: false}):
    net = map(membership_parent -> 0)            # en centimes
    dépenses = expenses(family) filtré sur settled == false (sauf si inclure_reglees)
    pour chaque dépense d:
        parts = repartir_effectif(d)             # map parent -> centimes dus (défini ci-dessus)
        net[d.payer] += d.amount_cents           # le payeur a déboursé le total
        pour chaque (membre, du) de parts:
            net[membre] -= du                    # chaque membre doit sa part
    # invariant : somme(net) == 0 (conservation)
    retourner net
```

- **Réduction à « A doit B »** : pour 2 parents A et B, `net[A] = -net[B]`. Si `net[B] < 0`, « B doit A » de `|net[B]|` ; si `= 0`, « comptes équilibrés ».
- **Dépenses réglées** : exclues du solde « dû » par défaut (`settled=true` ⇒ le remboursement est réputé fait). Elles restent comptées dans le **total engagé** et la **répartition par catégorie** (qui portent sur tout l'historique, réglé inclus, sauf filtre).
- **Conservation** : `Σ net == 0` est un invariant testé (sinon bug d'arrondi → échec de test).

**Complexité** : `O(D · n)` où `D` = nombre de dépenses non réglées, `n` = membres par partage (≈ 2) → linéaire. Pour des familles à fort volume, prévoir une agrégation SQL (somme par payeur + somme des parts) ; la version applicative reste la référence des tests.

**Ordre / priorité** : aucun ordre de priorité métier (contrairement à la garde) ; seule compte la somme. Le calcul est **pur** (déterministe, sans effet de bord) ⇒ facilement testable et mémoïsable.

### 3.3 Répartition par catégorie + total engagé

```
fonction agregats(family, {settled_filter: all|settled|unsettled}):
    total = 0
    par_categorie = map(catégorie -> {montant: 0, count: 0})
    pour chaque dépense d (filtrée selon settled_filter):
        total += d.amount_cents
        par_categorie[d.category].montant += d.amount_cents
        par_categorie[d.category].count   += 1
    pour chaque catégorie c: par_categorie[c].part = arrondi(par_categorie[c].montant / total)   # % affichage
    retourner { total, par_categorie, settled_total, unsettled_total }
```

- Le **total engagé** = somme de `amount_cents` sur l'historique (ou filtré).
- La **part %** par catégorie est un dérivé d'affichage (arrondi à l'unité ou 1 décimale, fr-FR) ; la somme des parts peut différer de 100 de ±1 à l'affichage — ce n'est pas un montant, donc pas d'invariant de centime.
- Les **couleurs** de catégories proviennent de §8 (cdc) : Santé `#C75D5D`, Scolarité `#C99A3F`, Activités `#5E7CB8`, Vêtements `#7E9B7A`, Autre `#857E71`. Aucune couleur n'est codée ailleurs que dans les tokens du design system.

### 3.4 Activation conditionnelle du module (`expenses_enabled`)

Le module est gardé à **trois niveaux** (défense en profondeur) :

1. **Routing / contrôleur** : les endpoints `/api/families/{id}/expenses*` et `/api/expenses/{id}` vérifient `Family.expenses_enabled == true` ; sinon `404` (la ressource « n'existe pas » pour cette famille). Implémentation via un **provider/state processor API Platform** ou un listener qui s'exécute **après** le scope famille et **avant** le Voter.
2. **Voter `expenses`** : refuse l'accès si le module est désactivé (cohérent avec le `404`), et applique la matrice de droits §2.2.
3. **Rendu UI** : l'onglet « Dépenses » (sidebar web + onglet bas mobile) n'est rendu **que si** `expenses_enabled && right(expenses) ≥ view && role != child`. Côté Flutter, l'onglet est retiré de la barre si la condition est fausse (et toute deep-link vers l'écran dépenses redirige proprement).

```
fonction module_dépenses_accessible(family, membership):
    si non family.expenses_enabled: retourner false
    si membership.role == 'child': retourner false              # interdiction stricte
    retourner droit(membership, 'expenses') in {view, edit}
```

### 3.5 Temps réel (Mercure)

> Le cdc §4.5 ne liste pas explicitement un topic dépenses (table v1). On l'ajoute en cohérence stricte avec la convention existante (`/families/{id}/<ressource>`), réservé v2.

- Topic : `/families/{id}/expenses` — publié à la **création**, **modification**, **règlement**, **suppression** d'une dépense (rafraîchit liste + solde + agrégats).
- **Web** : Turbo Streams over Mercure — le serveur pousse des fragments HTML (ligne de dépense, encart solde, encart répartition catégorie). Aucun JS dédié.
- **Mobile** : SSE/JSON via le client Mercure ; le store (Riverpod/Bloc) invalide/rafraîchit la liste et les agrégats.
- **Autorisation des abonnements** : le JWT Mercure ne liste le topic `/families/{id}/expenses` que si le membre a `expenses ≥ view` ET `expenses_enabled` ET `role != child`. Un enfant ou un membre `none` ne reçoit jamais ce topic.

### 3.6 Interdiction stricte d'accès enfant (Voter `expenses`)

```
classe ExpensesVoter (Voter Symfony, domaine 'expenses'):
    supports(attribute ∈ {EXPENSES_VIEW, EXPENSES_EDIT}, subject = Family|Expense)
    voteOnAttribute(attribute, subject, token):
        membership = membership_actif(token.user, family_active())     # via X-Family-Id
        si membership == null: DENY
        si non family.expenses_enabled: DENY                            # module off
        si membership.role == 'child': DENY                            # FORCÉ none, toujours
        niveau = (membership.role == 'parent') ? 'edit' : droit(membership,'expenses')
        switch attribute:
            EXPENSES_VIEW: ACCÈS si niveau in {view, edit}
            EXPENSES_EDIT: ACCÈS si niveau == edit
```

- Le rôle `parent` force `edit` (cdc §2.2) ; le rôle `child` force `none` **quel que soit** le `Right` stocké (un test unitaire vérifie qu'aucune combinaison de `Right.expenses` ne donne l'accès à un enfant).
- Le Voter opère sur **chaque** ressource (collection ET item). Pour un item `Expense`, il vérifie en plus que la dépense appartient bien à la famille active (anti-IDOR, cohérent avec le scope `X-Family-Id`).

---

## 4. Cas limites (edge cases)

### 4.1 Données aux bornes (dates, fuseau, heures, années bissextiles)

- **Fuseau** : `expense_date` est une **date** (jour civil), interprétée et affichée dans le fuseau de la famille (`Family.timezone`, défaut `Europe/Paris`). Le tri et les filtres `from/to` (si ajoutés) s'appliquent sur le jour civil local, pas sur l'instant UTC, pour éviter les décalages de minuit.
- **Changement d'heure été/hiver** : sans incidence directe (la dépense porte une **date**, pas une heure). La mise en forme localisée des **horodatages** (`created_at`/`updated_at`, e-mails de rappel) respecte le fuseau famille et bascule été/hiver correctement (test sur une date de bascule, ex. dernier dimanche d'octobre/mars, sans coder une année en dur).
- **Année bissextile** : `expense_date = 29 février` d'une année bissextile valide ; refusé sur une année non bissextile (`422`). Test paramétré sur une date bissextile et une non-bissextile, déduites dynamiquement (jamais 2026 en dur).
- **Date future / passée** : autorisée (on peut antidater une dépense). Une borne « pas trop loin dans le futur » optionnelle (ex. +N ans) peut renvoyer un avertissement ; par défaut, aucune restriction stricte.
- **Date de référence des maquettes (23 juin 2026)** : jamais codée en dur ; les fixtures/tests utilisent des dates relatives (`now`, `+N days`).

### 4.2 Montants et arrondis

- **Montant nul ou négatif** : `amount_cents ≤ 0` → `422`.
- **Montant non entier** : seuls des centimes entiers sont acceptés (pas de fraction de centime) → `422` sinon.
- **Montant maximal** : borne haute (ex. `amount_cents ≤ 1_000_000_00`, soit 1 000 000 €) pour éviter overflow d'agrégat et saisies aberrantes → `422` au-delà. Valeur exacte à fixer en config, pas en dur dispersé.
- **Répartition d'un montant impair** : 1 centime résiduel attribué de façon déterministe (plus grand reste, puis `membership_id` croissant). Test : un montant de 1 centime sur 2 parents 50/50 ⇒ {1, 0} reproductible.
- **Pourcentages ne sommant pas à 100** : `422`. **Montants ne sommant pas au total** : `422`.
- **Pourcentage à 0 %** pour un parent : autorisé (l'autre paie 100 %) ; il apparaît avec part 0.
- **Tous les pourcentages sur un seul parent (100/0)** : autorisé.

### 4.3 Concurrence & idempotence

- **Double soumission rapide** (web double-clic / retry réseau) : `Idempotency-Key` (issu de l'outbox mobile) garantit l'unicité ; côté web, désactivation du bouton + protection serveur (au pire, déduplication best-effort). Pas de double dépense.
- **Marquer réglé concurrent** : deux parents règlent la même dépense en même temps → idempotent (`settled=true` déjà posé, second `200` sans effet ni double notification).
- **Édition concurrente** : last-write-wins sur champs simples (autorité serveur, cdc §4.4) ; `updated_at` arbitre ; le perdant voit l'état canonique via Mercure.
- **Suppression vs édition concurrente** : si la dépense est supprimée pendant qu'un autre l'édite, l'édition échoue (`404`/`409`) proprement ; message localisé.
- **Recalcul de solde concurrent** : le calcul est pur et relu à la demande ; pas de cache muable partagé incohérent (si cache, invalidation sur tout événement dépense).

### 4.4 Autorisations (parent / beau-parent / enfant / admin / dernier admin)

- **Parent** : `expenses=edit` forcé ; accès complet (créer/éditer/régler/supprimer/rappel/solde).
- **Beau-parent** : selon `Right.expenses` (`none|view|edit`). Peut être payeur ? **Non** : le payeur est un membre **parent** (cdc §2.7). Un beau-parent `edit` saisit pour le compte d'un parent payeur, mais n'est pas lui-même payeur ni dans le solde inter-parents. (Si une famille souhaite l'inclure, c'est hors cdc → non livré.)
- **Enfant** : `expenses` **forcé `none`** ; tout accès → `403` ; onglet masqué ; topic Mercure non délivré.
- **Admin** : seul `family_settings=edit` (porté par un admin) peut basculer `expenses_enabled`. L'admin n'a pas de privilège dépenses particulier au-delà de son rôle `parent`.
- **Dernier admin** : aucun chemin du Lot 10 ne retire/rétrograde un admin ; l'invariant reste garanti par le Lot 1. Désactiver le module ne touche pas la composition admin.
- **Membre `invited` (non `active`)** : aucun accès dépenses tant que le membership n'est pas `active`.

### 4.5 Scope famille (`X-Family-Id`)

- **Header absent** sur une route scopée → `400`/`403` (selon convention Lot 0/1), jamais d'accès non scopé.
- **`X-Family-Id` ≠ famille de l'URL** → `403`/`404` (incohérence rejetée).
- **Dépense d'une autre famille** (`/api/expenses/{id}` dont la dépense n'est pas dans la famille active) → `403`/`404` (anti-IDOR), jamais de fuite cross-famille.
- **Multi-famille** : un utilisateur membre de plusieurs familles voit des soldes strictement isolés par famille active ; bascule de famille recharge un contexte propre.

### 4.6 Valeurs vides / limites

- **Titre vide** → `422`. Titre très long → tronqué à la longueur max validée (`422` au-delà).
- **Aucun enfant** → `422` (au moins un enfant ; ou « toute la famille » si la convention l'autorise — voir décision ci-dessous).
- **Décision « toute la famille » vs enfants** : le cdc §2.7 mentionne « enfant(s) concerné(s) » via `expense_child`. Contrairement à l'agenda/échanges, **aucun flag `whole_family` n'est prévu** pour les dépenses dans le modèle §5.2. Décision de spec : exiger **au moins un enfant** dans `expense_child` ; ne pas inventer de `whole_family` côté dépenses.
- **`shares` vide alors que `share_type` fourni** → `422`.
- **Catégorie hors énumération** → `422`.
- **Famille avec un seul parent** (parent unique) : le partage 50/50 par défaut n'a qu'un seul parent ⇒ ce parent porte 100 % ; le solde inter-parents est trivialement « équilibré » (pas de second parent). Comportement défini, pas d'erreur.
- **Famille sans enfant** : impossible d'attacher un enfant ⇒ création refusée (`422`, au moins un enfant requis). UI : message localisé « ajoutez d'abord un enfant ».

### 4.7 Échecs externes (e-mail, API vacances, Mercure hors-ligne)

- **E-mail de rappel en échec** (SMTP down) : envoi via Messenger ⇒ **retry** avec backoff ; l'action utilisateur n'est pas bloquée (la dépense reste non réglée) ; échec final journalisé. L'UI confirme la **mise en file** du rappel, pas la remise effective.
- **API vacances scolaires** : sans objet pour le Lot 10 (aucune dépendance aux vacances). Aucun appel à `SchoolHolidaysProvider`.
- **Mercure hors-ligne / indisponible** : les mutations REST réussissent quand même (la publication temps réel est best-effort, asynchrone) ; l'écran se met à jour au prochain refresh / reconnexion SSE. Aucune mutation ne dépend de la réussite de la publication Mercure. Échec de publication journalisé, pas remonté comme erreur métier.
- **Idempotency store indisponible** : dégradation contrôlée (au pire, risque de doublon best-effort signalé) ; ne bloque pas la création en ligne.

### 4.8 Conflits offline (mobile)

- **Création offline rejouée** : `Idempotency-Key` ⇒ pas de doublon (US-DEP-8).
- **Édition offline d'une dépense supprimée côté serveur** : refus serveur (`404`) ; le client résout (informe l'utilisateur ; option de recréation explicite).
- **Module désactivé pendant l'offline** : rejeu → `404` ; mutation locale invalidée, message clair.
- **Solde local vs serveur** : le solde affiché hors-ligne est **provisoire** (calculé sur le cache local) et clairement marqué « non synchronisé » ; il est remplacé par l'état canonique à la reconnexion.
- **Ordre des mutations outbox** : les mutations d'une même dépense sont rejouées dans l'ordre d'émission (FIFO par entité) pour éviter qu'une édition précède sa création.

### 4.9 Sécurité / RGPD

- **Champs personnels** : voir §5 et §7. Aucune donnée de santé n'est introduite (la catégorie `sante` reste une étiquette budgétaire, pas un champ typé santé — invariant cdc §1.4.1).
- **Anonymisation d'un membre supprimé** (RGPD, Lot 8) : si un parent supprime son compte, ses dépenses **ne sont pas effacées** (intégrité des comptes partagés, analogie avec les messages/notes anonymisés cdc §7.1) ; le payeur/membre concerné devient « membre supprimé » (pseudonyme), le montant et les parts restent pour préserver la cohérence du solde historique. La règle d'anonymisation pour `Expense`/`ExpenseShare` est ajoutée au RGPD Manager.
- **Export RGPD** (`/api/me/export`) : les dépenses où l'utilisateur est payeur ou partie prenante sont incluses dans son export (montants, parts, dates), localisées.
- **AuditLog** : création/édition/règlement/suppression de dépense + envoi de rappel + bascule `expenses_enabled` sont journalisés (append-only) avec acteur, famille, entité.
- **Pas de fuite cross-famille / famille liée** : l'overlay famille liée (Lot 5) **n'expose jamais** les dépenses (il ne concerne que l'agenda en lecture seule, cdc §2.1/§7) ; aucune dépense d'une famille liée n'est visible.
- **Rate-limiting du rappel** : cooldown anti-spam (ex. 1 rappel / dépense / fenêtre de N heures) pour éviter le harcèlement par e-mail ; au-delà, `429`/message localisé.

### 4.10 i18n / localisation

- **Toutes les chaînes** (UI web/mobile + e-mails) externalisées (ICU), fr-FR livrée ; ajout d'une langue = nouveau fichier, sans code.
- **Format monétaire** : EUR formaté fr-FR (`1 234,56 €`), via le formateur ICU/Intl ; jamais de format codé en dur.
- **Pluriels / genre** : libellés type « 1 dépense » / « N dépenses », « réglée »/« réglées » via ICU.
- **Dates** : formatées selon la locale + fuseau famille ; jamais de format ISO brut à l'écran.
- **Noms de catégories** : libellés traduits (Santé, Scolarité, Activités, Vêtements, Autre) ; la valeur stockée reste la clé d'énumération (`sante`, `scolarite`, …), jamais le libellé affiché.

---

## 5. Modèle de données touché

> Référence cdc §5.2. Tous les entités ont `id` (UUID), `created_at`, `updated_at`. Aucun champ typé santé n'est créé. Le Lot 10 **active** des entités déjà réservées en v1.

### 5.1 `Expense` *(v2)* — créée/activée

| Champ | Type | Contraintes / notes |
|---|---|---|
| `id` | UUID | PK |
| `family_id` | FK → Family | scope famille obligatoire ; index |
| `title` | string | non vide, longueur max validée |
| `category` | enum | `sante` \| `scolarite` \| `activites` \| `vetements` \| `autre` |
| `payer_membership_id` | FK → Membership | doit être un membre **parent** actif de la famille |
| `amount_cents` | int | > 0, ≤ borne max (config) |
| `currency` | string(3) | `EUR` en v2 (réservé multi-devise) |
| `expense_date` | date | jour civil, fuseau famille à l'affichage |
| `settled` | bool | défaut `false` |
| `created_at` / `updated_at` | timestamptz | |

- Relation N-N vers `Child` via table de liaison **`expense_child`** (`expense_id`, `child_id`) — au moins une ligne par dépense.
- Relation 1-N vers `ExpenseShare`.

### 5.2 `ExpenseShare` *(v2)* — créée/activée

| Champ | Type | Contraintes / notes |
|---|---|---|
| `id` | UUID | PK |
| `expense_id` | FK → Expense | cascade delete |
| `membership_id` | FK → Membership | membre concerné par la part (parent pour le solde inter-parents) |
| `share_type` | enum | `percent` \| `amount` |
| `value` | numérique | si `percent` : [0,100] ; si `amount` : centimes int ≥ 0 |

- **Unicité** : `(expense_id, membership_id)` — un seul partage par membre et par dépense.
- Le **montant effectif** par part est dérivé (§3.1) ; pas obligatoirement stocké. La source de vérité est `(share_type, value)` + `Expense.amount_cents`.

### 5.3 `expense_child` *(v2)* — table de liaison créée/activée

| Champ | Type | Contraintes |
|---|---|---|
| `expense_id` | FK → Expense | PK composite |
| `child_id` | FK → Child | PK composite ; même famille que l'Expense |

- **Unicité** : `(expense_id, child_id)`.
- (Optionnel, pondération par enfant §3.1.D) : si la pondération doit être persistée, ajouter `weight int` (défaut 1) sur cette table de liaison. **Décision de spec** : ajouter `weight` (int ≥ 1, défaut 1) pour matérialiser la pondération par enfant prévue au cdc §2.7, plutôt que de la garder volatile. **Cette colonne étend le schéma cdc §5.2 (`expense_child` y est sans `weight`) → à valider avec le porteur produit (voir callout §2).** Rappel §3.1.D : `weight` ne sert que de **dimension d'attribution par enfant** (reporting) ; il ne modifie jamais le partage entre parents (et il est explicitement ignoré pour le partage lorsque `share_type=amount`).

### 5.4 `Family` — champ déjà présent, utilisé

- `expenses_enabled` (bool, déjà réservé v1) : pilote l'activation. Le Lot 10 le **consomme** (lecture) et le **modifie** via `PATCH /api/families/{id}` (admin). Aucune migration de schéma sur ce champ s'il existe déjà.

### 5.5 `Membership` / `Right` — consommés

- `Right.domain = 'expenses'`, `level ∈ {none, view, edit}` (déjà réservé v1). Le Lot 10 active le Voter correspondant. Le rôle `parent` force `edit`, le rôle `child` force `none`.

### 5.6 `NotificationPref` — catégorie consommée

- `category = 'expenses'`, `channel = 'email'` (canal v1). Le Lot 10 produit les déclencheurs « dépense ajoutée / réglée / rappel ». (Canal `push` = Lot 11.)

### 5.7 Champs chiffrés 🔒

- **Aucun champ 🔒 nouveau** n'est introduit par le Lot 10. Les champs dépenses (titre, montant, catégorie, date) ne sont pas marqués chiffrés dans le cdc §5.2 (seuls `User.full_name`, `User.phone`, `Message.text`, `Note.text`, `Invitation.email` le sont). Le titre d'une dépense est un libellé fonctionnel ; il n'est pas typé sensible. **Ne pas ajouter de chiffrement non prévu** (cohérence avec le modèle §5.2). Si une analyse RGPD ultérieure le requiert, ce serait une évolution explicite hors périmètre Lot 10.

### 5.8 Migrations

- Migration Doctrine versionnée créant/complétant `expense`, `expense_share`, `expense_child` (+ colonne `weight` sur `expense_child`), avec FK, index (`family_id`, `expense_date`, `category`, `settled`) et contraintes d'unicité. Idempotente vis-à-vis d'un éventuel squelette v1.

---

## 6. Endpoints API concernés

> Base : cdc §6.2 « Dépenses (v2) ». Conventions §6.1 : REST/JSON sous `/api`, `Authorization: Bearer`, `X-Family-Id` sur les routes scopées, `Accept-Language`, pagination `?page=&itemsPerPage=`, erreurs RFC 7807 (`application/problem+json`), `Idempotency-Key` accepté sur les POST issus de l'outbox.

| Méthode | Chemin | Droit requis | Description |
|---|---|---|---|
| GET | `/api/families/{id}/expenses` | `expenses:view` | Liste paginée des dépenses (filtres `?category=&childId=&settled=&page=&itemsPerPage=`), triée par date. |
| POST | `/api/families/{id}/expenses` | `expenses:edit` | Crée une dépense (+ parts + enfants). Accepte `Idempotency-Key`. → `201`. |
| PATCH | `/api/expenses/{id}` | `expenses:edit` | Met à jour une dépense (champs, partage) **ou** la marque réglée (`{settled:true}`). Idempotent sur `settled`. |
| DELETE | `/api/expenses/{id}` | `expenses:edit` | Supprime une dépense (cascade parts + liaisons). → `204`. *(Extension du contrat cdc §6.2 — à valider porteur produit, voir callout §2.)* |
| GET | `/api/families/{id}/expenses/balance` | `expenses:view` | Solde « qui doit quoi » (inter-parents) + répartition par catégorie + total engagé + ventilation réglé/non réglé. |
| POST | `/api/expenses/{id}/reminder` | `expenses:edit` | Envoie un rappel e-mail au(x) débiteur(s) (Messenger, async, anti-spam). *(Extension du contrat cdc §6.2 pour l'action « Envoyer un rappel » §2.7 — à valider porteur produit, voir callout §2.)* |
| PATCH | `/api/families/{id}` | `family_settings:edit` | (déjà existant Lot 1) Bascule `expensesEnabled` (active/désactive le module). |

**Conventions spécifiques au lot**
- **Scope** : toutes les routes ci-dessus (hors `PATCH /api/families/{id}` qui est family_settings) exigent `X-Family-Id` cohérent et le module `expenses_enabled` actif ; sinon `404` (module off) / `403` (scope/IDOR).
- **Idempotence** : `POST /expenses` et `POST /reminder` honorent `Idempotency-Key` (rejeu outbox → même réponse, pas de doublon, pas de double e-mail).
- **Erreurs** : `403` droit insuffisant (`detail` localisé « Droit 'expenses:edit' requis. »), `404` module désactivé / ressource hors famille, `422` validation (montant, parts, enfant, catégorie), `429` rappel trop fréquent.
- **OpenAPI** : ces opérations sont exposées dans le schéma exporté → régénération du client Dart (Flutter) dans `/contracts`.

---

## 7. Invariants & règles métier

> Uniquement les invariants pertinents pour ce lot (cdc / CLAUDE.md).

1. **Scope famille obligatoire** : toute ressource dépense est scopée via `X-Family-Id`, validé contre les memberships. Jamais de requête dépense non scopée ; isolation stricte entre familles (anti-IDOR).
2. **Autorisation par Voters** : Voter `expenses` (niveaux `none|view|edit`) sur **chaque** ressource (collection + item). `parent` ⇒ `edit` forcé ; `child` ⇒ `none` **forcé** (aucun accès, aucune combinaison de `Right` ne l'ouvre).
3. **Dernier admin protégé** : non régressé par le Lot 10 (la désactivation du module ne touche pas la composition admin).
4. **Activation par module** : `expenses_enabled` pilote l'existence de l'onglet, des écrans et de l'API ; module off ⇒ `404`. Optionnel pour familles unies.
5. **Payeur = parent** : le payeur d'une dépense et les parties du solde « qui doit quoi » sont des membres **parent**. Un beau-parent peut éditer (selon droit) mais n'est pas payeur ni dans le solde inter-parents.
6. **Exactitude monétaire** : montants en centimes entiers ; `Σ parts == amount_cents` (invariant testé) ; `Σ net == 0` (conservation du solde). Aucun flottant.
7. **Pas de données de santé** : la catégorie `sante` est une étiquette budgétaire ; aucun champ typé santé n'est créé. (Invariant transverse cdc §1.4.1 / CLAUDE.md.)
8. **Registre des données personnelles** : tout nouveau champ personnel ⇒ entrée de registre (`/backend/docs/registre-donnees.md` + attribut déclaratif sur l'entité) ; le test de cohérence CI doit passer. (Le Lot 10 n'introduit pas de champ 🔒 ; il documente néanmoins `Expense`/`ExpenseShare` au registre — finalité, base légale, rétention, destinataires — car ce sont des données personnelles relatives à la famille/aux enfants.)
9. **Chiffrement au repos** : aucun nouveau champ 🔒 (cohérent §5.2). Ne pas introduire de chiffrement non prévu.
10. **i18n** : toutes chaînes UI + e-mail externalisées (ICU), fr-FR livrée ; format monétaire/date localisé ; ajout de langue sans code.
11. **Dates non codées en dur** : fuseau `Europe/Paris` par défaut ; la date des maquettes (23/06/2026) n'apparaît jamais dans le code ; tests sur dates relatives.
12. **Temps réel via Mercure** : topic `/families/{id}/expenses` ; Turbo Streams (web), SSE/JSON (mobile) ; abonnement autorisé seulement si `expenses ≥ view` + module on + non-enfant. Publication best-effort (ne bloque pas les mutations).
13. **Idempotence offline** : `Idempotency-Key` sur les POST outbox ; autorité serveur, last-write-wins champs simples ; conflits signalés.
14. **AuditLog** : actions sensibles (création/édition/règlement/suppression/rappel/bascule module) journalisées, append-only.
15. **Anonymisation préservant l'intégrité** : suppression d'un compte ⇒ dépenses conservées, membre devenu « membre supprimé », solde historique cohérent (cf. RGPD Manager Lot 8).
16. **Architecture verrouillée** : backend Symfony 8.1 + API Platform 4 + Doctrine + Mercure + Messenger / PostgreSQL 16 ; front web Twig + Turbo + Stimulus + AssetMapper (aucun framework JS) ; mobile Flutter avec client généré OpenAPI ; rien de tout cela n'est remis en cause.

---

## 8. Definition of Done (DoD)

> DoD générale du cdc §9 + critères spécifiques au Lot 10.

**Tests**
- **Unitaires (PHPUnit)** :
  - Calcul des parts §3.1 : 50/50 défaut, percent, amount, pondération par enfant ; **balayage d'arrondis** (montants 1..N centimes) prouvant `Σ parts == amount_cents` et reproductibilité du centime résiduel.
  - **Interaction `child_weights` × `share_type` (§3.1.D)** : (a) percent/50-50 + `child_weights` ⇒ montant effectif par parent **identique** au cas sans pondération (neutralité par linéarité) ; (b) `share_type=amount` + `child_weights` ⇒ `montant_effectif(parent) == value` saisie, **indépendamment** de `child_weights` (pondération ignorée pour le partage parent) ; (c) ventilation par enfant : `Σ ventilation_enfant == amount_cents` dans tous les cas.
  - Solde inter-parents §3.2 : conservation `Σ net == 0` ; exclusion des réglées ; cas parent unique ; jeux mixtes réglé/non réglé.
  - Agrégats par catégorie + total engagé §3.3.
  - Voter `expenses` §3.6 : matrice complète (parent⇒edit, child⇒none forcé sur toutes combinaisons de `Right`, step_parent selon droit, module off ⇒ deny).
  - Validation : montant, bornes, parts incohérentes, payeur non-parent, enfant hors famille, catégorie invalide.
- **Fonctionnels / API (client de test Symfony / API Platform)** : auth + scope `X-Family-Id` + droits sur tous les endpoints §6 ; `201/200/204/403/404/422/429` ; idempotence (`Idempotency-Key`) ; module off ⇒ `404` ; isolation cross-famille (anti-IDOR) ; rappel e-mail mis en file ; pas d'accès enfant.
- **E2E web (Playwright)** : parcours « activer le module (admin) → créer une dépense (50/50 puis personnalisée) → voir le solde et la répartition par catégorie → marquer réglé → envoyer un rappel », mise à jour **temps réel** entre deux sessions (Turbo Streams), onglet absent pour un enfant / module désactivé, responsive desktop/mobile, accessibilité de base.
- **Mobile (Flutter)** : tests unitaires (mappers, calcul de solde local), widgets (écran liste/détail/création de dépense, encart solde), intégration **offline → resynchro outbox** avec `Idempotency-Key` (création hors-ligne rejouée sans doublon ; conflit signalé). Onglet dépenses retiré si module off / enfant.

**Livrables techniques**
- **Migrations Doctrine** fournies et réversibles (`expense`, `expense_share`, `expense_child` + `weight`, index, unicité).
- **OpenAPI à jour** (endpoints §6 exposés) ⇒ **client Dart régénéré** dans `/contracts`, consommé par le mobile.
- **i18n (fr-FR) câblée** : toutes chaînes UI + e-mails (ICU), formats monétaire/date localisés ; aucun libellé en dur.
- **Registre des données personnelles** à jour (`/backend/docs/registre-donnees.md` + attributs déclaratifs sur `Expense`/`ExpenseShare`) ; **test de cohérence CI vert**.
- **`/infra/INSTALL.md`** mis à jour **si** une dépendance change (le Lot 10 ne devrait en introduire aucune nouvelle ; vérifier et documenter le cas échéant).
- **AuditLog** branché sur toutes les actions sensibles du lot ; règle d'**anonymisation** `Expense`/`ExpenseShare` ajoutée au RGPD Manager.

**Qualité & conformité**
- **Analyse statique propre** : PHPStan (niveau élevé) + php-cs-fixer côté backend ; `dart analyze` + `dart format` côté mobile ; aucun warning bloquant.
- **Conformité design system §8** : couleurs catégories dépenses exactes (Santé `#C75D5D`, Scolarité `#C99A3F`, Activités `#5E7CB8`, Vêtements `#7E9B7A`, Autre `#857E71`) ; typographies (Bricolage Grotesque / Hanken Grotesk) ; layout desktop grille 2 colonnes pour l'écran Dépenses, sidebar repliable ; mobile iOS « liquid glass » + Android Material adapté ; onglet conditionnel.
- **Couverture** ≥ seuil défini au Lot 0.
- **Périmètre v2 respecté** : pas de push (Lot 11), pas de multi-devise réelle, pas de pièces jointes, pas de `whole_family` dépenses, pas de champ santé typé.
