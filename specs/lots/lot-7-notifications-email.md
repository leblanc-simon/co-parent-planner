# Lot 7 — Notifications e-mail

> Document de conception détaillée. Source de vérité : `specs/cdc.md` (notamment §2.9, §4.2, §4.6, §5.2, §6.2, §9 Lot 7) et `CLAUDE.md` (invariants verrouillés). Ce document ne contredit aucune décision d'architecture verrouillée (§0 du cdc). Toutes les dates sont relatives ; aucune date n'est codée en dur (la date des maquettes, le 23 juin 2026, ne sert que d'exemple d'affichage). Fuseau par défaut : `Europe/Paris`.

---

## 0. Objectif & périmètre

### 0.1 Objectif

Mettre en place le **sous-système de notifications e-mail** de CoParentPlanner : un pipeline asynchrone, idempotent et localisé qui transforme des **événements métier notifiables** (changement de garde, événement d'agenda, message/décision dans un échange, invitation) en **e-mails** envoyés aux **membres concernés**, dans le **respect strict des préférences utilisateur** (`NotificationPref` par catégorie + canal), des **quiet-hours**, de la **locale** de chaque destinataire et du **fuseau** de la famille.

Le lot ajoute également les **rappels d'échéance planifiés** (Scheduler) pour les échanges de type `decision` dont la `deadline` approche ou est dépassée, et expose l'écran/endpoint de **gestion des préférences de notification** (US-SET-1, déjà partiellement câblé en Lot 0/1, finalisé ici).

### 0.2 Dans le périmètre

- Entité **`NotificationPref`** (déjà déclarée §5.2) : modèle, migration, valeurs par défaut, endpoint `GET/PUT /api/me/notification-prefs` (canal `email` uniquement en v1).
- **Déclencheurs (event subscribers)** branchés sur les événements des lots 1, 3, 4, 6 :
  - **garde** (catégorie `custody`) : exception manuelle créée/supprimée, échange de jour, changement de modèle régénérant le futur, règle de vacances appliquée ;
  - **agenda** (catégorie `agenda`) : événement créé / modifié / supprimé, rappel d'événement à venir ;
  - **échanges** (catégorie `exchanges`) : nouveau message, nouvelle option validée / décision tranchée, ouverture/clôture d'un échange ;
  - **invitation** (catégorie `invitation`) : invitation envoyée (e-mail au destinataire, avec lien à usage unique, déjà produit en Lot 1 — ce lot **unifie** son envoi dans le pipeline de notifications et le localise).
- **Routage** événement → destinataires → filtrage préférences → quiet-hours → locale → rendu template → envoi.
- **Templates Twig localisés** (HTML + texte alternatif) par type de notification, externalisés en ICU (i18n).
- **Envoi asynchrone** via Symfony Messenger (transport Doctrine/Redis) avec **retry**, **dead-letter** et **idempotence**.
- **Rappels d'échéance planifiés** via le Scheduler (job récurrent), avec déduplication anti-spam.
- **Déduplication / anti-spam** : un même destinataire ne reçoit pas plusieurs fois la même notification pour le même fait ; regroupement éventuel des rafales.
- **Journalisation** des envois dans `AuditLog` (au moins pour les notifications sensibles : invitation, garde) et trace technique de l'état d'envoi.
- **Écran Paramètres › Compte › Notifications** (web Twig/UX + Flutter) : toggles par catégorie, réglage des quiet-hours, choix de la locale (relayé vers `User.locale`).

### 0.3 Hors périmètre

- **Push mobile (APNs/FCM)** : explicitement **v2 / Lot 11**. `NotificationPref.channel` réserve déjà la valeur `push`, mais le canal `push` reste **inactif** en v1 (aucun déclencheur, aucun toggle visible côté UI v1).
- **Notifications de dépenses** : la catégorie `expenses` existe dans l'énumération `NotificationPref.category` (réservation v2), mais **aucun déclencheur dépense** n'est branché en v1 (module dépenses masqué — invariant « Dépenses = v2 »). Les toggles `expenses` ne sont pas affichés tant que le module n'est pas actif.
- Notifications **in-app / temps réel** (badges, Turbo Streams over Mercure) : c'est le périmètre des lots temps réel (Lot 6 pour les échanges) ; ce lot ne traite **que** le canal e-mail. Le temps réel reste la voie « immédiate » ; l'e-mail est la voie « asynchrone/absence ».
- **SMS**, webhooks tiers, digests quotidiens configurables (au-delà du regroupement anti-spam de base).

### 0.4 Contexte v1 / v2

| Canal | v1 (ce lot) | v2 |
|---|:--:|:--:|
| E-mail | ✅ | |
| Push mobile | (réservé, inactif) | ✅ (Lot 11) |

| Catégorie de notification | v1 (ce lot) | v2 |
|---|:--:|:--:|
| `custody` | ✅ | |
| `agenda` | ✅ | |
| `exchanges` | ✅ | |
| `invitation` | ✅ | |
| `expenses` | (réservé, inactif) | ✅ (Lot 10/11) |

---

## 1. Dépendances

### 1.1 Lots prérequis (avant)

| Lot | Pourquoi requis |
|---|---|
| **Lot 0 — Fondations** | Fournit Symfony Mailer + templates Twig localisés, Symfony Messenger (transport Doctrine/Redis), le Scheduler, le composant Translation/ICU, MailHog en dev, et le squelette d'auth (`User.locale`, `User.email`). Le pipeline e-mail s'appuie directement sur cet outillage. |
| **Lot 1 — Familles, membres, droits, invitations, liens** | Fournit `Family`, `Membership`, `Right`, `Invitation`, le scope `X-Family-Id` et les Voters. Indispensable pour résoudre les **destinataires** d'une notification (membres d'une famille, filtrés par droit) et pour le déclencheur **invitation**. |
| **Lot 3 — Garde** | Fournit `CustodyPattern`, `CustodyDay`, `CustodyException`, `HolidayRule` et les événements de modification de garde → déclencheur catégorie `custody`. |
| **Lot 4 — Agenda** | Fournit `Event` (+ récurrence) et ses événements de cycle de vie → déclencheur catégorie `agenda` + rappels d'agenda. |
| **Lot 6 — Échanges** | Fournit `Exchange`, `Message`, `ExchangeOption`, `deadline`, et les événements temps réel (nouveau message, décision tranchée) → déclencheur catégorie `exchanges` + **rappels d'échéance** des décisions. |

> Conformité §9 : l'implémentation est **séquentielle** ; le Lot 7 vient après le Lot 6. Les lots 1/3/4/6 doivent exposer des **événements de domaine** (Symfony Events / `Messenger` messages internes) que ce lot consomme. Si un lot antérieur n'expose pas encore l'événement attendu, ce lot **ajoute** la dispatch minimale au point de mutation correspondant (sans modifier la logique métier).

### 1.2 Lots débloqués (après)

| Lot | Ce qu'il réutilise |
|---|---|
| **Lot 8 — PWA / offline / RGPD Manager** | L'`AuditLog` des envois et l'anonymiseur RGPD doivent neutraliser les destinataires/contenus des notifications déjà envoyées ; le registre des données personnelles doit contenir l'entrée `Invitation.email` 🔒 et les préférences. Les e-mails RGPD (lien d'export signé, confirmation de suppression) réutilisent le **pipeline et les templates** de ce lot. |
| **Lot 10 — Dépenses (v2)** | Branche les déclencheurs `expenses` (dépense ajoutée / remboursée / rappel) sur le **même pipeline** et active le toggle `expenses` dans l'UI préférences. Aucun changement de pipeline attendu. |
| **Lot 11 — Push mobile (v2)** | Ajoute le canal `push` à `NotificationPref` et à la **table de routage** des canaux. Le filtrage par catégorie, les quiet-hours, la locale et la déduplication sont **réutilisés tels quels** ; seul l'adaptateur de transport (e-mail → APNs/FCM) change. |

