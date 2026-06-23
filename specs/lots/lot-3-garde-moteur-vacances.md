# Lot 3 — Garde (moteur + vacances)

> Spécification détaillée d'implémentation. Source de vérité : `specs/cdc.md` (§2.4, §3.3, §4.7, §5.2, §6.2, invariant « Priorité de la garde ») et `CLAUDE.md` (invariants verrouillés). En cas d'ambiguïté de rendu, les maquettes Claude Design font foi. Ce document ne contredit aucune décision d'architecture verrouillée (§0 cdc).

---

## 0. Objectif & périmètre

### 0.1 Objectif

Doter les familles **séparées** d'un planning de garde **généré automatiquement et matérialisé en base** : à partir d'un modèle de rythme (`CustodyPattern`) et d'une date d'ancrage, un moteur **idempotent** produit un enregistrement `CustodyDay` par jour (parent attribué) sur un **horizon glissant d'environ 18 mois**, surchargé par les **règles de vacances scolaires** (`HolidayRule`) puis par les **exceptions manuelles** (`CustodyException`), selon l'ordre de priorité **exception > vacances > base**. Le lot couvre aussi l'**import du calendrier officiel des vacances** (adaptateur isolé `SchoolHolidaysProvider`, asynchrone), le **calcul de statistiques** (jours par parent), les **écrans Garde** (calendrier coloré, détail du jour, périodes spéciales) et **Paramètres › Garde**, et la **régénération planifiée** de l'horizon (Scheduler).

### 0.2 Dans le périmètre

- Entité `CustodyPattern` (4 modèles : `alternating`, `pattern_2_2_3`, `midweek_weekends`, `custom` nuit par nuit sur 14 créneaux) + paramètres communs (`anchor_date`, `handover_weekday`, `handover_time`, `parent_a_membership_id`, `parent_b_membership_id`) + champ de configuration dédié `settings` (jsonb) requis ssi `kind=midweek_weekends` (cf. §5.1).
- Moteur de génération idempotent → `CustodyDay` (un par jour, `source ∈ {base, holiday, exception}`).
- Entité `CustodyException` (échange d'un jour ou d'une plage) + logique « Échanger ce jour ».
- Entités `SchoolHoliday` + `HolidayRule` (5 genres : `alternating_weeks`, `split_50_50`, `fixed_parent`, `eve_alternating`, `custom`).
- Import asynchrone des vacances depuis la source officielle `fr-en-calendrier-scolaire` (adaptateur `SchoolHolidaysProvider` isolé, avec cache et job de rafraîchissement annuel) + création manuelle de période.
- Calcul des statistiques (jours par parent sur une période affichée), à partir des `CustodyDay` **effectifs**.
- Endpoints API §6.2 « Garde » (REST/JSON, scope famille, Voters `custody`).
- Écrans **web** (Symfony UX : Twig/Turbo/Stimulus) **et** **mobile** (Flutter) : calendrier mensuel coloré, détail du jour, périodes spéciales, Paramètres › Garde (builder de rythme dont custom 2 semaines, jour/heure d'échange, import, exceptions).
- Régénération planifiée de l'horizon glissant (Scheduler + Messenger).
- Publication temps réel sur le topic `/families/{id}/custody` (Turbo Streams web, SSE mobile).
- Masquage de l'onglet Garde pour les familles **unies** (`type = intact`).

> **Terminologie famille (à respecter sans divergence)** : la valeur d'énumération **technique** stockée en base est `separated` / `intact` (cdc §5.2). Les **libellés UI/métier** francophones correspondants sont « famille séparée » / « famille unie » (et « séparée »/« unie » en forme courte). La présente spec emploie volontairement `separated`/`intact` pour désigner la donnée et « séparée »/« unie » pour le libellé : c'est **la même** notion, jamais deux valeurs distinctes. Toute chaîne UI passe par l'i18n ICU (clés du type `family.type.separated` / `family.type.intact`) ; ne jamais afficher la valeur d'énumération brute ni la coder en dur dans un template.

### 0.3 Hors périmètre

- **Familles liées & overlay** de la garde : **Lot 5** (le présent lot affiche uniquement la garde de la famille active ; « La garde n'est jamais modifiée par l'overlay »).
- **Notifications e-mail** de changement de garde (US §2.9) : **Lot 7** (le présent lot publie l'événement temps réel et écrit l'`AuditLog`, mais ne câble pas l'envoi d'e-mail). Le déclencheur métier est néanmoins prévu pour être consommé par le Lot 7.
- **Agenda / activités du jour** dans le détail du jour : les événements proviennent du **Lot 4**. Le présent lot prévoit l'emplacement « activités du jour » mais l'alimente seulement si le Lot 4 est livré ; sinon section vide.
- **Offline riche mobile** (outbox complet) : harnais mis en place au Lot 0 ; le présent lot doit être **compatible** (mutations idempotentes via `Idempotency-Key`, résolution de conflits autorité-serveur) mais le parcours offline de bout en bout est validé au **Lot 8**.

### 0.4 Contexte v1 / v2

Le planning de garde est **v1** (cdc §1.3). Aucune dépendance aux dépenses (v2). Aucune donnée de santé n'est créée (invariant verrouillé). La seule locale livrée est `fr-FR`, fuseau par défaut `Europe/Paris`, mais toutes les chaînes (UI + e-mail à venir) sont externalisées en ICU dès ce lot.

---

## 1. Dépendances

### 1.1 Lots prérequis (avant)

| Lot | Pourquoi |
|---|---|
| **Lot 0 — Fondations** | Monorepo, Symfony + API Platform + Doctrine + PostgreSQL, JWT, squelette Voters, i18n ICU, Mailer, Mercure, **Messenger** et **Scheduler** (indispensables au moteur asynchrone et à la régénération planifiée), AssetMapper/Turbo/Stimulus côté web, client Flutter généré depuis l'OpenAPI + couche offline, harnais de tests (PHPUnit, Playwright, `flutter test`, PHPStan), squelette RGPD + `registre-donnees.md`. |
| **Lot 1 — Familles, membres, droits, invitations, liens** | Le moteur a besoin de deux **memberships parents** (`parent_a_membership_id`, `parent_b_membership_id`) pour attribuer les jours. Scope famille `X-Family-Id`, Voters par domaine, et **Voter `custody`** (niveaux `none|view|edit`) sont nécessaires. La distinction `type` famille (`separated` vs `intact`) qui conditionne l'affichage de l'onglet provient de ce lot. La protection du **dernier admin** et la couleur d'affichage des membres (`display_color`, utilisée pour colorer parent A/B) viennent aussi de là. |

### 1.2 Lots débloqués (après)

| Lot | Ce qu'il consomme du Lot 3 |
|---|---|
| **Lot 4 — Agenda** | Réutilise l'adaptateur `SchoolHolidaysProvider` et les `SchoolHoliday` pour l'option « exclure les vacances scolaires » des récurrences. Le détail du jour de garde affichera les événements agenda. |
| **Lot 5 — Familles liées & overlay** | Étend l'écran Garde avec l'overlay lecture seule de la famille liée (couleur `#9C6FB0`), **sans jamais modifier la garde**. S'appuie sur les `CustodyDay`/écrans du Lot 3. |
| **Lot 7 — Notifications e-mail** | Consomme le déclencheur « changement de garde (échange/exception) » (§2.9) émis par ce lot. |
| **Lot 8 — Offline & RGPD Manager** | Valide le parcours offline des exceptions de garde (résolution de conflits autorité-serveur §4.4) et branche l'audit/rétention sur les entités de garde. |

---

## 2. User stories complètes

> Format : `En tant que <rôle>, je veux <action> afin de <bénéfice>`. CA en **Étant donné / Quand / Alors**, plusieurs scénarios par US (nominal, erreur, autorisation, temps réel). Le **droit `custody`** (`none|view|edit`) est évalué par un **Voter** sur (Membre, ressource). Rappel : `parent` ⇒ `edit` forcé partout ; `child` ⇒ jamais d'édition de structure (donc jamais `custody=edit`), au plus `custody=view`.

### US-CUST-1 — Configurer un rythme de garde

**En tant que** parent (ou beau-parent avec `custody=edit`, ou admin), **je veux** configurer un rythme de garde (modèle + jour/heure d'échange + ancrage + désignation parent A/B) **afin que** le planning se génère automatiquement.

**Scénario nominal (création) :**
- **Étant donné** une famille séparée sans `CustodyPattern`, et que je dispose de `custody=edit`,
- **Quand** je soumets `PUT /api/families/{id}/custody/pattern` avec `kind=alternating`, `anchorDate`, `handoverWeekday=0` (dimanche), `handoverTime=19:00`, `parentAMembershipId`, `parentBMembershipId`,
- **Alors** le `CustodyPattern` est créé, le moteur génère les `CustodyDay` de `source=base` sur l'horizon (~18 mois à partir d'aujourd'hui), les stats sont recalculées, un `AuditLog` (`custody.pattern.updated`) est écrit, et un message est publié sur `/families/{id}/custody`.

**Scénario nominal (modification) :**
- **Étant donné** un `CustodyPattern` existant et des `CustodyException`/`HolidayRule` déjà posées,
- **Quand** je modifie le modèle (ex. `alternating` → `pattern_2_2_3`),
- **Alors** seuls les `CustodyDay` **futurs** de `source=base` sont régénérés (les jours `source=holiday` et `source=exception` ne sont **pas écrasés**), les jours passés sont **figés**, les stats sont recalculées, et le changement est poussé en temps réel.

**Scénario d'erreur (validation) :**
- **Étant donné** que je soumets un `handoverTime` hors plage (`25:00`) ou un `anchorDate` non parsable, ou `parentAMembershipId == parentBMembershipId`,
- **Quand** la requête est validée,
- **Alors** la réponse est `422 application/problem+json` avec messages localisés `fr-FR`, et **aucune** régénération n'est déclenchée.

**Scénario d'erreur (famille unie) :**
- **Étant donné** une famille `type=intact`,
- **Quand** j'appelle `PUT /api/families/{id}/custody/pattern` (mutation),
- **Alors** la réponse est **`409 Conflict`** `application/problem+json` (`type=/errors/custody-not-applicable`, `title="La garde ne s'applique qu'aux familles séparées"`), et aucun `CustodyPattern` n'est créé.

> **Convention de statut figée (famille `intact`) — vaut pour tout le lot.** Pour lever l'ambiguïté web/API : les **mutations** de garde (`PUT pattern`, `POST/DELETE exceptions`, `POST/PUT holidays*`) sur une famille `intact` renvoient **`409 Conflict`** (l'état de la ressource famille interdit l'opération — c'est un conflit d'état, pas une absence). Les **lectures** de garde (`GET .../custody/*`, `GET .../holidays`) sur une famille `intact` renvoient **`404 Not Found`** (masquage fonctionnel : la sous-ressource garde « n'existe pas » pour une famille unie). L'onglet/menu Garde est par ailleurs **masqué** côté UI (web et mobile), de sorte que le `404` n'est atteint qu'en accès direct à l'URL. Cette règle est testée (un test fonctionnel par méthode).

**Scénario autorisation refusée :**
- **Étant donné** un membre avec `custody=view` (ou `child`),
- **Quand** il appelle `PUT …/custody/pattern`,
- **Alors** `403 /errors/forbidden` « Droit 'custody:edit' requis », aucun effet de bord.

**Scénario scope famille :**
- **Étant donné** un utilisateur membre de la famille B,
- **Quand** il envoie `X-Family-Id` = famille A à laquelle il n'appartient pas,
- **Alors** `403` (scope refusé) avant même l'évaluation du Voter custody.

### US-CUST-2 — Composer un rythme personnalisé nuit par nuit (2 semaines)

**En tant que** parent / membre `custody=edit`, **je veux** composer un rythme `custom` nuit par nuit sur 2 semaines (14 créneaux, chaque créneau = parent A ou B) **afin de** modéliser un planning irrégulier propre à ma situation.

**Scénario nominal :**
- **Étant donné** l'écran builder custom (grille 14 cases),
- **Quand** j'envoie `kind=custom` avec `customPattern` = tableau de 14 entrées (`"A"`/`"B"`),
- **Alors** le pattern est enregistré, l'horizon est généré en répétant le motif de 14 jours à partir de `anchorDate`, et l'aperçu visuel reflète exactement les 14 créneaux.

**Scénario d'erreur (longueur invalide) :**
- **Étant donné** un `customPattern` de longueur ≠ 14, ou contenant une valeur ∉ {A,B},
- **Quand** je soumets,
- **Alors** `422` « Le motif personnalisé doit contenir exactement 14 créneaux (A ou B) », aucune génération.

**Scénario d'erreur (kind ≠ custom mais customPattern fourni / ou inverse) :**
- **Étant donné** `kind=alternating` avec un `customPattern` non vide, ou `kind=custom` sans `customPattern`,
- **Quand** je soumets,
- **Alors** `422` avec violation de contrainte conditionnelle, aucune génération.

**Scénario temps réel :**
- **Étant donné** un autre co-parent connecté visualisant le calendrier,
- **Quand** le pattern custom est enregistré,
- **Alors** son calendrier se met à jour via Turbo Stream / SSE (≤ ~1 s) sans rechargement manuel.

### US-CUST-3 — Importer les vacances scolaires et définir une règle par période

**En tant que** parent / membre `custody=edit`, **je veux** importer le calendrier officiel des vacances (zone/académie/année scolaire) et affecter une règle de garde à chaque période **afin de** gérer automatiquement la garde pendant les vacances.

**Scénario nominal (import) :**
- **Étant donné** une famille séparée avec `school_zone` et `academy` renseignés (réglages famille, Lot 1),
- **Quand** je déclenche `POST /api/families/{id}/holidays/import` (optionnellement avec `schoolYear`),
- **Alors** la réponse est `202 Accepted` (traitement **asynchrone** via Messenger), l'adaptateur `SchoolHolidaysProvider` interroge la source officielle filtrée par zone/académie/année, les `SchoolHoliday` (`source=official`) sont créés/mis à jour de façon **idempotente** (upsert sur clé naturelle), et le résultat est poussé en temps réel quand le job se termine.

**Scénario nominal (règle de période) :**
- **Étant donné** une `SchoolHoliday` importée sans règle,
- **Quand** j'appelle `PUT /api/holidays/{id}/rule` avec `kind=alternating_weeks` (ou `split_50_50`, `fixed_parent`, `eve_alternating`, `custom`) + `params`,
- **Alors** la `HolidayRule` (1-1) est créée/mise à jour, le moteur réécrit les `CustodyDay` de la période en `source=holiday` (écrasant la base mais **pas** les exceptions), les stats sont recalculées, temps réel poussé.

**Scénario nominal (création manuelle) :**
- **Étant donné** une période non couverte par la source officielle (ex. pont local),
- **Quand** j'appelle `POST /api/families/{id}/holidays` avec `label`, `startDate`, `endDate`,
- **Alors** une `SchoolHoliday` `source=manual` est créée et peut recevoir une règle.

**Scénario d'erreur (API officielle indisponible) :**
- **Étant donné** que la source `data.education.gouv.fr` répond en erreur ou timeout,
- **Quand** le job d'import s'exécute,
- **Alors** il **réessaie** (backoff via Messenger), et après épuisement des tentatives l'opération est marquée échouée, un message d'état localisé est affiché (« Import indisponible, réessayez plus tard »), **aucune** `SchoolHoliday` partielle/corrompue n'est laissée, et les données déjà présentes (cache) restent valides.

**Scénario d'erreur (réglages famille incomplets) :**
- **Étant donné** une famille sans `school_zone`/`academy`,
- **Quand** je déclenche l'import,
- **Alors** `422` « Zone scolaire et académie requises pour l'import » avec lien vers les réglages.

**Scénario autorisation :**
- **Étant donné** `custody=view`, **Quand** j'appelle `…/holidays/import` ou `PUT …/rule`, **Alors** `403`. La **lecture** des périodes (`GET …/holidays`) est autorisée en `custody=view`.

### US-CUST-4 — Ajouter une exception manuelle (jour ou plage)

**En tant que** parent / membre `custody=edit`, **je veux** ajouter une exception manuelle (échange d'un jour ou d'une plage de dates au profit d'un parent) **afin de** gérer un imprévu sans changer le rythme.

**Scénario nominal :**
- **Étant donné** un planning généré,
- **Quand** j'appelle `POST /api/families/{id}/custody/exceptions` avec `startDate`, `endDate`, `parentMembershipId`, `reason`,
- **Alors** une `CustodyException` est créée, les `CustodyDay` couverts passent à `source=exception` (priorité maximale, écrase base ET vacances), ils sont marqués visuellement « exceptionnel » (bordure pointillée), stats recalculées, `AuditLog` (`custody.exception.created`) écrit, temps réel poussé.

**Scénario nominal (suppression) :**
- **Étant donné** une exception existante,
- **Quand** j'appelle `DELETE …/custody/exceptions/{exceptionId}`,
- **Alors** l'exception est supprimée et les jours concernés sont **recalculés** selon la priorité restante (vacances si une règle couvre la période, sinon base), stats recalculées, temps réel poussé.

**Scénario d'erreur (bornes incohérentes) :**
- **Étant donné** `endDate < startDate`, ou des dates hors de l'horizon généré,
- **Quand** je soumets,
- **Alors** `422` « La date de fin doit être ≥ à la date de début » / « Plage hors de l'horizon de garde », aucune création.

**Scénario idempotence (offline mobile) :**
- **Étant donné** une création d'exception rejouée depuis l'outbox avec le même `Idempotency-Key`,
- **Quand** elle arrive deux fois,
- **Alors** la seconde requête renvoie la **même** ressource (pas de doublon), conformément à §6.1.

**Scénario autorisation / enfant :**
- **Étant donné** un membre `child` (au plus `custody=view`),
- **Quand** il tente de créer une exception,
- **Alors** `403`. Un beau-parent `custody=view` est aussi refusé en écriture.

### US-CUST-5 — Visualiser le calendrier coloré + statistiques

**En tant que** membre `custody=view` ou plus, **je veux** visualiser le calendrier mensuel coloré par parent (jours exceptionnels distincts) et les statistiques de jours par parent **afin de** comprendre d'un coup d'œil chez qui sont les enfants et l'équilibre de la garde.

**Scénario nominal :**
- **Étant donné** un planning généré,
- **Quand** j'ouvre l'écran Garde sur un mois,
- **Alors** chaque jour est coloré selon le parent attribué (parent A `--accent` terracotta, parent B `--positive` teal), les jours `source=exception` ont une **bordure pointillée**, les périodes de vacances sont distinguées, et un encart affiche les **stats** (jours parent A / parent B sur la période affichée), calculées sur les `CustodyDay` **effectifs**.

**Scénario nominal (navigation période) :**
- **Quand** je change de mois ou demande `GET …/custody/days?from=&to=` et `…/custody/stats?from=&to=`,
- **Alors** les jours et stats correspondent exactement à la fenêtre demandée (bornes incluses), fuseau `Europe/Paris`.

**Scénario données absentes :**
- **Étant donné** une famille séparée sans `CustodyPattern` configuré,
- **Quand** j'ouvre Garde,
- **Alors** un état vide localisé invite à configurer un rythme (CTA vers Paramètres › Garde si `custody=edit`, sinon message informatif).

**Scénario famille unie :**
- **Étant donné** une famille `type=intact`,
- **Quand** je navigue,
- **Alors** l'onglet/menu **Garde est masqué** (web et mobile) ; un accès direct à l'URL de **lecture** renvoie **`404`** (masquage fonctionnel, message « toute la famille réunie »), et toute **mutation** renvoie **`409`** (cf. convention figée en US-CUST-1).

**Scénario autorisation :**
- **Étant donné** `custody=none`,
- **Quand** j'accède à Garde,
- **Alors** `403` / écran refusé localisé, l'onglet peut être masqué selon le droit.

**Scénario temps réel :**
- **Étant donné** deux co-parents sur l'écran Garde,
- **Quand** l'un pose une exception ou change une règle,
- **Alors** le calendrier et les stats de l'autre se mettent à jour en direct (Turbo Stream / SSE) **sans** écraser une saisie en cours.

### US-CUST-6 — Détail d'un jour + proposer un échange

**En tant que** membre `custody=view` ou plus, **je veux** ouvrir le détail d'un jour (chez quel parent, transmission, activités) et, si j'ai le droit, proposer un échange de ce jour **afin de** consulter l'info de garde et réagir vite.

**Scénario nominal (consultation) :**
- **Étant donné** un jour donné,
- **Quand** j'ouvre son détail,
- **Alors** s'affichent : le parent chez qui sont les enfants, l'info de **transmission** « Retour chez {Parent} le {date} à {handoverTime} » calculée à partir du jour d'échange et de l'heure d'échange, les activités du jour (agenda, si Lot 4 livré, sinon section vide), et les actions « Échanger ce jour » et « Modifier la garde » (visibles seulement si `custody=edit`).

**Scénario nominal (échanger ce jour) :**
- **Étant donné** un jour attribué au parent A et `custody=edit`,
- **Quand** je clique « Échanger ce jour »,
- **Alors** une `CustodyException` d'un jour est créée attribuant ce jour au parent B (bascule), `source=exception`, marquée « exceptionnel », stats recalculées, temps réel poussé, et l'info de transmission est recalculée.

**Scénario d'erreur (jour hors horizon / passé) :**
- **Étant donné** un jour antérieur à aujourd'hui (selon politique) ou hors horizon,
- **Quand** je tente « Échanger ce jour »,
- **Alors** action désactivée / `422` « Impossible d'échanger un jour passé/hors horizon ».

**Scénario autorisation :**
- **Étant donné** `custody=view`,
- **Quand** j'ouvre le détail,
- **Alors** je vois l'info mais **pas** les actions d'édition ; un appel direct à l'API d'échange renvoie `403`.

### US-SET-3 — (Admin / famille séparée) configurer la garde

**En tant qu'**admin (parent) d'une famille séparée, **je veux** accéder à Paramètres › Garde pour configurer le modèle, le jour/heure d'échange, importer les vacances et gérer les exceptions **afin de** centraliser tout le paramétrage de garde.

**Scénario nominal :**
- **Étant donné** une famille séparée et un admin,
- **Quand** j'ouvre Paramètres › Garde,
- **Alors** je vois le builder de rythme (dont **custom 2 semaines**), les sélecteurs jour/heure d'échange et parent A/B, le bouton d'import des vacances, la liste des périodes avec leur règle, et la liste des exceptions (créer/supprimer).

**Scénario famille unie :**
- **Étant donné** une famille `type=intact`,
- **Quand** j'ouvre Paramètres,
- **Alors** la section **Garde n'apparaît pas**.

**Scénario autorisation :**
- **Étant donné** un membre non-admin sans `custody=edit` (ou `family_settings ≠ edit` pour la partie réglages structurels),
- **Quand** il accède à Paramètres › Garde,
- **Alors** sections en lecture seule ou masquées selon le droit ; toute mutation renvoie `403`.

### US-CUST-7 (ajout) — Régénération automatique de l'horizon glissant

**En tant que** système (tâche planifiée), **je veux** étendre périodiquement l'horizon de génération **afin que** le planning reste rempli ~18 mois en avant sans intervention.

**Scénario nominal :**
- **Étant donné** un `CustodyPattern` actif et un horizon qui se rapproche de sa borne,
- **Quand** le Scheduler déclenche la régénération (ex. quotidien/hebdomadaire),
- **Alors** le moteur génère les `CustodyDay` `source=base` manquants jusqu'à `aujourd'hui + 18 mois`, **sans** toucher aux jours passés, ni écraser exceptions/règles vacances, de façon **idempotente** (relancer ne crée pas de doublon).

**Scénario d'erreur (job concurrent) :**
- **Étant donné** deux exécutions concurrentes pour la même famille,
- **Quand** elles tournent en parallèle,
- **Alors** un **verrou** (lock) par famille empêche la double génération ; l'upsert sur `(family_id, date)` garantit l'absence de doublon même sans verrou.

### US-CUST-8 (ajout) — Rafraîchissement annuel des vacances

**En tant que** système, **je veux** rafraîchir annuellement le calendrier officiel des vacances **afin que** la nouvelle année scolaire soit disponible automatiquement.

**Scénario nominal :**
- **Étant donné** des familles séparées avec `school_zone`/`academy`,
- **Quand** le Scheduler déclenche le rafraîchissement annuel,
- **Alors** `SchoolHolidaysProvider` récupère la nouvelle `annee_scolaire`, upsert les `SchoolHoliday` `source=official` **sans** dupliquer ni écraser les périodes `source=manual`, ni les `HolidayRule` déjà affectées à des périodes inchangées.

**Scénario idempotence :**
- **Quand** le rafraîchissement est rejoué,
- **Alors** aucune période en double, les règles existantes conservées sur les périodes dont les bornes n'ont pas changé.

### US-CUST-9 (ajout) — Réassigner les parents A/B du pattern

**En tant que** parent / `custody=edit`, **je veux** définir/changer quels memberships sont parent A et parent B **afin que** les couleurs et attributions reflètent la réalité.

**Scénario nominal (échange A↔B) :**
- **Étant donné** un `CustodyPattern` existant et des `CustodyDay` déjà générés (base + vacances + exceptions),
- **Quand** je modifie `parentAMembershipId`/`parentBMembershipId` (réassignation des memberships parents),
- **Alors** le moteur applique la **convention de re-mapping figée** ci-dessous : les jours `source=base` et `source=holiday` **futurs** sont **régénérés** à partir des règles (donc re-pointés sur les bons memberships), les jours **passés** restent figés, les jours `source=exception` ne sont **pas** écrasés mais leur `parent_membership_id` est **conservé tel quel** (une exception cible un membership précis, pas un slot A/B), les couleurs d'affichage suivent `display_color` des nouveaux memberships, les stats sont recalculées, `AuditLog` (`custody.pattern.updated`) écrit, temps réel poussé.

**Scénario d'erreur (membership invalide) :**
- **Étant donné** un `parentAMembershipId` pointant un membre `child`, un beau-parent, un membre d'une autre famille, ou un membership inexistant,
- **Quand** je soumets,
- **Alors** `422` « Les parents A et B doivent être des membres parents de la famille », aucune génération.

> **Convention de re-mapping figée (changement de `parent_a`/`parent_b`).** Une réassignation des memberships parents est traitée **exactement comme une modification de pattern** (US-CUST-1 scénario modification) : on **régénère le futur** sans toucher au passé ni écraser exceptions/règles. Deux cas à distinguer explicitement, car la lacune portait sur ce point :
> 1. **Jours `base`/`holiday` (futurs)** : ils sont définis par des **slots logiques A/B** (le motif, les règles de période). Après changement, la régénération recalcule chaque jour et écrit le `parent_membership_id` **mis à jour** ⇒ les jours base/vacances suivent automatiquement les nouveaux memberships A/B (et donc les nouvelles couleurs). Les jours **passés** (`date < aujourd'hui`) ne sont **pas** re-mappés (le passé est figé, invariant §3.5).
> 2. **Jours `source=exception`** : une `CustodyException` référence un **membership concret** (`parent_membership_id`), pas un slot A/B. Le changement de désignation A/B **ne ré-écrit pas** ces jours : leur `parent_membership_id` est **conservé** tel quel (l'exception « ce jour-là chez Camille » reste chez Camille même si Camille passe de parent A à parent B). Seule la **couleur** affichée peut changer si le membership a changé de slot, car la couleur dérive de `display_color` du membership ciblé, pas du slot. Aucune corruption ni doublon (upsert `(family_id,date)`).
>
> Cette convention est **couverte par des tests** (régénération base/holiday re-pointée, exceptions préservées en membership, passé figé, recalcul stats).

---

## 3. Algorithme(s)

> Conventions : toutes les dates métier sont des **dates calendaires** (sans heure) dans le fuseau `Europe/Paris` ; l'**heure d'échange** (`handover_time`) ne sert qu'à calculer le libellé de transmission, pas l'attribution du jour. Le moteur raisonne en **jours pleins** : un `CustodyDay` représente la nuit/journée attribuée à un parent. La transition de garde se fait le soir du `handover_weekday` à `handover_time` ; par convention **figée ci-dessous** et testée, **le jour d'échange est attribué au parent qui prend la garde le soir** (cf. note suivante et edge cases §4).

> **Convention d'attribution du jour d'échange (transmission le soir) — décision figée et zone de risque assumée.** Le cdc §2.4 précise uniquement « La transmission se fait le soir du jour d'échange ». De ce libellé, on **fige** la sémantique suivante : un `CustodyDay` modélise la **nuit** (la journée + la nuit qui suit). Le jour d'échange `D`, l'enfant passe la **soirée et la nuit** chez le parent **entrant** ; donc **`CustodyDay(D)` est attribué au parent qui prend la garde le soir de `D`** (le parent entrant), et le parent sortant a gardé l'enfant jusqu'à `handover_time` ce même jour `D`. Conséquence : le libellé de transmission « Retour chez {parent entrant} le {D} à {handover_time} » et l'attribution `CustodyDay(D)` désignent **le même** parent entrant — cohérence garantie.
>
> **Sémantique inverse possible (transmission le matin)** : certains foyers pratiquent l'échange **le matin** (ex. dépose à l'école), auquel cas l'utilisateur attendrait que `CustodyDay(D)` revienne au parent **sortant** (qui a eu la nuit précédente). Le présent lot **ne** modélise **pas** cette variante : `handover_time` est traité comme une heure de **soirée** au sens de l'attribution (la convention ci-dessus est appliquée quelle que soit la valeur saisie). Une `handover_time` matinale (ex. `08:00`) reste **acceptée** et n'affecte **que** le texte affiché, **jamais** l'attribution du jour (pas de bascule de `CustodyDay(D)` vers le parent sortant). Cette divergence par rapport à une attente « matin » est **assumée pour la v1**, documentée ici, et à **revérifier contre les maquettes Claude Design avant codage** (les maquettes font foi en cas d'ambiguïté de rendu, cdc §0) : si une maquette imposait l'attribution « matin », il faudrait introduire un paramètre `handover_moment ∈ {morning, evening}` — **non prévu** par le modèle de données actuel et donc hors périmètre tant que les maquettes ne le réclament pas.

### 3.1 Vue d'ensemble du pipeline de génération

```
generateHorizon(family):
  1. charger CustodyPattern (sinon: rien à générer)
  2. déterminer fenêtre [from, to]:
       from = max(anchor_date, aujourd'hui_floor)   # ne jamais réécrire le passé
       to   = aujourd'hui + 18 mois
  3. dans UNE transaction, avec verrou advisory par family_id:
     a. BASE      : pour chaque jour de [from, to], calculer parent_base (3.2)
                    → upsert CustodyDay(source=base) SI le jour n'est pas déjà
                      figé par holiday ou exception
     b. VACANCES  : pour chaque SchoolHoliday ayant une HolidayRule et
                    chevauchant [from, to], appliquer la règle (3.3)
                    → upsert CustodyDay(source=holiday) en ÉCRASANT la base,
                      MAIS jamais un jour source=exception
     c. EXCEPTIONS: pour chaque CustodyException chevauchant [from, to],
                    → upsert CustodyDay(source=exception) en ÉCRASANT tout
  4. recalculer les stats sur demande (pas stockées) — voir 3.6
  5. publier sur /families/{id}/custody + AuditLog
```

**Ordre de priorité (invariant verrouillé)** : `exception > vacances > base`. L'implémentation l'obtient par **ordre d'application** (base, puis vacances, puis exception) **et** par garde explicite (ne jamais downgrader la `source` d'un jour : un upsert base/holiday ne touche pas un jour de priorité supérieure déjà posé). Cette double garantie rend l'ordre des passes robuste.

### 3.2 Génération de la base par modèle

Soit `D` un jour, `n = floor( joursEntre(anchor_date, D) )` (≥ 0 ; pour `D < anchor_date`, non généré). `A` = parent A, `B` = parent B.

```
parentBase(kind, D, anchor_date, handover_weekday, custom_pattern):

  case alternating:                      # 1 semaine sur 2 (7 nuits/parent)
      # le cycle bascule chaque semaine, ancré sur le jour d'échange
      semaine = floor( joursEntre(débutCycle(anchor_date, handover_weekday), D) / 7 )
      return (semaine pair) ? A : B

  case pattern_2_2_3:                    # cycle de 14 jours: A A B B A A A | B B A A B B B
      # motif canonique sur 2 semaines (2/2/3 en rotation), indexé sur n % 14
      MOTIF_223 = [A,A,B,B,A,A,A, B,B,A,A,B,B,B]   # à figer + tester
      return MOTIF_223[ n % 14 ]

  case midweek_weekends:                 # jours fixes en semaine + week-ends alternés
      # config lue dans CustodyPattern.settings (jsonb dédié, schéma figé §5.1) :
      #   settings.weekday_parents = { "1":"A","2":"A","3":"B","4":"B","5":"A" }
      #       (clé = jour de semaine 0..6 selon la convention figée §4.5 ;
      #        seuls les jours NON week-end sont listés ; chaque valeur ∈ {A,B})
      #   settings.weekend_days     = [0,6]            # jours considérés "week-end"
      #   settings.weekend_start_parent = "A"          # parent du 1er week-end du cycle
      jour = jourDeSemaine(D)
      si jour ∈ settings.weekend_days:                 # week-ends alternés
          semaine = floor(joursEntre(débutCycle(anchor_date, handover_weekday), D)/7)
          base = (settings.weekend_start_parent == "A") ? A : B
          return (semaine pair) ? base : autre(base)
      sinon:                                            # jours de semaine fixes
          return (settings.weekday_parents[jour] == "A") ? A : B

  case custom:                           # 14 créneaux nuit par nuit
      return custom_pattern[ n % 14 ]    # custom_pattern[i] ∈ {A,B}, i=0..13
```

Notes :
- `débutCycle(anchor_date, handover_weekday)` = le dernier `handover_weekday` ≤ `anchor_date`, pour aligner les bascules sur le jour d'échange.
- Le **motif exact** de `pattern_2_2_3` est figé dans le code (constante) et **couvert par des tests** : c'est un point de vérité à ne pas laisser implicite.
- **`midweek_weekends` — où réside la configuration (lacune levée).** Le modèle de données ne prévoyait aucun champ pour stocker « quels jours de semaine vont à quel parent » : `custom_pattern` est **réservé** au seul `kind=custom` (14 créneaux) et ne convient pas. On introduit donc un champ **dédié `CustodyPattern.settings` (jsonb)**, **requis ssi `kind=midweek_weekends`** (cf. §5.1), dont le **schéma est figé** :
  ```json
  {
    "weekday_parents":      { "1":"A", "2":"A", "3":"B", "4":"B", "5":"A" },
    "weekend_days":         [0, 6],
    "weekend_start_parent": "A"
  }
  ```
  - `weekday_parents` : objet clé→valeur, **clé** = jour de semaine (string `"0".."6"`, convention figée §4.5), **valeur** ∈ `{"A","B"}` ; doit couvrir **exactement** les jours **non** présents dans `weekend_days` (validation : aucun jour manquant, aucun jour en double, aucun jour appartenant aussi à `weekend_days`).
  - `weekend_days` : tableau de 1 à 3 jours distincts (0..6) traités comme « week-end » et **alternés** ; non vide.
  - `weekend_start_parent` ∈ `{"A","B"}` : parent du **premier** week-end du cycle (ancrage déterministe sur `débutCycle`).
  - **Validation & erreurs** : un `settings` absent/incomplet/incohérent pour `kind=midweek_weekends`, ou un `settings` non vide pour un autre `kind`, déclenche `422` (cf. §4.5) ; **aucune** génération. Le schéma est **couvert par des tests** unitaires dédiés (mapping jour→parent, alternance week-ends, ancrage) au même titre que `pattern_2_2_3` et `custom`.
- Complexité base : **O(nombre de jours de la fenêtre)** = O(~550) par génération complète ; régénération incrémentale : O(jours manquants).

### 3.3 Application des règles de vacances (`HolidayRule`) par période

Pour chaque `SchoolHoliday H` (avec règle) chevauchant la fenêtre, soit `P = [H.start_date, H.end_date]` :

```
applyHolidayRule(H, rule):
  case fixed_parent:                # toute la période chez un parent
      pour D dans P: setDay(D, rule.params.parent, source=holiday)

  case split_50_50:                 # moitié/moitié (coupure au milieu)
      milieu = P.start + floor(durée(P)/2)
      pour D dans [P.start, milieu):  setDay(D, rule.params.firstHalf, holiday)
      pour D dans [milieu, P.end]:    setDay(D, rule.params.secondHalf, holiday)

  case alternating_weeks:           # semaines alternées sur la période
      pour D dans P:
          k = floor(joursEntre(P.start, D)/7)
          setDay(D, (k pair) ? rule.params.startParent : autre, holiday)

  case eve_alternating:             # réveillons alternés (24/25, 31/1)
      # rule.params.eves       = liste de réveillons, chacun {mois, jour} (ex. {12,24},{12,31})
      # rule.params.evenParent = "A"|"B" : parent du réveillon les années CIVILES paires
      #                          (l'autre parent les années impaires) — ancrage déterministe
      # rule.params.baseRule   = règle appliquée aux jours NON-réveillon de la période
      #                          (ex. {"kind":"split_50_50", ...}) ; défaut documenté
      pour D dans P:
          si estReveillon(D, rule.params.eves):
              # alternance ancrée sur l'année CIVILE du jour D (pas l'année scolaire) :
              parRev = (année(D) pair) ? rule.params.evenParent : autre(rule.params.evenParent)
              setDay(D, parRev, holiday)
          sinon:
              setDay(D, appliquer(rule.params.baseRule, D, P), holiday)

  case custom:                      # mapping libre jour->parent fourni en params
      pour D dans P:
          si D ∈ rule.params.assignments: setDay(D, params.assignments[D], holiday)
```

`setDay(D, parent, source=holiday)` n'écrit **que** si le jour n'est pas déjà `source=exception`.

Notes (règles de vacances) :
- **`eve_alternating` — déterminisme de l'alternance annuelle (figé et testé).** L'ancrage de l'alternance « quel parent a le réveillon » est l'**année civile** du jour de réveillon `D` (et non l'année scolaire, qui chevauche deux années civiles et serait ambiguë pour le 24/31 décembre vs le 1ᵉʳ janvier) : `evenParent` détient les réveillons des **années paires**, l'autre parent ceux des **années impaires**. Ainsi le 24/12/2026 et le 24/12/2028 reviennent au même parent, le 24/12/2027 à l'autre — déterministe, indépendant de la fenêtre de génération et **stable d'une régénération à l'autre**. Les **dates de réveillon** proviennent de `params.eves` (paires `{mois, jour}`, par défaut `{12,24}` et `{12,31}` — la valeur par défaut est documentée et non codée en dur côté UI). Les jours non-réveillon de la période suivent `params.baseRule`. Ce déterminisme est **couvert par des tests** (années paire/impaire, bascule 31/12 → 1ᵉʳ/01, valeur par défaut de `baseRule`).
- **Chevauchement de deux `SchoolHoliday` officielles sur les mêmes jours (ordre d'application figé).** Une même date peut, par erreur de source ou par recouvrement de périodes (ex. zones limitrophes, libellés voisins, correctif amont), être couverte par **deux** `SchoolHoliday` portant chacune une `HolidayRule`. L'ordre d'application entre deux passes `holiday` doit être **déterministe** pour éviter un résultat dépendant de l'itération. Règle figée : on applique les périodes `holiday` triées par **(`start_date` croissante, puis `end_date` croissante, puis `id` croissant)** ; **la dernière écrite gagne** sur un jour partagé (la période la plus « récente/spécifique » dans cet ordre l'emporte). Comme `setDay(..., holiday)` ne fait qu'écraser une autre source `holiday` (jamais une `exception`), le résultat reste stable et reproductible quel que soit l'ordre de chargement initial. Ce cas est **journalisé en avertissement** (chevauchement détecté entre deux périodes officielles) et **couvert par un test** ; il ne bloque jamais la génération.

### 3.4 Application des exceptions (priorité maximale)

```
applyException(E):   # E = CustodyException(start_date,end_date,parent_membership_id)
  pour D dans [E.start_date, E.end_date]:
      upsert CustodyDay(D, parent=E.parent_membership_id, source=exception)
```

L'exception **écrase systématiquement** base et vacances. La suppression d'une exception déclenche un **recalcul ciblé** de sa plage : pour chaque jour de la plage, on réévalue dans l'ordre vacances→base (puisqu'il peut exister une règle vacances couvrant le jour) et on repositionne la bonne `source`.

### 3.5 Idempotence, transactions, concurrence

- **Idempotence structurelle** : contrainte d'unicité `(family_id, date)` sur `CustodyDay` ; toutes les écritures passent par un **upsert** (`INSERT … ON CONFLICT (family_id,date) DO UPDATE`). Relancer une génération identique laisse la base inchangée.
- **Préservation** : la régénération de la base **n'écrase jamais** un jour `source ∈ {holiday, exception}` ; un import/rafraîchissement vacances **n'écrase jamais** `source=exception` ni les périodes `source=manual`/leurs règles ; modifier le pattern ne touche **jamais** le passé (`date < aujourd'hui`).
- **Transaction** : une régénération complète d'une famille s'exécute dans **une** transaction Doctrine ; en cas d'échec, rollback total (pas d'état partiel).
- **Verrou** : verrou applicatif par `family_id` (advisory lock PostgreSQL ou clé Messenger) pour sérialiser les régénérations concurrentes (US-CUST-7 scénario concurrent).
- **Asynchronie** : la génération suite à un `PUT pattern`, un import vacances, ou une règle est dispatchée via **Messenger** (message `RegenerateCustodyMessage{familyId, fromDate?}`), avec dédoublonnage si plusieurs déclencheurs s'enchaînent.

### 3.6 Recalcul des statistiques

```
stats(family, from, to):
  jours = SELECT date, parent_membership_id FROM custody_day
          WHERE family_id=? AND date BETWEEN from AND to
  countA = nombre de jours où parent = parent_a
  countB = nombre de jours où parent = parent_b
  return { parentA: countA, parentB: countB, total: countA+countB,
           pctA: countA/total, pctB: countB/total }
```

Les stats sont **calculées à la volée** sur les `CustodyDay` **effectifs** (donc reflétant exceptions et vacances), **non stockées**, pour rester cohérentes après toute mutation. Complexité O(jours de la fenêtre).

### 3.7 Logique « Échanger ce jour »

```
echangerCeJour(family, D, membreActeur):
  vérifier custody=edit (Voter) et scope famille
  jourActuel = CustodyDay(family, D)
  parentCible = (jourActuel.parent == parent_a) ? parent_b : parent_a
  créer CustodyException(start=D, end=D, parent=parentCible, created_by=membreActeur)
  appliquer (source=exception) ; recalcul stats ; publier temps réel ; AuditLog
```

C'est un cas particulier de US-CUST-4 (exception d'un jour) : pas de logique parallèle, on réutilise le même chemin de création d'exception.

### 3.8 Transmission « Retour chez X le … 19h »

```
transmission(D):
  # trouver le prochain jour de bascule de garde à partir de D
  parentCourant = CustodyDay(D).parent
  E = plus petit jour > D tel que CustodyDay(E).parent != parentCourant
  prochainParent = CustodyDay(E).parent
  libellé = ICU("Retour chez {parent} le {date} à {heure}",
                parent=nom(prochainParent), date=E, heure=handover_time)
  # 'date' et 'heure' formatées en Europe/Paris, locale active
```

La bascule détectée tient compte des exceptions/vacances (puisque calculée sur `CustodyDay` effectifs), pas seulement du rythme de base.

### 3.9 Horizon glissant via Scheduler

- Une tâche planifiée (`ExtendCustodyHorizonHandler`) parcourt les familles séparées avec pattern et dispatche `RegenerateCustodyMessage` pour étendre jusqu'à `aujourd'hui + 18 mois`.
- Le **rafraîchissement annuel des vacances** (`RefreshSchoolHolidaysHandler`) déclenche `SchoolHolidaysProvider` pour la nouvelle année scolaire, puis une régénération des périodes concernées.
- Les deux tâches sont **idempotentes** et journalisées.

### 3.10 Adaptateur `SchoolHolidaysProvider` (isolation de la source officielle)

> Schéma **vérifié** contre l'API en vigueur (Opendatasoft Explore v2.1, dataset `fr-en-calendrier-scolaire`). Ne pas coder en dur une structure supposée (invariant cdc §4.7).

- **Endpoint** : `GET https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records`
- **Paramètres** : `where=annee_scolaire="{YYYY-YYYY}"`, `refine=zones:"Zone {A|B|C}"`, filtrage par académie via `location`, `limit`/`offset` pour la pagination.
- **Champs réels d'un enregistrement** : `description` (libellé, ex. « Vacances de la Toussaint »), `population` (valeurs observées : `-`, `Élèves`, `Enseignants` — **filtrer pour ne garder que la population pertinente aux élèves**, ignorer `Enseignants`), `start_date`, `end_date` (**ISO 8601 en UTC `+00:00`**), `location` (académie, ex. « Bordeaux »), `zones` (ex. « Zone A »), `annee_scolaire` (ex. « 2025-2026 »).
- **Conversion de fuseau (critique)** : les dates sont fournies en **UTC**. Une vacance commençant `2025-10-17T22:00:00+00:00` correspond à minuit heure de Paris (début de journée locale). L'adaptateur **convertit en `Europe/Paris`** puis dérive la **date calendaire** de début/fin pour `SchoolHoliday.start_date`/`end_date`. Ne jamais stocker le brut UTC comme date métier.
- **Mapping** : `description→label`, `start/end→start_date/end_date (dates Europe/Paris)`, `zones→zone (A|B|C)`, `location→academy`, `annee_scolaire→school_year`, `source=official`.
- **Idempotence d'import** : upsert sur clé naturelle `(family_id, zone, academy, school_year, label, start_date)`.
- **Cache** : réponses mises en cache (TTL raisonnable) ; un échec réseau retombe sur le cache si disponible.
- **Résilience** : timeouts, retries avec backoff (Messenger), aucune écriture partielle.
- **Testabilité** : l'adaptateur implémente une interface ; en test, un **double** renvoie des fixtures (pas d'appel réseau en CI).

---

## 4. Cas limites (edge cases)

### 4.1 Dates, fuseau, changements d'heure, bissextiles

- **Fuseau `Europe/Paris`** : toutes les dates métier sont calculées et affichées en `Europe/Paris` ; la date de référence (23 juin 2026) n'est **jamais** codée en dur — on utilise l'horloge système (injectable `ClockInterface` pour les tests).
- **Passage heure d'été/hiver** : le `handover_time` (ex. 19h00) reste une heure **locale** ; le jour où l'horloge change (dernier dimanche de mars/octobre), l'attribution du jour ne dépend pas de la durée réelle (23 h ou 25 h) — on raisonne en **jours calendaires**, donc aucun décalage d'attribution.
- **Source UTC des vacances** : voir §3.10 — conversion UTC→Paris avant dérivation de la date calendaire, sinon une vacance se décalerait d'un jour.
- **Année bissextile** : le 29 février est un jour comme un autre ; `joursEntre` utilise une arithmétique de dates correcte (pas d'hypothèse 365 jours).
- **Borne d'horizon** : `to = aujourd'hui + 18 mois` calculé en arithmétique calendaire (gère fins de mois, ex. 31 → 30/28).
- **Jour d'échange = jour d'ancrage** : si `anchor_date` tombe sur `handover_weekday`, la convention d'attribution du jour d'échange (§3) s'applique de façon déterministe et testée.
- **`handover_time` matinale vs attribution (zone de risque assumée)** : une `handover_time` saisie le matin (ex. `08:00`) **n'inverse pas** l'attribution du jour d'échange ; par convention figée (§3), `CustodyDay(D)` reste attribué au **parent entrant le soir**, et `handover_time` n'influe **que** sur le libellé de transmission. Si l'utilisateur attend la sémantique inverse (transmission le matin ⇒ jour au parent sortant), c'est **hors périmètre v1** (pas de champ `handover_moment` au modèle) ; à **revérifier contre les maquettes** avant codage. Comportement testé : varier `handover_time` (matin/soir) ne change **jamais** `CustodyDay(D).parent`.
- **Chevauchement de deux périodes officielles sur les mêmes jours** : ordre d'application figé (tri `start_date`,`end_date`,`id` ; dernière écrite gagne) ⇒ résultat déterministe et reproductible ; un `exception` reste toujours prioritaire ; chevauchement journalisé en avertissement (cf. §3.3 Notes). Couvert par un test.
- **Plage exception/vacances à cheval sur deux mois/années** : la génération et les stats opèrent sur la fenêtre demandée, indépendamment des frontières de mois/année.
- **Période vacances dépassant l'horizon** : seule la portion ∩ horizon est matérialisée ; le reste sera couvert à l'extension d'horizon.

### 4.2 Concurrence & idempotence

- **Régénérations concurrentes** (Scheduler + PUT pattern simultanés) : sérialisées par verrou par famille ; upsert `(family_id,date)` empêche les doublons même sans verrou.
- **Double import vacances** : upsert sur clé naturelle ; pas de doublon ; règles existantes préservées.
- **Rejouer le même message Messenger** : handlers idempotents (génération et import).
- **Exception créée puis pattern modifié** : le pattern ne réécrit pas le jour `source=exception` ; l'exception survit.
- **Suppression d'exception pendant une régénération** : la transaction et le verrou garantissent un état cohérent ; après suppression, recalcul ciblé de la plage.

### 4.3 Autorisations (Voters) & rôles

- **`parent`** : `custody=edit` forcé (non éditable) — accès complet.
- **`step_parent`** : selon le droit `custody` (`none|view|edit`) défini par un parent/admin ; en `view` aucune mutation.
- **`child`** : au plus `custody=view` ; **jamais** `edit` (invariant : pas d'édition de structure) ; toute mutation → `403`.
- **`admin`** : nécessaire pour la partie réglages structurels via `family_settings`, mais l'édition de garde reste gouvernée par `custody`. Le **dernier admin** ne peut être retiré/rétrogradé (invariant) — concerne le Lot 1 mais l'écran Paramètres › Garde ne doit pas offrir d'action contournant cette règle.
- **Voter sur chaque ressource** : l'autorisation est vérifiée sur (Membre, ressource) à **chaque** appel, jamais déduite globalement.

### 4.4 Scope famille (`X-Family-Id`)

- **Header absent** sur une route scopée garde → `400/403` selon convention, jamais d'exécution non scopée.
- **`X-Family-Id` ≠ famille de la ressource** (ex. `PUT /api/holidays/{id}/rule` où la période appartient à une autre famille) → `403`/`404`, validé avant le Voter custody.
- **Membre de plusieurs familles** : la garde affichée correspond strictement à la famille active ; aucun mélange de `CustodyDay` entre familles (unicité `(family_id,date)` ne collisionne jamais entre familles).
- **Aucune requête métier de garde non scopée** (invariant verrouillé).

### 4.5 Valeurs vides / limites

- **Pas de `CustodyPattern`** : écran Garde en état vide ; stats = 0/0 ; aucune génération.
- **`parent_a`/`parent_b` non définis** : `PUT pattern` exige les deux ; sinon `422`.
- **`customPattern` invalide** : longueur ≠ 14 ou valeur ∉ {A,B} → `422`.
- **`settings` (midweek_weekends) invalide** : absent/incomplet/incohérent (jour de semaine non couvert ou en double, jour à la fois `weekend_days` et `weekday_parents`, valeur ∉ {A,B}, `weekend_days` vide, `weekend_start_parent` ∉ {A,B}) → `422` ; `settings` non vide pour un `kind` ≠ `midweek_weekends` → `422`. Aucune génération.
- **Réassignation `parent_a`/`parent_b` sur pattern existant** : régénère base/holiday **futurs** (re-pointés sur les nouveaux memberships), **conserve** les exceptions (membership ciblé inchangé), **fige** le passé, recalcule les stats — convention figée US-CUST-9. Pas de doublon (upsert).
- **`handover_time` aux bornes** : `00:00` et `23:59` valides ; format `HH:MM` strict ; valeurs hors plage → `422`.
- **`handover_weekday`** : entier 0–6, **convention figée : `0`=dimanche, `1`=lundi, …, `6`=samedi** (cohérent avec US-CUST-1 où `handoverWeekday=0` ⇒ dimanche). **C'est la convention de référence dans tout le lot** (notamment les clés de `settings.weekday_parents` et `settings.weekend_days` de `midweek_weekends`, et `jourDeSemaine(D)`). Documentée et testée ; hors plage → `422`. Les libellés de jours sont rendus via la locale (jamais codés en dur).
- **Exception d'un seul jour** : `start=end` valide.
- **Période vacances vide ou bornes inversées** (import retournant `end < start`) : enregistrement **ignoré/rejeté** par l'adaptateur, journalisé.
- **Fenêtre stats `from > to`** : `422` ; `from == to` : 1 jour.
- **Membre parent supprimé/anonymisé (RGPD)** alors qu'il est `parent_a/b` : le pattern reste valide mais l'affichage utilise un libellé neutre (« Parent supprimé ») ; aucune corruption de `CustodyDay` (l'anonymisation préserve l'intégrité — Lot 8).

### 4.6 Échecs externes

- **API vacances indisponible/timeout/quota** : retries backoff ; après échec, opération marquée échouée, message localisé, **aucune** donnée partielle ; cache conservé.
- **Réponse API au schéma inattendu** (champ manquant/renommé) : l'adaptateur **valide** la forme et rejette proprement (log + erreur), ne crée pas de `SchoolHoliday` corrompue — cohérent avec l'exigence de vérification du schéma réel.
- **Mercure hors-ligne** : la mutation de garde **réussit** quand même (persistance + audit) ; seule la diffusion temps réel est différée/perdue ; les clients récupèrent l'état au prochain `GET`.
- **Messenger/worker arrêté** : `PUT pattern` renvoie tout de même un succès (la génération est asynchrone) ; un état « génération en cours » peut être affiché ; à la reprise du worker, la génération s'effectue (idempotente).
- **E-mail** : aucun envoi dans ce lot (Lot 7) ; un échec e-mail futur ne doit jamais bloquer une mutation de garde.

### 4.7 Conflits offline (mobile)

- **Exception créée hors-ligne** puis rejouée : `Idempotency-Key` empêche le doublon ; si le serveur a entretemps un état incompatible, l'autorité serveur tranche (§4.4 cdc) et renvoie l'état canonique ; l'utilisateur est informé en cas de refus.
- **Deux exceptions offline conflictuelles sur le même jour** : last-write-wins n'est **pas** suffisant pour la garde ; le serveur valide et peut **refuser** la seconde, signalé à l'utilisateur (cohérent avec §4.4 cdc « refus possible si conflit »).
- **Lecture offline** : le calendrier consulté récemment reste lisible (cache Drift) ; toute mutation hors-ligne est mise en outbox et signalée « en attente de synchro ».

### 4.8 Sécurité / RGPD

- **Aucune donnée de santé** créée par ce lot (les périodes/règles/jours ne portent que des dates et des références membres) — invariant respecté.
- **`reason` d'exception** : champ de texte libre court ; **ne pas** y inciter à des données de santé ; pas de chiffrement requis a priori (ce n'est pas un champ 🔒 listé), mais **toute** donnée personnelle nouvelle devrait être évaluée pour le registre (voir §7). Le `reason` reste minimal et non sensible.
- **AuditLog** : tracer les mutations de garde (`custody.pattern.updated`, `custody.exception.created/deleted`, `custody.holiday.imported`, `custody.rule.updated`) — append-only.
- **Pas de fuite hors famille** : aucune donnée de garde n'est exposée à une famille liée (l'overlay du Lot 5 ne touche que l'agenda en RO, jamais la garde).

### 4.9 i18n / localisation

- **Toutes** les chaînes (labels modèles, genres de règles, libellé de transmission, états vides, erreurs) externalisées en **ICU**, locale `fr-FR` livrée, ajout d'une langue sans changement de code.
- **Libellés de vacances** (`description`) viennent de la source officielle en français ; l'affichage les présente tels quels (pas de traduction automatique), mais l'UI autour reste localisée.
- **Formats date/heure/jour de semaine** localisés (`Europe/Paris`, locale active) ; pluriels ICU pour les stats (« {n, plural, one {# jour} other {# jours}} »).
- **Noms de jours d'échange** présentés via la locale, pas codés en dur.

---

## 5. Modèle de données touché

> Base : §5.2 cdc. Tous : `id` (UUID), `created_at`, `updated_at`. Aucun champ 🔒 chiffré n'est **introduit** par ce lot (les champs garde ne sont pas dans la liste 🔒 : `full_name`, `phone`, `Message.text`, `Note.text`, `Invitation.email`).

### 5.1 Entités créées par ce lot

- **CustodyPattern** (1-1 avec Family)
  - `family_id` (FK, **unique** → un seul pattern par famille)
  - `kind` (`alternating`|`pattern_2_2_3`|`midweek_weekends`|`custom`)
  - `anchor_date` (date)
  - `handover_weekday` (smallint 0–6)
  - `handover_time` (time)
  - `custom_pattern` (jsonb, **14 entrées** A/B ; requis ssi `kind=custom`, sinon null/vide)
  - `settings` (jsonb, **dédié à `kind=midweek_weekends`** ; requis ssi `kind=midweek_weekends`, sinon null/vide). Schéma figé (cf. §3.2) : `{ "weekday_parents": {"<0..6>":"A|B", …}, "weekend_days": [<0..6>, …], "weekend_start_parent": "A|B" }`. Sépare la config `midweek_weekends` de `custom_pattern` (réservé à `kind=custom`), ce qui lève la lacune « où réside le paramétrage de `midweek_weekends` ».
  - `parent_a_membership_id` (FK Membership, rôle `parent`, même famille)
  - `parent_b_membership_id` (FK Membership, rôle `parent`, même famille ; **≠** parent A)
  - Contraintes (validées, → `422` sinon) :
    - `kind=custom` ⇔ `custom_pattern` valide (14×{A,B}) **et** `settings` vide.
    - `kind=midweek_weekends` ⇔ `settings` valide (schéma §3.2 : couverture exacte jours semaine/week-end, valeurs ∈ {A,B}) **et** `custom_pattern` vide.
    - `kind ∈ {alternating, pattern_2_2_3}` ⇒ `custom_pattern` **et** `settings` vides.

- **CustodyDay** (matérialisé par le moteur)
  - `family_id` (FK)
  - `date` (date)
  - `parent_membership_id` (FK Membership)
  - `source` (`base`|`holiday`|`exception`)
  - **Unicité `(family_id, date)`** (invariant d'idempotence).
  - Index sur `(family_id, date)` pour les requêtes `from..to`.

- **CustodyException**
  - `family_id` (FK)
  - `start_date` (date), `end_date` (date, ≥ start_date)
  - `parent_membership_id` (FK Membership)
  - `reason` (texte court, libre, non sensible)
  - `created_by` (FK Membership)

- **SchoolHoliday**
  - `family_id` (FK)
  - `label` (string ; vient de `description` pour les officielles)
  - `start_date` (date, **Europe/Paris**), `end_date` (date)
  - `zone` (`A`|`B`|`C`)
  - `academy` (string ; vient de `location`)
  - `school_year` (string, ex. `2025-2026`)
  - `source` (`official`|`manual`)
  - Unicité naturelle (upsert) : `(family_id, zone, academy, school_year, label, start_date)`.

- **HolidayRule** (1-1 avec SchoolHoliday)
  - `school_holiday_id` (FK, **unique**)
  - `kind` (`alternating_weeks`|`split_50_50`|`fixed_parent`|`eve_alternating`|`custom`)
  - `params` (jsonb, schéma dépendant du `kind` — validé, `422` sinon) :
    - `fixed_parent` : `{ "parent":"A|B" }`
    - `split_50_50` : `{ "firstHalf":"A|B", "secondHalf":"A|B" }`
    - `alternating_weeks` : `{ "startParent":"A|B" }`
    - `eve_alternating` : `{ "eves":[{"month":12,"day":24},{"month":12,"day":31}], "evenParent":"A|B", "baseRule":{ "kind":"split_50_50", … } }` — `evenParent` = parent du réveillon les **années civiles paires** (ancrage déterministe, cf. §3.3) ; `eves` par défaut `{12,24}`+`{12,31}` ; `baseRule` régit les jours non-réveillon de la période.
    - `custom` : `{ "assignments": { "<YYYY-MM-DD>":"A|B", … } }`

### 5.2 Entités modifiées / référencées

- **Family** (Lot 1) : `type` (`separated|intact`) conditionne l'existence de la garde ; `timezone`, `school_zone`, `academy` consommés par l'import. Aucun nouveau champ requis par ce lot (sauf si l'on choisit de stocker un curseur d'horizon — optionnel).
- **Membership** (Lot 1) : référencé par `parent_a/b`, `parent_membership_id`, `created_by` ; `display_color` réutilisé pour colorer parent A/B.

### 5.3 Relations & cascades

- `Family 1-1 CustodyPattern` ; `Family 1-N CustodyDay/CustodyException/SchoolHoliday`.
- `SchoolHoliday 1-1 HolidayRule` (suppression période → suppression règle en cascade).
- Suppression d'une exception → recalcul ciblé (pas de cascade destructrice sur les `CustodyDay`, qui sont repositionnés).
- Les FK vers Membership respectent le scope famille (membership de la **même** famille).

---

## 6. Endpoints API concernés

> Conventions §6.1 : REST/JSON sous `/api` ; `Authorization: Bearer <jwt>` ; **`X-Family-Id`** sur toute route scopée famille (validé contre les memberships) ; erreurs **RFC 7807** (`application/problem+json`) localisées ; **`Idempotency-Key`** accepté sur les POST issus de l'outbox offline ; OpenAPI servi par le backend.

| Méthode | Chemin | Droit requis | Description |
|---|---|---|---|
| GET | `/api/families/{id}/custody/pattern` | `custody:view` | Lire le modèle de rythme courant |
| PUT | `/api/families/{id}/custody/pattern` | `custody:edit` | Créer/modifier le modèle ; **régénère** le futur (async) sans écraser exceptions/règles |
| GET | `/api/families/{id}/custody/days?from=&to=` | `custody:view` | Jours **effectifs** (base/vacances/exception) sur la fenêtre |
| GET | `/api/families/{id}/custody/exceptions` | `custody:view` | Lister les exceptions |
| POST | `/api/families/{id}/custody/exceptions` | `custody:edit` | Créer une exception (jour ou plage) ; supporte `Idempotency-Key` |
| DELETE | `/api/families/{id}/custody/exceptions/{exceptionId}` | `custody:edit` | Supprimer une exception ; recalcul ciblé |
| GET | `/api/families/{id}/custody/stats?from=&to=` | `custody:view` | Jours par parent sur la fenêtre (calcul à la volée) |
| GET | `/api/families/{id}/holidays` | `custody:view` | Lister les périodes (officielles + manuelles) |
| POST | `/api/families/{id}/holidays` | `custody:edit` | Créer une période manuelle |
| POST | `/api/families/{id}/holidays/import` | `custody:edit` | Importer depuis la source officielle (**async**, `202 Accepted`) |
| PUT | `/api/holidays/{id}/rule` | `custody:edit` | Définir/mettre à jour la règle d'une période ; régénère la période |

Notes :
- Le `PUT pattern` répond rapidement (la génération est dispatchée via Messenger) ; l'état « génération en cours » peut être exposé. Le `POST holidays/import` répond `202` et notifie la fin via Mercure.
- Les écritures publient sur **`/families/{id}/custody`** (Mercure) : modèle modifié, exception/échange, règle vacances (cf. §4.5 cdc). Web ⇒ Turbo Streams ; mobile ⇒ SSE JSON.
- Les routes de **garde** ne sont exposées (et l'onglet présent) que pour les familles `type=separated`. Pour `intact` (convention figée, cf. US-CUST-1) : les **lectures** renvoient **`404`** (masquage fonctionnel), les **mutations** renvoient **`409`** (`/errors/custody-not-applicable`) ; l'onglet est absent côté UI.

---

## 7. Invariants & règles métier

Invariants du cdc / `CLAUDE.md` **pertinents pour ce lot** :

1. **Scope famille obligatoire** : toute ressource de garde est scopée via `X-Family-Id`, validé contre les memberships ; **jamais** de requête de garde non scopée.
2. **Autorisation par Voters** : Voter `custody` (`none|view|edit`) sur (Membre, ressource), vérifié sur **chaque** ressource. `parent` ⇒ `edit` forcé ; `child` ⇒ jamais `edit` (au plus `view`), jamais d'édition de structure ; `step_parent` selon le droit configuré.
3. **Dernier admin protégé** : aucune action de l'écran Paramètres › Garde ne doit permettre de contourner cette protection (règle portée par le Lot 1, respectée ici).
4. **Priorité de la garde** : **exception > règle de vacances > base**. Modifier le `CustodyPattern` régénère les `CustodyDay` **futurs sans écraser** exceptions ni règles vacances. Recalculer les stats à partir des **jours effectifs**.
5. **Garde matérialisée en base** : un `CustodyDay` par jour, moteur idempotent, horizon glissant ~18 mois — **pas** de calcul à la volée des jours.
6. **Génération idempotente** : unicité `(family_id, date)` + upsert ; relancer ne crée pas de doublon, ne réécrit pas le passé, ne downgrade pas une `source` prioritaire.
7. **L'overlay ne modifie jamais la garde** (la garde de la famille active n'est pas affectée par une famille liée — pertinent dès la conception, mis en œuvre au Lot 5).
8. **Pas de données de santé** : aucun champ typé santé ; ce lot n'en crée pas. (Le bandeau enfant est sur l'écran enfant, Lot 2.)
9. **Champs chiffrés au repos** : aucun nouveau champ 🔒 introduit ; les champs de garde ne sont pas sensibles. (Vérifier néanmoins que `reason` reste non sensible.)
10. **Registre des données personnelles** : si un champ personnel était ajouté (a priori **aucun** ici), ajouter une entrée de registre + `registre-donnees.md` (test CI de cohérence). À vérifier au passage de DoD : confirmer qu'aucun champ personnel n'est introduit, ou documenter le contraire.
11. **i18n** : toutes les chaînes UI (et e-mail futures) externalisées en ICU dès ce lot ; seule `fr-FR` livrée ; fuseau par défaut `Europe/Paris`.
12. **Dates non codées en dur** : la date de référence (23 juin 2026) n'est jamais en dur ; horloge injectable.
13. **`/infra/INSTALL.md` à jour** : si le lot ajoute une dépendance (ex. client HTTP pour l'API vacances, configuration du Scheduler), mettre à jour le guide bare-metal.
14. **Adaptateur isolé `SchoolHolidaysProvider`** : la source officielle est encapsulée derrière une interface, avec cache, schéma **vérifié** (pas codé en dur sur une supposition), conversion UTC→Europe/Paris, et testable via double.

---

## 8. Definition of Done (DoD)

Au-delà de la DoD générale (§9 cdc), critères **spécifiques** au Lot 3 :

### 8.1 Tests

- **Unitaires (PHPUnit)** : moteur de génération pour les 4 modèles (`alternating`, `pattern_2_2_3` avec son motif canonique figé, `midweek_weekends` **avec son schéma `settings` figé** — mapping jour→parent, alternance des week-ends, ancrage, validations d'incohérence, `custom` 14 créneaux) ; application des 5 genres de `HolidayRule` dont **`eve_alternating`** (déterminisme de l'alternance ancrée sur l'année civile : années paire/impaire, bascule 31/12 → 1ᵉʳ/01, `baseRule` par défaut) ; **chevauchement de deux périodes officielles** (ordre d'application figé, résultat déterministe, exception toujours prioritaire) ; application/suppression d'exceptions ; **ordre de priorité** exception>vacances>base ; **réassignation `parent_a`/`parent_b`** (re-mapping base/holiday futurs, préservation des exceptions par membership, passé figé) ; **`handover_time` matinale n'inverse pas l'attribution** ; **idempotence** (relance sans doublon ni écrasement) ; recalcul de stats ; calcul de transmission ; conversions de fuseau et changements d'heure été/hiver ; années bissextiles ; bornes d'horizon. `SchoolHolidaysProvider` testé via double (mapping des champs réels, conversion UTC→Paris, filtrage `population`, idempotence d'upsert, gestion d'échec/cache).
- **Fonctionnels / API** : auth + scope `X-Family-Id` + Voters `custody` sur chaque endpoint (200/403) ; `PUT pattern` régénère sans écraser exceptions/règles ; validations `422` (`handover_time`, `custom_pattern`, **`settings` midweek_weekends**, parents A/B invalides) ; import async (`202`) ; règle de période ; exceptions CRUD ; stats ; **famille `intact` → `404` en lecture et `409` (`/errors/custody-not-applicable`) en mutation** (un test par méthode) ; `Idempotency-Key` sur POST exception.
- **E2E web (Playwright)** : configurer un rythme (dont **custom 2 semaines**), visualiser le calendrier coloré + stats, ouvrir le détail d'un jour, « Échanger ce jour », poser/supprimer une exception, importer les vacances et affecter une règle ; vérifier la mise à jour **temps réel** (Turbo Stream) d'un second contexte ; responsive desktop/mobile.
- **Flutter** : tests de widgets des écrans Garde (calendrier, détail jour, périodes spéciales, builder de rythme) ; tests unitaires des mappers du client généré ; compatibilité outbox (exception offline rejouée avec `Idempotency-Key`).

### 8.2 Livrables techniques

- **Migrations Doctrine** fournies pour `CustodyPattern`, `CustodyDay` (+ unicité `(family_id,date)` et index), `CustodyException`, `SchoolHoliday` (+ unicité naturelle), `HolidayRule` (+ unicité `school_holiday_id`).
- **OpenAPI** à jour (tous les endpoints §6 exposés et typés) ; **client Flutter régénéré** depuis l'OpenAPI.
- **i18n (fr-FR)** câblée : toutes les chaînes du lot en ICU (modèles, genres de règles, transmission, états vides, erreurs RFC 7807) ; aucune chaîne en dur.
- **Scheduler** configuré : extension d'horizon glissant + rafraîchissement annuel des vacances (idempotents, journalisés) ; **Messenger** : message/handler de régénération et d'import.
- **Mercure** : publication sur `/families/{id}/custody` câblée (Turbo Streams web + SSE mobile).
- **`/infra/INSTALL.md`** mis à jour si une dépendance est ajoutée (client HTTP API vacances, config Scheduler/worker).
- **Registre des données personnelles** : confirmé qu'aucun champ personnel n'est introduit (ou entrée ajoutée + `registre-donnees.md` mis à jour si c'est le cas) ; test CI de cohérence vert.

### 8.3 Qualité & conformité

- **Analyse statique propre** : PHPStan niveau élevé, `php-cs-fixer`, `dart analyze`/`dart format` sans erreur.
- **AuditLog** branché sur les mutations de garde (pattern/exception/import/règle).
- **Conformité design system §8** : calendrier coloré parent A `--accent` (terracotta `#C2643D`), parent B `--positive` (teal `#2E7D71`), jours exceptionnels en **bordure pointillée**, périodes de vacances distinguées ; polices `Bricolage Grotesque`/`Hanken Grotesk` ; rayons/ombres conformes ; desktop sidebar 252px + grille 2 colonnes pour Garde ; mobile iOS « liquid glass » / Material adapté ; onglet Garde **masqué** pour familles unies. Écrans validés contre les maquettes (qui font foi).
- **Vérification de la source vacances** : preuve que le schéma/endpoint a été vérifié contre la source réelle (champs `description/population/start_date/end_date/location/zones/annee_scolaire`, dates UTC) et non supposé.