---

## 2. User stories complètes

> Format : **En tant que** <rôle>, **je veux** <action> **afin de** <bénéfice>. Critères d'acceptation en **Étant donné / Quand / Alors**, plusieurs scénarios par US (nominal, erreur, autorisation, temps réel/asynchrone, bornes).
> Rôles : `parent`, `step_parent` (beau-parent), `child` (enfant), `admin` (parent + attribut admin). Canaux v1 : e-mail uniquement.

### US-SET-1 — Gérer mes préférences de notification *(repris du cdc §3.8, finalisé ici)*

**En tant qu'**utilisateur (tout rôle), **je veux** activer/désactiver les notifications e-mail par catégorie et définir une plage de quiet-hours **afin de** ne recevoir que ce qui m'importe et pas de mails en pleine nuit.

**Critères d'acceptation**

- **Scénario nominal — lecture des préférences**
  - *Étant donné* un utilisateur authentifié possédant des `NotificationPref` (créées par défaut à l'inscription),
  - *Quand* il appelle `GET /api/me/notification-prefs` (ou ouvre l'écran Paramètres › Notifications),
  - *Alors* il reçoit la liste de ses préférences par catégorie (`custody`, `agenda`, `exchanges`, `invitation`) pour le canal `email`, plus ses quiet-hours (heure début/fin) et sa locale, le tout localisé.

- **Scénario nominal — mise à jour d'un toggle**
  - *Étant donné* un utilisateur avec `agenda=email:enabled`,
  - *Quand* il envoie `PUT /api/me/notification-prefs` désactivant `agenda` pour `email`,
  - *Alors* la préférence est persistée, une entrée `AuditLog` est écrite, et **aucune** notification e-mail de catégorie `agenda` ne lui est plus envoyée jusqu'à réactivation.

- **Scénario quiet-hours valides**
  - *Étant donné* un utilisateur qui définit des quiet-hours `22:00–07:00` (interprétées dans le fuseau de la famille active, défaut `Europe/Paris`),
  - *Quand* il enregistre,
  - *Alors* toute notification qui devrait partir pendant cette plage est **reportée** à la fin de la plage (cf. §3.3), sauf catégories marquées « critiques » non reportables (voir §2.9 invariants — aucune par défaut en v1).

- **Scénario d'erreur — plage de quiet-hours invalide**
  - *Étant donné* un utilisateur,
  - *Quand* il soumet des quiet-hours mal formées (ex. `25:00`) ou une catégorie inconnue,
  - *Alors* il reçoit une réponse `422`/`400` RFC 7807 (`application/problem+json`) avec un message **localisé** et la préférence n'est pas modifiée.

- **Scénario d'autorisation — préférences d'autrui**
  - *Étant donné* un utilisateur A,
  - *Quand* il tente de modifier les préférences d'un utilisateur B (les préférences ne sont **pas** scopées famille mais scopées utilisateur),
  - *Alors* l'API ne permet d'accéder qu'à `/api/me/...` ; aucune route ne permet d'éditer les préférences d'un tiers → `403`/`404`.

- **Scénario canal v1**
  - *Étant donné* la v1,
  - *Quand* l'utilisateur ouvre l'écran préférences,
  - *Alors* le canal `push` n'est **pas** proposé et la catégorie `expenses` n'est **pas** affichée (module dépenses inactif).

### US-NOTIF-1 — Être notifié d'un changement de garde

**En tant que** parent ou beau-parent habilité (droit `custody:view` ou plus), **je veux** recevoir un e-mail quand la garde change (exception, échange de jour, règle de vacances, régénération du modèle) **afin d'**adapter mon organisation.

**Critères d'acceptation**

- **Scénario nominal — exception créée**
  - *Étant donné* une famille `separated` avec deux parents et un beau-parent ayant `custody:view`,
  - *Quand* un parent crée une `CustodyException` (échange d'un jour),
  - *Alors* les **autres** membres concernés et habilités (`custody != none`) ayant `custody:email=enabled` reçoivent un e-mail localisé indiquant la date, l'ancien et le nouveau parent, et un lien vers le jour de garde ; l'auteur de l'action **ne se notifie pas lui-même**.

- **Scénario — régénération du modèle**
  - *Étant donné* un parent qui modifie le `CustodyPattern` (PUT pattern → régénère le futur),
  - *Quand* le moteur régénère les `CustodyDay` futurs (sans écraser exceptions ni règles vacances — invariant priorité),
  - *Alors* une **seule** notification « le planning de garde a été modifié » par destinataire est envoyée (pas une par jour régénéré — anti-spam, cf. §3.4), avec lien vers le planning.

- **Scénario d'autorisation refusée — destinataire sans droit custody**
  - *Étant donné* un beau-parent dont le droit `custody = none`,
  - *Quand* un changement de garde survient,
  - *Alors* ce beau-parent **ne reçoit aucune** notification de garde (filtrage par Voter `custody`), même si sa préférence `custody:email` est activée.

- **Scénario — enfant-membre**
  - *Étant donné* un enfant-membre avec `custody = view`,
  - *Quand* un changement de garde survient et que sa préférence `custody:email` est activée,
  - *Alors* il peut recevoir une notification de garde (lecture autorisée), mais **jamais** une notification d'édition de structure (l'enfant n'édite pas la garde — invariant).

- **Scénario — famille unie**
  - *Étant donné* une famille `intact` (pas de garde, onglet masqué),
  - *Quand* un événement de garde serait dispatché,
  - *Alors* aucun déclencheur `custody` ne s'applique (pas de planning de garde dans une famille unie).

- **Scénario — famille liée (overlay)**
  - *Étant donné* une famille liée affichée en overlay (lecture seule, agenda uniquement, §2.1),
  - *Quand* la garde de la famille liée change,
  - *Alors* **aucune** notification n'est envoyée aux membres de l'autre famille (la garde n'est jamais propagée par l'overlay ; pas de fuite hors agenda).

### US-NOTIF-2 — Être notifié d'un événement d'agenda

**En tant que** membre habilité (droit `agenda:view` ou plus), **je veux** recevoir un e-mail à la création/modification/suppression d'un événement me concernant **afin de** rester au courant des activités des enfants.

**Critères d'acceptation**

- **Scénario nominal — création**
  - *Étant donné* une famille avec un événement créé pour l'enfant Léa, responsable = un beau-parent,
  - *Quand* l'événement est créé,
  - *Alors* les membres habilités `agenda:view+` concernés (ceux qui ont accès à l'enfant ou « toute la famille ») et abonnés `agenda:email` reçoivent un e-mail avec titre, catégorie (couleur §8), enfant(s), responsable, date/heure (formatée dans le fuseau famille), lieu ; l'auteur ne s'auto-notifie pas.

- **Scénario — série récurrente**
  - *Étant donné* une création de série récurrente (hebdo) sur 6 mois,
  - *Quand* la série est créée,
  - *Alors* **une seule** notification « série créée » est envoyée (pas une par occurrence — anti-spam) ; les **rappels** d'occurrence sont gérés séparément (cf. US-NOTIF-6).

- **Scénario — modification d'occurrence vs série**
  - *Étant donné* une modification `scope=occurrence` puis une autre `scope=series`,
  - *Quand* chacune survient,
  - *Alors* la notification précise la portée (occurrence du <date> vs toute la série) ; deux modifications rapprochées sur la même série sont regroupées si elles tombent dans la fenêtre de coalescence (§3.4).

- **Scénario d'autorisation refusée**
  - *Étant donné* un membre avec `agenda = none`,
  - *Quand* un événement est créé,
  - *Alors* il ne reçoit **aucune** notification agenda.

- **Scénario — suppression**
  - *Étant donné* un événement supprimé,
  - *Quand* la suppression est confirmée,
  - *Alors* une notification « événement annulé » est envoyée aux concernés ; aucun lien mort (le lien renvoie vers l'agenda du jour, pas vers la ressource supprimée).

### US-NOTIF-3 — Être notifié d'un nouveau message dans un échange

**En tant que** participant à un échange (droit `exchanges:view` ou plus), **je veux** recevoir un e-mail quand un nouveau message est posté **afin de** suivre la discussion même hors connexion.

**Critères d'acceptation**

- **Scénario nominal**
  - *Étant donné* un échange `discussion` avec parents A, B et un beau-parent habilité,
  - *Quand* le parent A poste un message (déjà poussé en temps réel via Mercure au Lot 6),
  - *Alors* B et le beau-parent (si `exchanges:email=enabled`) reçoivent un e-mail ; A (auteur) ne reçoit rien ; le contenu du message (`Message.text` 🔒) est **déchiffré uniquement en mémoire** pour le rendu, jamais journalisé en clair.

- **Scénario — coalescence de rafale**
  - *Étant donné* le parent A qui poste 5 messages en 30 secondes,
  - *Quand* la fenêtre de coalescence (§3.4) s'applique,
  - *Alors* B reçoit **un seul** e-mail récapitulant « N nouveaux messages » plutôt que 5 e-mails.

- **Scénario — destinataire actuellement connecté/temps réel**
  - *Étant donné* B actuellement actif (Mercure live),
  - *Quand* un message arrive,
  - *Alors* B le voit en temps réel ; l'e-mail reste envoyé selon la préférence (la suppression conditionnée à la présence en ligne est **hors périmètre** v1 — la voie e-mail et la voie temps réel sont indépendantes, mais soumise au délai de coalescence qui laisse le temps de « lire »).

- **Scénario d'autorisation refusée**
  - *Étant donné* un membre avec `exchanges = none`,
  - *Quand* un message est posté,
  - *Alors* il ne reçoit aucune notification d'échange.

- **Scénario — rattachement à un enfant restreint**
  - *Étant donné* un échange rattaché à l'enfant Léa et un membre n'ayant pas accès à Léa,
  - *Quand* un message est posté,
  - *Alors* ce membre n'est **pas** destinataire (cohérence avec la visibilité de l'échange).

### US-NOTIF-4 — Être notifié d'une décision tranchée

**En tant que** participant à un échange de type `decision`, **je veux** recevoir un e-mail quand une option est validée et que la décision passe à `decided` **afin de** connaître le choix retenu.

**Critères d'acceptation**

- **Scénario nominal**
  - *Étant donné* un échange `decision` ouvert avec options,
  - *Quand* un membre choisit et valide une option (statut → `decided`, `decided_option_id` renseigné),
  - *Alors* les autres participants habilités et abonnés reçoivent « Décision prise : <option retenue> » avec lien vers l'échange.

- **Scénario — option validée intermédiaire**
  - *Étant donné* une décision nécessitant plusieurs validations,
  - *Quand* une option est validée par un membre sans clôturer la décision,
  - *Alors* la notification reflète l'état (« <membre> a validé l'option X ») sans annoncer faussement « décidé » ; le passage à `decided` déclenche une notification distincte.

- **Scénario d'erreur — décision déjà tranchée**
  - *Étant donné* une décision déjà `decided`,
  - *Quand* une seconde tentative de validation arrive (concurrence),
  - *Alors* le serveur (Lot 6) renvoie l'état canonique ; **aucune** notification dupliquée « décidé » n'est émise (idempotence sur `(exchangeId, decided)`).

### US-NOTIF-5 — Recevoir une invitation par e-mail

**En tant que** personne invitée (futur parent / beau-parent / enfant), **je veux** recevoir un e-mail avec un lien d'invitation à usage unique et expirant **afin de** rejoindre la famille.

**Critères d'acceptation**

- **Scénario nominal**
  - *Étant donné* un admin qui invite `bob@example.com` comme `step_parent` avec des droits proposés,
  - *Quand* l'invitation est créée (`POST /api/families/{id}/invitations`),
  - *Alors* un e-mail localisé part vers l'adresse invitée (champ `Invitation.email` 🔒 déchiffré en mémoire pour l'envoi), contenant un lien `accept` à usage unique et expirant, le nom de la famille, le rôle proposé et un récapitulatif des droits ; une entrée `AuditLog` (action `invitation.sent`) est écrite.

- **Scénario — locale de l'invité inconnue**
  - *Étant donné* que l'invité n'a pas encore de compte (locale inconnue),
  - *Quand* l'e-mail est rendu,
  - *Alors* la locale par défaut `fr-FR` est utilisée (fallback §3.5), ou la locale de l'inviteur si jugée plus pertinente — **décision : locale par défaut famille puis `fr-FR`** (jamais d'échec faute de locale).

- **Scénario — invitation périmée / révoquée (renvoi)**
  - *Étant donné* une invitation `expired` ou `revoked`,
  - *Quand* un admin la renvoie/recrée,
  - *Alors* un **nouveau** token est généré (l'ancien reste invalide), un nouvel e-mail part, et l'ancien lien renvoie une erreur localisée à l'ouverture.

- **Scénario d'autorisation refusée**
  - *Étant donné* un membre non-admin (sans `family_settings:edit`),
  - *Quand* il tente d'inviter,
  - *Alors* `403` (Voter `family_settings`) et **aucun** e-mail n'est envoyé.

- **Scénario — adresse e-mail invalide / rebond**
  - *Étant donné* une adresse mal formée détectée à la validation,
  - *Alors* `422` localisé avant tout envoi ; un rebond ultérieur (bounce) marque l'envoi en échec sans révéler l'adresse en clair dans les logs.

### US-NOTIF-6 — Recevoir des rappels d'échéance / d'événement

**En tant que** membre habilité, **je veux** recevoir un rappel e-mail avant l'échéance d'une décision et/ou avant un événement d'agenda **afin de** ne rien oublier.

**Critères d'acceptation**

- **Scénario nominal — décision dont l'échéance approche**
  - *Étant donné* un échange `decision` avec `deadline` à J+1 et un statut encore `open`,
  - *Quand* le job planifié de rappels s'exécute (Scheduler) à l'avance définie (ex. 24 h avant),
  - *Alors* les participants habilités et abonnés `exchanges:email` reçoivent « Échéance demain : <titre> » ; **un seul** rappel par fenêtre (déduplication sur `(exchangeId, reminderKind, occurrence)`).

- **Scénario — échéance dépassée**
  - *Étant donné* une décision `open` dont la `deadline` est passée,
  - *Quand* le job s'exécute,
  - *Alors* un rappel « En retard » est envoyé une **seule** fois (pas à chaque exécution du job), tant que la décision n'est pas tranchée.

- **Scénario — décision déjà tranchée avant le rappel**
  - *Étant donné* une décision passée à `decided` avant l'échéance,
  - *Quand* le job s'exécute,
  - *Alors* **aucun** rappel n'est envoyé pour cette décision.

- **Scénario — rappel d'événement d'agenda**
  - *Étant donné* un événement à venir avec rappel activé (par préférence catégorie `agenda`),
  - *Quand* le job atteint la fenêtre de rappel (ex. la veille au matin),
  - *Alors* un rappel localisé est envoyé, en tenant compte du fuseau famille et des occurrences récurrentes (chaque occurrence rappelée une seule fois).

- **Scénario — fuseau & changement d'heure**
  - *Étant donné* une échéance/événement proche d'un passage heure d'été/hiver (`Europe/Paris`),
  - *Quand* le job calcule la fenêtre,
  - *Alors* le calcul utilise le fuseau famille (DST géré par la bibliothèque de dates), sans décalage d'une heure ni double envoi (cf. §4 cas limites temporels).

- **Scénario d'autorisation refusée**
  - *Étant donné* un membre `exchanges=none` ou `agenda=none`,
  - *Quand* le job s'exécute,
  - *Alors* aucun rappel correspondant ne lui est envoyé.

### US-NOTIF-7 — Ne pas être dérangé pendant mes quiet-hours

**En tant qu'**utilisateur, **je veux** que les e-mails non urgents soient reportés en dehors de ma plage de silence **afin de** préserver mes nuits.

**Critères d'acceptation**

- **Scénario nominal — report**
  - *Étant donné* des quiet-hours `22:00–07:00` (fuseau famille),
  - *Quand* une notification doit partir à `23:15`,
  - *Alors* elle est **planifiée** pour `07:00` le lendemain (report), pas annulée.

- **Scénario — coalescence pendant la plage**
  - *Étant donné* plusieurs notifications de même catégorie reçues durant la plage de silence,
  - *Quand* la plage se termine,
  - *Alors* elles peuvent être regroupées en un récapitulatif à l'heure de fin de plage (anti-spam, §3.4).

- **Scénario — plage couvrant minuit**
  - *Étant donné* une plage `22:00–07:00` (passe minuit),
  - *Quand* une notif tombe à `01:00`,
  - *Alors* le report cible bien `07:00` du même « cycle » (gestion de plage circulaire, §3.3).

- **Scénario — quiet-hours désactivées**
  - *Étant donné* un utilisateur sans quiet-hours définies,
  - *Quand* une notification est prête,
  - *Alors* elle part immédiatement (selon préférence catégorie).

- **Scénario — invitation pendant quiet-hours**
  - *Étant donné* une invitation déclenchée à `23:00`,
  - *Quand* le pipeline applique les quiet-hours,
  - *Alors* **décision** : l'invitation est traitée comme **transactionnelle** (déclenchée par une action explicite de l'invité futur) → option configurable ; par défaut en v1 elle **respecte** les quiet-hours du destinataire **si** celui-ci a déjà un compte avec quiet-hours, sinon part immédiatement (invité sans compte → pas de quiet-hours connues).

### US-NOTIF-8 — Fiabilité d'envoi (retry & non-duplication)

**En tant qu'**utilisateur, **je veux** que les e-mails partent de façon fiable et sans doublon malgré les pannes transitoires **afin de** faire confiance au système.

**Critères d'acceptation**

- **Scénario — panne transitoire du serveur SMTP**
  - *Étant donné* un transport e-mail temporairement indisponible,
  - *Quand* l'envoi échoue,
  - *Alors* Messenger **réessaie** selon la politique de retry (backoff exponentiel, N tentatives) ; après épuisement, le message va en **dead-letter** (failed transport) et une alerte/log est produite ; aucune perte silencieuse.

- **Scénario — idempotence sur re-livraison Messenger**
  - *Étant donné* un message Messenger re-livré (at-least-once),
  - *Quand* il est traité une seconde fois,
  - *Alors* la clé d'idempotence (`notification_key`, §3.1) empêche un **second** envoi pour le même fait/destinataire.

- **Scénario — crash entre rendu et envoi**
  - *Étant donné* un crash worker après marquage « en cours » mais avant envoi confirmé,
  - *Quand* le worker redémarre,
  - *Alors* le statut transactionnel permet de rejouer sans double envoi observable (au pire un doublon borné — documenté ; viser exactly-once pratique via clé d'idempotence).

---

## 3. Algorithme(s)

### 3.1 Pipeline central : événement notifiable → e-mail

Conventions :
- Un **événement de domaine** est dispatché par les lots 1/3/4/6 (ex. `CustodyExceptionCreated`, `EventCreated`, `MessagePosted`, `DecisionDecided`, `InvitationCreated`).
- Le pipeline est **asynchrone** : l'événement de domaine est capté par un **subscriber synchrone léger** qui calcule les destinataires puis **dispatch un message Messenger par (destinataire, notification)**, traité par un worker.
- Idempotence portée par une **`notification_key`** déterministe : `hash(eventType + entityType + entityId + factVersion + recipientUserId + reminderKind?)`.

```
fonction onDomainEvent(event):                       # subscriber synchrone, dans la transaction métier
  1. Déterminer (famille, catégorie, type, ressource, auteurMembership).
     - catégorie ∈ {custody, agenda, exchanges, invitation}
  2. Si famille.type == 'intact' ET catégorie == 'custody' : STOP (pas de garde).
  3. recipients ← résoudreDestinataires(event)        # §3.2
  4. recipients ← recipients \ { auteurMembership }    # ne pas s'auto-notifier
  5. Pour chaque membership r dans recipients :
       a. Voter.check(domaine(catégorie), r, ressource) ≥ 'view' ?  sinon SKIP   # §3.2
       b. user ← r.user
       c. pref ← NotificationPref(user, catégorie, 'email')
          si pref absent → défaut (cf. §3.6) ; si pref.enabled == false → SKIP
       d. key ← notification_key(event, user)          # idempotence
       e. si déjà émise(key) → SKIP                     # déduplication (§3.4)
       f. Construire NotificationEnvelope {
            key, recipientUserId, locale(provisoire), catégorie, type,
            payloadRefs (IDs seulement, pas de clair sensible),
            createdAt (UTC)
          }
       g. dispatch Messenger(SendNotificationMessage(envelope))   # async
  # La dispatch effective est faite APRÈS commit de la transaction métier
  # (Messenger middleware doctrine_transaction / dispatch_after_current_bus).
```

```
worker handle(SendNotificationMessage env):           # asynchrone, idempotent
  1. Charger user, ressource (par IDs) ; si ressource supprimée → adapter (ex. "annulé").
  2. Si idempotence(env.key) déjà marquée 'sent' → ACK sans renvoyer.   # §3.7
  3. locale ← résoudreLocale(user, famille)            # §3.5
  4. now ← horloge() ; tz ← famille.timezone (défaut Europe/Paris)
  5. (delaiQuietHours, target) ← appliquerQuietHours(user, now, tz, catégorie)   # §3.3
       si delaiQuietHours > 0 :
          re-dispatch SendNotificationMessage(env) avec DelayStamp(target - now)
          marquer idempotence(env.key) = 'deferred'    # évite double report
          ACK ; RETURN
  6. (doitEnvoyer, contenuAgrégé) ← coalescence(env)   # §3.4 : fusionne rafales
       si !doitEnvoyer (absorbé dans un agrégat en attente) → ACK ; RETURN
  7. déchiffrer en mémoire les champs 🔒 strictement nécessaires au rendu
  8. html, text ← rendreTemplate(type, locale, contenuAgrégé, tz)   # Twig + ICU
  9. email ← composer(From, To=user.email, Subject localisé, html, text, headers)
 10. mailer.send(email)                                # peut lever → retry Messenger
 11. marquer idempotence(env.key) = 'sent', sentAt=now
 12. AuditLog(action='notification.sent', categorie, recipient, sansContenuClair)  # §3.8
 13. ACK
```

**Ordre des opérations / garanties :**
- Le filtrage **Voter** (5.a) précède le filtrage **préférence** (5.c) — un destinataire non habilité est exclu **même** s'il a activé la préférence (la sécurité prime).
- La **dispatch** des messages Messenger ne se fait qu'**après commit** de la transaction métier (`dispatch_after_current_bus` / middleware transaction) → on ne notifie jamais un fait annulé par un rollback.
- Coût : résolution des destinataires en O(membres de la famille) ; un message Messenger par destinataire effectif → O(destinataires éligibles).

### 3.2 Résolution des destinataires

```
fonction résoudreDestinataires(event):
  base ← memberships actifs de event.family (status='active')
  selon catégorie :
    custody    : base filtrés sur Voter(custody) ≥ view
    agenda     : base filtrés sur Voter(agenda) ≥ view
                 ∩ membres ayant accès à l'enfant concerné OU event.wholeFamily
    exchanges  : base filtrés sur Voter(exchanges) ≥ view
                 ∩ visibilité de l'échange (enfant rattaché ou whole_family)
    invitation : { destinataire = l'adresse invitée } (hors membership existant)
  exclure : membres en status 'invited' (pas encore actifs) sauf catégorie=invitation
  retourner ensemble de memberships (ou pseudo-destinataire e-mail pour invitation)
```

- **Famille liée / overlay** : les membres de la famille liée ne sont **jamais** destinataires (overlay = lecture seule agenda, aucune notification croisée — invariant scope famille + pas de fuite). Le scope famille (`X-Family-Id`) garantit qu'aucune ressource d'une autre famille n'entre dans la résolution.
- **Enfant** : inclus seulement si son droit le permet et **jamais** pour des actions d'édition de structure (garde/membres/réglages) ni pour `expenses`.

### 3.3 Quiet-hours (report)

```
fonction appliquerQuietHours(user, now_utc, tz, catégorie):
  qh ← user.quiet_hours        # { start: "HH:MM", end: "HH:MM" } ou null
  si qh == null : retourner (0, now_utc)
  si catégorie est "non reportable" (config ; aucune par défaut v1) : retourner (0, now_utc)
  local ← convertir(now_utc → tz)                 # DST géré par la lib de dates
  startToday ← local.date @ qh.start (dans tz)
  endTarget  ← local.date @ qh.end   (dans tz)
  # plage circulaire (peut passer minuit) :
  si qh.start <= qh.end :                          # plage dans la même journée
     dansPlage ← startToday <= local < endTarget
     finPlage  ← endTarget
  sinon :                                          # plage chevauchant minuit (ex. 22:00→07:00)
     dansPlage ← local >= startToday OU local < endTarget
     finPlage  ← (local < endTarget) ? endTarget : endTarget + 1 jour
  si dansPlage :
     finPlage_utc ← convertir(finPlage → UTC)      # recalcule l'offset DST à la cible
     retourner (finPlage_utc - now_utc, finPlage_utc)
  sinon :
     retourner (0, now_utc)
```

- **DST** : le report est calculé sur l'instant local cible puis **reconverti en UTC** à l'offset en vigueur **à la cible** (pas à l'instant courant) → un report vers `07:00` reste `07:00` local même si l'heure change dans l'intervalle.
- Le report utilise un **`DelayStamp`** Messenger ; pendant le report, la clé d'idempotence passe à `deferred` pour éviter qu'un nouvel événement identique ne crée un second report concurrent.

### 3.4 Coalescence / anti-spam

But : éviter les rafales (5 messages d'échange en 30 s, régénération de garde sur 540 jours, série récurrente sur 6 mois).

Stratégie :
1. **Niveau émission** (§3.1) : pour les opérations « en masse » (régénération de garde, création de série), le subscriber émet **une seule** notification de haut niveau (« planning modifié », « série créée ») au lieu d'une par jour/occurrence.
2. **Niveau worker — fenêtre de coalescence** par clé d'agrégat `(recipientUserId, catégorie, entityRootId)` :
   - À la première notification, ouvrir une **fenêtre** (ex. `COALESCE_WINDOW` = 60 s configurable) en plaçant le message avec un petit `DelayStamp` ;
   - Les notifications suivantes pour la même clé d'agrégat dans la fenêtre **incrémentent un compteur** (en base/redis) et sont ACK sans envoi propre ;
   - À l'expiration, le message « porteur » rend un e-mail agrégé (« N nouveaux messages dans <échange> »).
3. **Niveau déduplication stricte** : `notification_key` empêche le **même** fait d'être notifié deux fois (re-livraison Messenger, double dispatch).

Ordre : déduplication stricte (idempotence) **avant** coalescence (regroupement) — on ne regroupe que des faits distincts.

### 3.5 Résolution de la locale

```
fonction résoudreLocale(user, famille):
  si user != null ET user.locale renseignée : retourner user.locale
  sinon si famille.settings.locale : retourner famille.settings.locale   # contexte invitation
  sinon : retourner 'fr-FR'                                              # défaut (§10 cdc)
```

- La locale est **résolue côté worker** (au moment du rendu), pas à l'émission — l'utilisateur a pu changer sa langue entre-temps.
- Tout le rendu (sujet, corps, dates, nombres) passe par le composant Translation **ICU** ; aucune chaîne en dur dans le code. Dates/heures formatées dans le **fuseau famille** (défaut `Europe/Paris`).

### 3.6 Préférences par défaut

À la création d'un compte (Lot 0/1) ou au premier accès, matérialiser les `NotificationPref` par défaut :

| Catégorie | `email` par défaut |
|---|---|
| `custody` | enabled |
| `agenda` | enabled |
| `exchanges` | enabled |
| `invitation` | enabled |
| `expenses` (v2) | enabled (mais inactif tant que module masqué) |

- Absence d'enregistrement explicite ⇒ traité comme **enabled** (opt-out), sauf décision produit contraire. Les quiet-hours sont **null** par défaut (pas de silence).

### 3.7 Idempotence & transactions

- Table/clé d'idempotence `notification_dispatch` indexée sur `notification_key` (unique). États : `pending` → `deferred` → `sent` / `failed`.
- L'insertion de la clé se fait en **upsert** ; un conflit d'unicité = notification déjà prise en charge → ACK silencieux.
- Le **dispatch** Messenger se fait **après** commit métier (middleware `doctrine_transaction` + `dispatch_after_current_bus`).
- L'écriture `sent` + `AuditLog` doit être **atomique avec** ou **après** l'envoi confirmé ; en cas de crash entre envoi et marquage, la clé reste `pending` → au pire un doublon borné (documenté). Viser un marquage `sending` juste avant `mailer.send` pour réduire la fenêtre.

### 3.8 Journalisation (AuditLog) & confidentialité

- Journaliser : `notification.sent`, `notification.failed`, `invitation.sent`, avec `recipient_user_id`, `category`, `notification_type`, `family_id`, `occurred_at`. **Jamais** le contenu clair des champs 🔒 (texte de message, e-mail invité, nom complet) ; au plus un identifiant ou un hash.
- `AuditLog` est **append-only / immuable** (invariant §5.4).

### 3.9 Planification des rappels (Scheduler)

```
job RappelsEcheance() :   # exécuté périodiquement (ex. toutes les 15 min) via Scheduler
  now ← horloge()
  # --- décisions ---
  pour chaque Exchange e où e.type='decision' ET e.status='open' ET e.deadline != null :
     tz ← e.family.timezone
     fenêtres ← [ {kind:'before_24h', at: e.deadline - 24h},
                  {kind:'overdue',    at: e.deadline} ]
     pour chaque f dans fenêtres où now >= f.at :
        key ← dedup(e.id, f.kind)
        si !déjàEnvoyé(key) :
           recipients ← résoudreDestinataires(echangeEvent(e))
           pour chaque r éligible (Voter exchanges + pref) :
              dispatch SendNotificationMessage(rappel décision, key⊕r)
           marquer déjàEnvoyé(key)
  # --- événements d'agenda ---
  pour chaque occurrence d'Event survenant dans [now, now + horizonRappel] non déjà rappelée :
     # occurrences récurrentes : 1 rappel par (occurrence, jour)
     idem que ci-dessus avec catégorie agenda
```

- **Idempotence du job** : clé `dedup(entityId, reminderKind, occurrenceDate)` → un rappel par fait, quel que soit le nombre d'exécutions du job (le job peut tourner souvent sans spammer).
- **Fuseau** : toutes les comparaisons de bornes utilisent le fuseau famille (DST géré). Une exécution manquée (worker arrêté) est rattrapée à la reprise sans double envoi (grâce à la clé).
- Complexité : O(décisions ouvertes + occurrences dans l'horizon). Indexer `Exchange(status, deadline)` et l'horizon agenda.

---

## 4. Cas limites (edge cases)

### 4.1 Données aux bornes & temporel (fuseau Europe/Paris, DST, bissextiles)

- **Passage heure d'été (mars)** : nuit où `02:00→03:00`. Une notification reportée vers `07:00` reste `07:00` local ; une quiet-hours `02:00–03:00` ce jour-là peut être de durée nulle/ambiguë — comportement attendu : aucune erreur, report borné à la fin de plage effective.
- **Passage heure d'hiver (octobre)** : `03:00→02:00` (heure répétée). Un rappel calé sur l'instant local répété ne doit pas partir **deux fois** (clé d'idempotence sur `(entity, kind, occurrenceDate)` protège). 
- **Année bissextile** : échéance/événement au 29 février → arithmétique de dates via la lib (pas de `+365j` en dur).
- **Échéance exactement à minuit** : interprétée dans le fuseau famille ; le rappel `before_24h` et `overdue` ne se chevauchent pas.
- **Quiet-hours `00:00–00:00`** (vide/égal) : interprété comme **pas de silence** (durée nulle), aucune notification reportée.
- **Quiet-hours couvrant 24 h** (ex. `00:00–23:59`) : tout est reporté à la fin de plage → garde-fou : interdire/avertir une plage ≥ 23 h (validation) pour éviter le silence permanent.
- **Date des maquettes (23 juin 2026)** : utilisée seulement comme exemple d'affichage ; jamais codée en dur dans la logique de planification (invariant).

### 4.2 Concurrence & idempotence

- **Double dispatch** du même événement (re-livraison Messenger at-least-once) : bloqué par `notification_key` unique.
- **Deux changements de garde quasi simultanés** sur le même jour par deux parents : chaque fait a une `factVersion` distincte → deux notifications distinctes possibles, mais coalescées en « planning modifié » si dans la fenêtre.
- **Décision tranchée pendant que le rappel est en file** : le worker recharge l'état ; si `decided`, il **n'envoie pas** le rappel (vérification à l'envoi, pas seulement à l'émission).
- **Crash worker** entre `sending` et `sent` : au redémarrage, clé `pending/sending` → politique documentée (au pire doublon borné).
- **Course report/nouveau fait** : un nouvel événement identique pendant un report ne crée pas de second report (clé `deferred`).
- **Modification puis suppression** rapprochées d'un événement agenda : la notification de modification absorbée par la coalescence peut se résoudre en « annulé » si la ressource n'existe plus au moment du rendu (le worker recharge l'état).

### 4.3 Autorisations (parent / beau-parent / enfant / admin, dernier admin)

- **Beau-parent `custody=none`** : exclu des notifications de garde même si préférence active (Voter prioritaire).
- **Enfant** : reçoit au plus des notifications de lecture (garde/agenda/échanges selon droits) ; **jamais** d'édition de structure ni `expenses`.
- **Auteur de l'action** : jamais auto-notifié.
- **Membre `status='invited'`** (pas encore actif) : exclu des notifications métier ; ne reçoit que l'e-mail d'invitation.
- **Dernier admin** : non spécifique aux notifications, mais une action de rétrogradation/retrait du dernier admin est **refusée** en amont (Lot 1) — aucune notification « retiré » n'est donc émise pour ce cas interdit ; si une rétrogradation **valide** d'un autre admin a lieu, une notification de changement de droits **peut** être émise (hors périmètre strict du lot, à confirmer — par défaut **non** notifié par e-mail en v1, seulement journalisé).
- **Changement de droits faisant perdre un accès** : à partir du changement, l'utilisateur cesse d'être destinataire (résolution dynamique à chaque événement).

### 4.4 Scope famille (X-Family-Id)

- Toute résolution de destinataires part de `event.family` ; **aucune** requête métier non scopée (invariant). Un utilisateur multi-familles ne reçoit une notif que pour la famille concernée.
- **Famille liée (overlay)** : aucune notification croisée ; la garde n'est jamais propagée.
- Un membre **retiré** de la famille entre l'émission et l'envoi : exclu au moment de l'envoi (re-vérification) — pas de fuite post-départ.

### 4.5 Valeurs vides / limites

- **Aucun destinataire éligible** (tous ont désactivé / pas de droit) : pipeline ne produit aucun e-mail, aucune erreur ; éventuellement un log debug.
- **Famille à un seul membre actif** (autre invité non accepté) : aucun co-parent à notifier → rien d'envoyé.
- **Champ 🔒 vide** (message vide impossible côté Lot 6 ; note vide) : rendu gère le vide sans planter.
- **Locale utilisateur vide** : fallback (§3.5).
- **Préférence absente** : défaut enabled (§3.6).
- **`deadline` null** sur une décision : pas de rappel d'échéance (rien à planifier).

### 4.6 Échecs externes (e-mail, API vacances, Mercure hors-ligne)

- **SMTP indisponible** : retry Messenger (backoff) → dead-letter après épuisement ; pas de perte silencieuse ; supervision/log.
- **Adresse en rebond (bounce)** : marquée échec ; pas de boucle de renvoi ; e-mail jamais journalisé en clair (champ 🔒).
- **API vacances scolaires (data.education.gouv.fr)** : **hors périmètre direct** de ce lot, mais une **règle de vacances appliquée** (Lot 3) peut déclencher une notif `custody` ; si l'import a échoué, aucune règle n'est appliquée → aucune notif erronée.
- **Mercure hors-ligne** : le temps réel (Lot 6) et l'e-mail sont **indépendants** ; si Mercure tombe, l'e-mail reste la voie de secours et part normalement.
- **Redis/transport Messenger indisponible** : le subscriber synchrone échoue à dispatcher → la transaction métier ne doit **pas** être annulée pour autant (dispatch after commit) ; en cas d'indispo, repli sur le transport Doctrine (durable) configuré au Lot 0.

### 4.7 Conflits offline (mobile)

- Les préférences de notification éditées **hors-ligne** sur mobile sont mises en **outbox** (Lot 0) et rejouées avec `Idempotency-Key` ; en cas de conflit, autorité serveur (last-write-wins sur champs simples). 
- Une action métier offline (ex. exception de garde) rejouée à la reconnexion déclenche la notification **au moment du rejeu serveur**, pas au moment de l'action locale (la notification reflète l'état canonique validé serveur, §4.4 cdc).
- Pas de notification émise depuis le client offline ; **toutes** les notifications sont serveur-side.

### 4.8 Sécurité / RGPD

- **Champs 🔒** (`Message.text`, `Note.text`, `Invitation.email`, `full_name`, `phone`) : déchiffrés **uniquement en mémoire** pour le rendu/envoi, **jamais** persistés en clair, **jamais** journalisés en clair (AuditLog, logs applicatifs, dead-letter inclus).
- **Contenu sensible dans l'e-mail** : limiter le contenu (ex. titre de l'échange oui, extrait de message court ou simple « nouveau message » — décision produit : par défaut **ne pas** inclure le texte intégral d'un message dans l'e-mail, privilégier un lien). À confirmer dans les templates ; par défaut **minimisation**.
- **Pas de données de santé** (invariant) : aucun template, aucun champ de notification ne crée/typifie de donnée de santé ; les infos enfant restent libres et ne sont pas embarquées en clair dans les e-mails au-delà du strict nécessaire.
- **Liens d'invitation** : token haché, usage unique, expirant (Lot 1) ; l'e-mail ne contient que le token opaque.
- **En-têtes anti-énumération** : ne pas révéler dans un e-mail/réponse l'existence d'un compte (cohérent avec US-AUTH non-énumérant).
- **Désabonnement** : envisager un lien/footer renvoyant vers les préférences (pas un unsubscribe one-click non authentifié exposant des données) — au minimum, mention de la possibilité de gérer ses préférences dans l'app.
- **Anonymisation (Lot 8)** : un utilisateur supprimé ne doit plus recevoir de notifications ; les notifications en file le concernant sont purgées/neutralisées.

### 4.9 i18n / localisation

- **Locale mixte dans une famille** : chaque destinataire reçoit l'e-mail dans **sa** locale (rendu par destinataire).
- **Pluriels / genre** : gérés via ICU (ex. « N nouveaux messages »).
- **Fallback** : locale inconnue → famille → `fr-FR` ; jamais d'échec de rendu faute de traduction (clé manquante → fallback `fr-FR`, jamais la clé brute en prod ; test CI sur complétude des clés).
- **Formatage** : dates/heures dans le **fuseau famille** ; nombres/devise localisés ; sujet et corps entièrement traduits, y compris les libellés de catégorie et de couleur (texte, pas couleur).
- **Ajout d'une langue** : aucun changement de code, seulement de nouveaux fichiers de ressources (invariant i18n).

---

## 5. Modèle de données touché

> Tous les entités ont `id` (UUID), `created_at`, `updated_at`. 🔒 = chiffré au repos (AES-256-GCM applicatif).

### 5.1 Entité créée / finalisée

- **`NotificationPref`** *(déclarée §5.2, finalisée ici)*
  - `user_id` (FK → User, requis)
  - `category` (enum : `custody` | `agenda` | `exchanges` | `invitation` | `expenses`(réservé v2))
  - `channel` (enum : `email` | `push`(réservé v2)) — v1 : `email` seul actif
  - `enabled` (bool, défaut `true`)
  - **Contrainte d'unicité** : `(user_id, category, channel)`.
  - **Index** : `(user_id)`, `(user_id, channel)`.

- **Quiet-hours & locale** : portés au niveau **User** (préférence globale, indépendante de la famille) :
  - Réutiliser `User.locale` (déjà présent §5.2).
  - Ajouter (si non présent) `User.quiet_hours_start` / `User.quiet_hours_end` (`time`, nullable) **ou** un champ `User.settings` jsonb `{ quietHours: { start, end } }`. **Décision** : champ structuré nullable, validé (`HH:MM`), interprété dans le fuseau de la famille active.

### 5.2 Entité technique créée (infrastructure de notification)

- **`NotificationDispatch`** *(nouvelle, technique — non personnelle au sens contenu, mais lie un destinataire à un fait)*
  - `notification_key` (string, **unique**) — idempotence
  - `recipient_user_id` (FK → User)
  - `category`, `notification_type`
  - `family_id` (FK → Family, nullable pour invitation pré-membership)
  - `status` (enum : `pending` | `deferred` | `sending` | `sent` | `failed`)
  - `scheduled_at` (datetime UTC, pour report quiet-hours / coalescence)
  - `sent_at` (datetime UTC, nullable)
  - `aggregate_key` (string, nullable) + `aggregate_count` (int, pour coalescence)
  - `payload_refs` (jsonb : IDs uniquement, **aucun** champ 🔒 en clair)
  - **Index** : `(notification_key)` unique, `(aggregate_key, status)`, `(status, scheduled_at)`.
  - **Registre des données personnelles** : comme cette table relie un destinataire à des faits, l'ajouter au registre (catégorie « technique / traçabilité », base légale intérêt légitime, rétention bornée — purge au Lot 8). `payload_refs` ne contient aucune donnée personnelle en clair.

### 5.3 Entités lues (non modifiées) pour la résolution

- **User** (`email`, `locale`, quiet-hours, `full_name` 🔒 pour la salutation éventuelle).
- **Membership** (`role`, `is_admin`, `status`, `child_id`) et **Right** (filtrage Voter).
- **Family** (`type`, `timezone`, `settings.locale`).
- **Invitation** (`email` 🔒, `role`, `proposed_rights`, `token_hash`, `expires_at`, `status`).
- **Exchange** (`type`, `deadline`, `status`, `decided_option_id`), **Message** (`text` 🔒, `sent_at`), **ExchangeOption**.
- **Event** (`title`, `category`, `starts_at`, `recurrence`, relation Child / `whole_family`).
- **CustodyException**, **CustodyDay**, **CustodyPattern**, **HolidayRule** (pour le libellé de la notif garde).
- **Note** (`text` 🔒) si une notif note est ajoutée (note temps réel existait Lot 2 ; e-mail note optionnel — par défaut **non** notifié par e-mail en v1 sauf décision, car catégorie non listée §2.9 ; **à exclure** du périmètre e-mail v1 pour rester fidèle aux 4 catégories du cdc).

### 5.4 Champs chiffrés 🔒 touchés

- `Invitation.email` (lecture + déchiffrement mémoire pour l'envoi). 
- `Message.text`, `Note.text`, `User.full_name`, `User.phone` (déchiffrement mémoire si rendu, sinon non lus). Aucun nouveau champ 🔒 créé par ce lot.

---

## 6. Endpoints API concernés

| Méthode | Chemin | Droit / Rôle | Description |
|---|---|---|---|
| `GET` | `/api/me/notification-prefs` | auth (utilisateur) | Lire mes préférences (catégories × canal `email`) + quiet-hours + locale. |
| `PUT` | `/api/me/notification-prefs` | auth (utilisateur) | Mettre à jour préférences/quiet-hours (validation ICU/RFC 7807). |
| `GET/PATCH` | `/api/me` | auth | Profil & `locale` (relais depuis l'écran Notifications pour la langue). |
| `POST` | `/api/families/{id}/invitations` | `family_settings` (admin) | Crée l'invitation → déclenche l'**e-mail d'invitation** (catégorie `invitation`) via le pipeline. |
| `POST` | `/api/invitations/accept` | public + token | Acceptation (n'envoie pas de notif e-mail au sens de ce lot, mais peut journaliser). |

> **Endpoints déclencheurs indirects** (définis dans leurs lots respectifs, **consommés** ici via événements de domaine — pas redéfinis par le Lot 7) :
> - Garde : `PUT /api/families/{id}/custody/pattern`, `POST/DELETE /api/families/{id}/custody/exceptions`, `PUT /api/holidays/{id}/rule` → catégorie `custody`.
> - Agenda : `POST /api/families/{id}/events`, `PATCH/DELETE /api/events/{id}` → catégorie `agenda`.
> - Échanges : `POST /api/exchanges/{id}/messages`, `POST /api/exchanges/{id}/decide`, `GET/PATCH /api/exchanges/{id}` (clôture) → catégorie `exchanges`.

**Conventions applicables**
- **`X-Family-Id`** : requis sur les endroits déclencheurs scopés famille (garde/agenda/échanges/invitations). Les préférences `/api/me/...` sont scopées **utilisateur**, donc `X-Family-Id` non requis.
- **RFC 7807** (`application/problem+json`) : erreurs de validation des préférences et `403` de droit insuffisant, messages **localisés**.
- **`Idempotency-Key`** : accepté sur le `PUT /api/me/notification-prefs` issu de l'outbox mobile offline (rejeu sans double effet).
- **OpenAPI** : ces endpoints (au moins `notification-prefs`) doivent figurer dans le schéma exporté → régénération du client Dart.

---

## 7. Invariants & règles métier

Issus du cdc et de `CLAUDE.md`, **pertinents pour ce lot** :

1. **Scope famille obligatoire** : toute résolution de destinataires et tout déclencheur métier sont scopés à la famille via `X-Family-Id` / `event.family`. Jamais de requête métier non scopée.
2. **Autorisation par Voters** : le destinataire d'une notification est filtré par le Voter du domaine correspondant (`custody`, `agenda`, `exchanges`, `family_settings` pour l'invitation), niveau ≥ `view`. La sécurité **prime** sur la préférence.
3. **Enfant à droits limités** : jamais de notification d'édition de structure ni `expenses` pour un enfant ; lecture seule selon droits.
4. **Dernier admin protégé** : aucune notification ne contourne ni n'accompagne une action interdite (retrait/rétrogradation du dernier admin) — l'action est refusée en amont.
5. **Priorité de la garde** (exception > vacances > base) : les notifications de garde reflètent l'état **effectif** après application des priorités ; la régénération du modèle ne notifie pas l'écrasement d'exceptions (elles ne sont pas écrasées).
6. **Champs chiffrés au repos** : `Invitation.email`, `Message.text`, `Note.text`, `full_name`, `phone` déchiffrés uniquement en mémoire ; jamais en clair en base, logs, AuditLog ou dead-letter.
7. **Registre des données personnelles** : ajouter les entrées pour `NotificationPref` (préférences = donnée personnelle), quiet-hours/locale sur User, et `NotificationDispatch` (traçabilité destinataire). Mettre à jour `/backend/docs/registre-donnees.md` + attributs déclaratifs ; le test CI de cohérence doit passer.
8. **Pas de données de santé** : aucun champ/typage santé ; minimisation du contenu enfant dans les e-mails.
9. **i18n / ICU** : toutes les chaînes (sujet + corps HTML + texte) externalisées en ICU dès le départ ; rendu par locale du destinataire ; `fr-FR` seule livrée mais ajout d'une langue sans code.
10. **Fuseau & dates** : `Europe/Paris` par défaut ; aucune date codée en dur (pas le 23 juin 2026) ; DST géré.
11. **Asynchrone & fiabilité** : envoi via Messenger (dispatch after commit), retry + dead-letter ; idempotence sur `notification_key`.
12. **Dépenses = v2** : catégorie `expenses` réservée mais inactive (aucun déclencheur, toggle masqué).
13. **Push = v2** : canal `push` réservé mais inactif (Lot 11).
14. **`/infra/INSTALL.md`** : mettre à jour si une dépendance/réglage change (transport Messenger e-mail, configuration Scheduler des rappels, variables d'environnement Mailer/quiet-hours).

---

## 8. Definition of Done (DoD)

Conforme à la **DoD générale §9** + spécificités du lot.

### 8.1 Tests
- **Unitaires (PHPUnit)** :
  - Résolution des destinataires (par catégorie, par droit Voter, exclusion auteur, famille unie, overlay exclu).
  - Filtrage par `NotificationPref` (enabled/disabled, défaut, catégorie inconnue rejetée).
  - Quiet-hours : plage simple, plage circulaire (passe minuit), DST été/hiver, plage vide, plage ≥ 23 h refusée.
  - Coalescence (rafale messages, régénération garde, série agenda) et déduplication (`notification_key`).
  - Résolution de locale (user / famille / fallback `fr-FR`).
  - Idempotence du job de rappels (pas de double envoi sur exécutions répétées ; décision tranchée → pas de rappel).
- **Fonctionnels / API (client de test Symfony)** :
  - `GET/PUT /api/me/notification-prefs` (auth, validation RFC 7807, scope utilisateur, refus accès tiers).
  - Déclencheur invitation : `POST /api/families/{id}/invitations` envoie un e-mail (capté MailHog/transport de test), droit `family_settings` requis (`403` sinon).
  - Déclencheurs garde/agenda/échanges produisent les messages Messenger attendus (transport de test in-memory), avec les bons destinataires.
  - Champs 🔒 non révélés dans les logs/AuditLog.
- **E2E web (Playwright)** :
  - Parcours préférences : désactiver `agenda`, vérifier qu'aucun e-mail agenda n'est reçu (MailHog) ; réactiver.
  - Invitation de bout en bout : admin invite → e-mail reçu (MailHog) → lien d'acceptation valide.
  - Quiet-hours : régler une plage, déclencher une notif, vérifier le report (via transport simulé / fast-forward horloge).
- **Flutter** : tests widget de l'écran Notifications (toggles, quiet-hours, locale) + test d'intégration outbox (modif préférence offline → resynchro avec `Idempotency-Key`).

### 8.2 Migrations
- Migration `NotificationPref` (table + unicité `(user_id, category, channel)`).
- Migration quiet-hours sur `User` (champs structurés nullable).
- Migration `NotificationDispatch` (idempotence/coalescence) + index.

### 8.3 OpenAPI
- `notification-prefs` (GET/PUT) documenté, schémas de requête/réponse + erreurs RFC 7807 ; **client Dart régénéré** (`/contracts`).

### 8.4 i18n
- Toutes les chaînes des templates (sujet + HTML + texte) externalisées en ICU, locale `fr-FR` complète ; test CI de **complétude des clés** ; fallback validé ; aucune chaîne en dur.
- Dates/heures formatées dans le fuseau famille ; pluriels ICU vérifiés.

### 8.5 Registre des données personnelles
- Entrées ajoutées pour `NotificationPref`, quiet-hours/locale (User) et `NotificationDispatch` ; `/backend/docs/registre-donnees.md` à jour ; **test CI de cohérence vert** (tout champ personnel a une entrée).

### 8.6 INSTALL.md
- `/infra/INSTALL.md` mis à jour : configuration Mailer (SMTP/MailHog), transport Messenger pour notifications (Doctrine/Redis) + worker, configuration du **Scheduler** des rappels d'échéance, variables d'environnement (expéditeur, fenêtres de coalescence/quiet-hours par défaut), commande de lancement du worker et du scheduler.

### 8.7 Qualité & analyse statique
- **PHPStan** niveau élevé propre, **php-cs-fixer** appliqué ; `dart analyze` / `dart format` propres côté mobile. Couverture ≥ seuil défini au Lot 0.

### 8.8 Conformité design system (§8)
- Templates e-mail HTML respectant la palette §8 (terracotta/teal sur crème), typographies (fallback web-safe en e-mail, polices `Bricolage Grotesque`/`Hanken Grotesk` en titres si supportées, sinon repli), libellés/couleurs de catégories agenda cohérents.
- Écran Paramètres › Notifications (web Twig/UX + Flutter iOS « liquid glass » / Material) conforme au design system : toggles, sections par catégorie, sélecteur de quiet-hours, sélecteur de langue.

### 8.9 Sécurité / RGPD
- Aucun champ 🔒 en clair dans dump SQL, logs, AuditLog, dead-letter.
- Minimisation du contenu sensible dans les e-mails (pas de texte intégral de message par défaut ; lien vers l'app).
- Aucune donnée d'enfant exposée hors famille ; aucune notification croisée via overlay.
