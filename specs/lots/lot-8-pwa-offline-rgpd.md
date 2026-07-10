# Lot 8 — PWA web, offline mobile & RGPD Manager

> Document de conception détaillée. Source de vérité : `specs/cdc.md` (§4.3, §4.4, §5.2, §5.3, §5.4, §6.2, §7, §7.1, §9 Lot 8) et `CLAUDE.md` (invariants verrouillés). En cas de divergence entre ce document et le cdc, le cdc prime. Locale par défaut `fr-FR`, fuseau par défaut `Europe/Paris`. Aucune date n'est codée en dur (la date de référence des maquettes, 23 juin 2026, est une donnée d'exemple, jamais une constante de code).

---

## 0. Objectif & périmètre

### 0.1 Objectif

Le Lot 8 transforme l'application en produit installable et résilient hors-ligne, et livre le sous-système de conformité RGPD complet. Trois chantiers parallèles mais indépendants par leur logique :

1. **PWA web (offline dégradé)** — rendre le front Symfony UX installable (manifest + service worker), avec un cache du shell applicatif et des écrans consultés récemment en lecture seule. Hors-ligne, toute tentative de mutation est bloquée et signalée par un message clair. L'offline « riche » avec file d'écritures n'est PAS porté par le web (décision verrouillée cdc §4.3).
2. **Offline mobile riche (Flutter)** — valider de bout en bout le parcours hors-ligne mobile reposant sur le cache local Drift, l'**outbox pattern** (mise en file des mutations hors-ligne, rejeu à la reconnexion avec `Idempotency-Key`) et la **résolution de conflits** (autorité serveur ; last-write-wins sur champs simples ; validation serveur + état canonique pour garde/décisions ; refus signalé). Le socle technique (Drift + squelette outbox) a été posé au Lot 0 ; le Lot 8 le finalise et le teste contre toutes les ressources mutables livrées (Lots 1 à 7).
3. **RGPD Manager complet (`RgpdManager`, cdc §7.1)** — les 5 composants : registre des données personnelles (attributs déclaratifs sur entités + `registre-donnees.md` + test de cohérence CI), règles de rétention/anonymisation, anonymiseur, purge planifiée idempotente avec dry-run, et rapports (registre, opérations, conformité). Endpoints `/api/admin/rgpd/*`, `/api/me/export`, `DELETE /api/me`. `AuditLog` branché partout. Chiffrement au repos des champs 🔒 vérifié (un dump SQL brut ne révèle ni identités ni contenus).

### 0.2 Dans le périmètre

- Web : `manifest.webmanifest`, service worker (stratégie de cache shell + écrans récents RO, blocage des mutations hors-ligne, bannière de reconnexion), prompt d'installation, écran/état hors-ligne, icônes et splash conformes au design system §8.
- Mobile : finalisation de l'outbox (persistance Drift, statuts, rejeu ordonné, `Idempotency-Key`), moteur de résolution de conflits, indicateurs UI « en attente de synchro » / « conflit », tests d'intégration offline → resynchro.
- Backend : entités `AuditLog`, `RetentionRule`, `RgpdOperation` (création/finalisation au Lot 8), composant `RgpdManager` (5 sous-modules), endpoints RGPD, branchement systématique de l'`AuditLog` sur toutes les actions sensibles déjà livrées par les lots précédents, vérification effective du chiffrement applicatif AES-256-GCM des champs 🔒.
- Idempotence côté serveur : prise en charge du header `Idempotency-Key` sur les POST issus de l'outbox (cdc §6.1).
- Registre des données personnelles : attributs déclaratifs PHP sur chaque champ personnel, génération de `/backend/docs/registre-donnees.md`, test CI de cohérence (champ personnel sans entrée ⇒ échec).

### 0.3 Hors périmètre

- **Notifications push mobile (APNs/FCM)** : Lot 11, v2.
- **Dépenses** (`Expense`, `ExpenseShare`) : Lot 10, v2 ; masquées en v1. Le RGPD Manager doit toutefois prévoir la place de ces champs dans le registre dès qu'ils existent (le test de cohérence n'échoue que sur des champs effectivement présents et marqués personnels).
- Offline web « riche » (file d'écritures côté web) : explicitement exclu par cdc §4.3.
- Toute donnée de santé : interdiction absolue (cdc §1.4, §7) — aucun champ typé santé n'est créé ni traité.

### 0.4 Contexte v1/v2

Tout le périmètre du Lot 8 est **v1**. PWA/offline et RGPD (chiffrement, export/suppression, journalisation) sont marqués v1 dans la matrice cdc §1.3. Le module Dépenses et le push mobile restent v2 et ne sont pas activés ici.

---

## 1. Dépendances

### 1.1 Lots prérequis (avant)

| Lot | Pourquoi il est requis |
|---|---|
| **Lot 0 — Fondations** | Squelette du `RgpdManager` et `registre-donnees.md` initialisé ; socle offline mobile (Drift + squelette outbox) ; AssetMapper/Turbo/Stimulus pour la PWA ; harnais de tests (PHPUnit, Playwright, Flutter) ; Scheduler/Messenger pour la purge planifiée ; coffre/KMS UE pour les clés de chiffrement. |
| **Lot 1 — Familles, membres, droits, invitations, liens** | Fournit `User`, `Family`, `Membership`, `Right`, `Invitation`, `FamilyLink`, le scope `X-Family-Id` et les Voters. Données personnelles 🔒 (`full_name`, `phone`, `Invitation.email`) à inscrire au registre, à chiffrer et à inclure dans export/anonymisation. Protection du dernier admin à respecter lors d'une suppression de compte. |
| **Lot 2 — Enfants & notes** | `Child` (info jsonb), `Note.text` 🔒 → anonymisation « auteur anonymisé », export, registre. |
| **Lot 3 — Garde** | `CustodyPattern`, `CustodyDay`, `CustodyException`, `SchoolHoliday`, `HolidayRule` : objets de conflit offline « garde » (validation serveur + état canonique) ; horizon glissant de la purge (`CustodyDay` au-delà de l'horizon). |
| **Lot 4 — Agenda** | `Event` (+ récurrence) : mutations dans l'outbox, export, écrans cachés RO en PWA. |
| **Lot 5 — Familles liées & overlay** | Destinataires « familles liées » à documenter au registre ; l'overlay RO ne doit jamais fuir hors agenda même hors-ligne. |
| **Lot 6 — Échanges (temps réel)** | `Exchange`, `ExchangeOption`, `Message.text` 🔒, vote/décision : objets de conflit offline « décisions » (validation serveur + état canonique) ; anonymisation des messages (préserver le fil) ; dégradation Mercure hors-ligne. |
| **Lot 7 — Notifications e-mail** | `NotificationPref` à inclure au registre/export ; un export ou une suppression peut déclencher un e-mail (réutilise le Mailer localisé du Lot 7). |

> Le Lot 8 est le **dernier lot v1** et consolide la conformité et la résilience de tout ce qui précède. Il a besoin que chaque ressource mutable et chaque champ personnel existent déjà.

### 1.2 Lots débloqués (après)

| Lot | Ce que le Lot 8 lui apporte |
|---|---|
| **Lot 10 — Dépenses (v2)** | À l'introduction de `Expense`/`ExpenseShare`, le registre, l'export, l'anonymisation, l'outbox et la résolution de conflits devront simplement être étendus en suivant les patterns posés au Lot 8 (entrée de registre obligatoire, test de cohérence déjà en place). |
| **Lot 11 — Push mobile (v2)** | Les tokens d'appareil seront des données techniques à inscrire au registre et à purger ; la mécanique RGPD/registre du Lot 8 les couvrira par extension. |

---

## 2. User stories complètes

Conventions Gherkin : « Étant donné / Quand / Alors ». Chaque US couvre au minimum le cas nominal, un cas d'erreur, un cas d'autorisation refusée si pertinent, et un cas temps réel/offline si applicable. Le contexte famille (`X-Family-Id`) et les Voters s'appliquent partout où une ressource métier est touchée.

---

### 2.1 PWA web (offline dégradé)

#### US-PWA-1 — Installer l'application web
**En tant qu'** utilisateur web, **je veux** installer CoParentPlanner comme application (PWA) sur mon appareil **afin de** l'ouvrir comme une app native, en plein écran, depuis mon écran d'accueil.

- **Scénario nominal**
  - Étant donné que je consulte l'app dans un navigateur compatible PWA et que le `manifest.webmanifest` et le service worker sont servis,
  - Quand le navigateur détecte les critères d'installabilité (manifest valide, SW enregistré, servi en HTTPS),
  - Alors un point d'entrée d'installation est proposé (prompt natif et/ou bouton Stimulus « Installer l'application »), et après installation l'app se lance en mode `standalone` avec l'icône et le nom définis dans le manifest.
- **Scénario manifest incomplet / non installable**
  - Étant donné un navigateur qui ne remplit pas les critères (ex. pas d'icône maskable, pas de HTTPS),
  - Quand je charge l'app,
  - Alors aucun prompt n'est affiché, l'app reste pleinement utilisable en mode onglet, et aucune erreur n'est montrée à l'utilisateur.
- **Scénario refus / report d'installation**
  - Étant donné que le prompt d'installation a été affiché,
  - Quand je le refuse,
  - Alors le bouton « Installer » reste disponible pour une tentative ultérieure et le refus n'est pas redemandé de façon intrusive (pas de prompt à chaque navigation).
- **Scénario localisation**
  - Étant donné ma locale `fr-FR`,
  - Quand l'app s'installe,
  - Alors le `name`/`short_name`/`description` du manifest et tous les libellés de l'UI d'installation sont en français (chaînes externalisées ICU).

#### US-PWA-2 — Consulter hors-ligne les écrans récents (lecture seule)
**En tant qu'** utilisateur web, **je veux** consulter le shell de l'app et les écrans que j'ai visités récemment même sans connexion **afin de** garder accès à l'information déjà chargée.

- **Scénario nominal**
  - Étant donné que j'ai consulté l'Accueil, la Garde et l'Agenda en ligne (mis en cache par le SW),
  - Quand je perds la connexion puis ouvre l'app,
  - Alors le shell applicatif s'affiche, les écrans précédemment consultés sont rendus depuis le cache en lecture seule, et une bannière persistante « Hors-ligne — affichage des données mises en cache » est visible.
- **Scénario écran jamais consulté**
  - Étant donné que je n'ai jamais ouvert l'écran Échanges en ligne,
  - Quand je tente d'y naviguer hors-ligne,
  - Alors une page hors-ligne dédiée s'affiche (« Cet écran n'est pas disponible hors-ligne »), sans erreur navigateur brute, avec une action « Réessayer » qui recharge dès le retour réseau.
- **Scénario données potentiellement périmées**
  - Étant donné des données mises en cache datant d'avant ma déconnexion,
  - Quand je les consulte hors-ligne,
  - Alors elles sont clairement marquées comme potentiellement non à jour (horodatage de dernière synchro affiché), sans prétendre au temps réel.
- **Scénario scope famille hors-ligne**
  - Étant donné que j'appartiens à plusieurs familles et que seule la famille active a été consultée en ligne,
  - Quand je suis hors-ligne,
  - Alors seuls les écrans en cache de la famille active sont disponibles ; basculer vers une autre famille non mise en cache renvoie la page hors-ligne dédiée (le cache ne mélange jamais les scopes familles).

#### US-PWA-3 — Tenter une mutation hors-ligne sur le web (blocage explicite)
**En tant qu'** utilisateur web hors-ligne, **je veux** être averti clairement qu'une modification est impossible **afin de** ne pas croire qu'elle a été enregistrée.

- **Scénario nominal (blocage)**
  - Étant donné que je suis hors-ligne,
  - Quand je soumets une mutation (créer un événement, poster un message, ajouter une exception de garde…),
  - Alors la requête est interceptée par le service worker (ou la couche Stimulus/Turbo), n'est PAS mise en file, et un message clair s'affiche : « Action impossible hors-ligne — reconnectez-vous pour enregistrer. » L'état précédent reste inchangé.
- **Scénario perte de connexion en cours de saisie**
  - Étant donné que j'ai rempli un formulaire en ligne et perds la connexion avant l'envoi,
  - Quand je soumets,
  - Alors la saisie n'est pas perdue (le formulaire reste rempli) et le message de blocage hors-ligne s'affiche ; à la reconnexion je peux re-soumettre.
- **Scénario reconnexion**
  - Étant donné que j'étais hors-ligne,
  - Quand la connexion revient,
  - Alors la bannière hors-ligne disparaît, une notification « Connexion rétablie » apparaît brièvement, et les mutations redeviennent possibles. Aucune mutation n'a été silencieusement rejouée (pas d'outbox web).
- **Scénario autorisation (cohérence avec les Voters)**
  - Étant donné un membre sans droit `agenda:edit`,
  - Quand il revient en ligne et soumet une création d'événement,
  - Alors le serveur refuse (403 RFC 7807, `agenda:edit` requis) exactement comme en mode connecté — l'offline n'ouvre aucune porte d'autorisation.

#### US-PWA-4 — Mise à jour de l'application installée
**En tant qu'** utilisateur ayant installé la PWA, **je veux** recevoir les nouvelles versions **afin de** ne pas rester bloqué sur une version obsolète du shell mis en cache.

- **Scénario nominal**
  - Étant donné qu'une nouvelle version du service worker est déployée,
  - Quand je rouvre l'app en ligne,
  - Alors le nouveau SW s'installe en arrière-plan, et une invite discrète « Nouvelle version disponible — recharger » est proposée ; après acceptation, le SW prend le contrôle (`skipWaiting`/`clients.claim`) et le shell est rafraîchi.
- **Scénario invalidation du cache**
  - Étant donné un changement de version d'assets (hash AssetMapper),
  - Quand le nouveau SW s'active,
  - Alors les anciens caches versionnés sont purgés pour éviter de servir des assets périmés.

---

### 2.2 Offline mobile riche (Flutter)

#### US-OFF-1 — Consulter les données en cache hors-ligne (mobile)
**En tant qu'** utilisateur mobile, **je veux** consulter mes données déjà synchronisées même sans réseau **afin de** rester opérationnel en déplacement.

- **Scénario nominal**
  - Étant donné que mes familles, ma garde, mon agenda, mes enfants et mes échanges ont été synchronisés dans le cache Drift,
  - Quand je passe hors-ligne et navigue,
  - Alors tous les écrans affichent les données du cache local, avec un indicateur global « Mode hors-ligne ».
- **Scénario cache partiel**
  - Étant donné qu'une ressource n'a jamais été chargée,
  - Quand j'y accède hors-ligne,
  - Alors un état vide explicite est affiché (« Données non disponibles hors-ligne »), sans plantage.
- **Scénario scope famille**
  - Étant donné plusieurs familles en cache,
  - Quand je bascule de famille hors-ligne,
  - Alors seules les données de la famille sélectionnée s'affichent ; le cache est cloisonné par `family_id` (jamais de fuite inter-familles).

#### US-OFF-2 — Créer/modifier hors-ligne et mettre en file (outbox)
**En tant qu'** utilisateur mobile, **je veux** créer ou modifier des données hors-ligne et qu'elles soient enregistrées localement en file d'attente **afin de** ne pas perdre ma saisie et qu'elle parte dès le retour réseau.

- **Scénario nominal (mise en file)**
  - Étant donné que je suis hors-ligne et autorisé (droit `edit` du domaine),
  - Quand je crée un événement,
  - Alors la mutation est persistée dans l'outbox Drift avec une `Idempotency-Key` générée localement, l'UI affiche optimistically l'entité avec un badge « En attente de synchro », et l'écran reste navigable.
- **Scénario rejeu à la reconnexion**
  - Étant donné des mutations en file,
  - Quand le réseau revient,
  - Alors l'outbox rejoue les requêtes dans l'ordre d'enfilement, en envoyant la même `Idempotency-Key`, et chaque succès retire l'entrée de la file et remplace l'optimiste par l'état serveur (badge retiré).
- **Scénario rejeu idempotent (doublon réseau)**
  - Étant donné qu'une requête a été reçue par le serveur mais que la réponse s'est perdue,
  - Quand l'outbox rejoue la même `Idempotency-Key`,
  - Alors le serveur renvoie le résultat déjà calculé sans créer de doublon (réponse identique au premier traitement).
- **Scénario autorisation refusée au rejeu**
  - Étant donné qu'entre la mise en file et la reconnexion mes droits ont été retirés (ex. `agenda` passé à `view`),
  - Quand l'outbox rejoue la mutation,
  - Alors le serveur répond 403 (RFC 7807), l'entrée passe au statut « rejetée », l'optimiste local est annulé (rollback), et l'utilisateur est notifié (« Modification refusée : droits insuffisants »).
- **Scénario erreur de validation au rejeu**
  - Étant donné une mutation devenue invalide côté serveur (ex. enfant supprimé entre-temps),
  - Quand l'outbox la rejoue,
  - Alors le serveur répond 422, l'entrée passe « rejetée », l'optimiste est annulé et l'utilisateur invité à corriger.
- **Scénario ordre & dépendances**
  - Étant donné deux mutations liées hors-ligne (création d'un échange puis message dans cet échange),
  - Quand l'outbox rejoue,
  - Alors l'ordre d'enfilement est respecté ; si la création parente échoue définitivement, les mutations dépendantes sont mises en échec et signalées (pas d'envoi orphelin).

#### US-OFF-3 — Résolution de conflits (last-write-wins champs simples)
**En tant qu'** utilisateur mobile, **je veux** que mes modifications hors-ligne se résolvent proprement face aux changements survenus côté serveur **afin de** ne pas écraser à l'aveugle ni perdre l'info de l'autre parent.

- **Scénario nominal LWW**
  - Étant donné que j'ai modifié hors-ligne le lieu d'un événement et que personne d'autre ne l'a touché,
  - Quand l'outbox rejoue,
  - Alors le serveur applique la modification (last-write-wins sur champ simple) et renvoie l'état canonique.
- **Scénario conflit champ simple concurrent**
  - Étant donné que l'autre parent a modifié le même champ pendant que j'étais hors-ligne,
  - Quand je rejoue ma modification,
  - Alors selon last-write-wins l'écriture la plus récente (la mienne au moment du rejeu) gagne sur le champ simple, l'état canonique serveur est renvoyé et affiché ; l'app indique que des changements ont été appliqués si l'état canonique diffère de l'optimiste.
- **Scénario entité supprimée côté serveur**
  - Étant donné que j'ai modifié hors-ligne une note dont l'enfant a été supprimé côté serveur,
  - Quand je rejoue,
  - Alors le serveur répond 404/409, l'entrée passe « rejetée » et l'utilisateur est informé que l'élément n'existe plus.

#### US-OFF-4 — Résolution de conflits autorité serveur (garde & décisions)
**En tant qu'** utilisateur mobile, **je veux** que les actions sensibles (échange de garde, exception, validation de décision) soient arbitrées par le serveur **afin de** garantir un état canonique cohérent et signalé en cas de refus.

- **Scénario nominal (validé)**
  - Étant donné que j'ai ajouté hors-ligne une exception de garde non conflictuelle,
  - Quand l'outbox rejoue,
  - Alors le serveur valide, renvoie l'état canonique des `CustodyDay` impactés (priorité exception > vacances > base respectée), et l'app met à jour le calendrier local.
- **Scénario refus (conflit de garde)**
  - Étant donné qu'une exception serveur a déjà été posée sur la même plage par l'autre parent pendant mon hors-ligne,
  - Quand je rejoue ma propre exception conflictuelle,
  - Alors le serveur refuse (409), renvoie l'état canonique, l'entrée passe « rejetée », et l'utilisateur voit « Conflit de garde : l'autre parent a déjà modifié cette période. »
- **Scénario décision déjà tranchée**
  - Étant donné une décision passée à `decided` côté serveur pendant mon hors-ligne,
  - Quand je rejoue mon vote/validation hors-ligne,
  - Alors le serveur refuse (409, statut déjà `decided`), renvoie l'état canonique, et l'app affiche l'option retenue sans appliquer mon vote tardif.
- **Scénario message dans un échange clôturé**
  - Étant donné un échange clôturé côté serveur,
  - Quand je rejoue un message posté hors-ligne,
  - Alors le serveur applique sa règle métier (refus ou acceptation selon §2.6/§6.2) de façon canonique, et l'app reflète la décision serveur sans état divergent.
- **Scénario temps réel après resynchro**
  - Étant donné que je reviens en ligne avec des mutations rejouées avec succès,
  - Quand le serveur publie sur Mercure (§4.5),
  - Alors les autres membres voient les mises à jour en direct, et mon app converge vers le même état canonique.

#### US-OFF-5 — Visibilité de l'état de synchronisation
**En tant qu'** utilisateur mobile, **je veux** voir l'état de synchronisation de mes actions **afin de** savoir ce qui est enregistré, en attente ou en échec.

- **Scénario nominal**
  - Étant donné des mutations en file,
  - Quand je consulte l'app,
  - Alors un indicateur global (compteur « N en attente ») et des badges par entité (« en attente », « synchronisé », « rejeté ») sont affichés.
- **Scénario relance manuelle**
  - Étant donné une mutation en statut « rejetée » récupérable,
  - Quand je demande une relance,
  - Alors la mutation repasse en file et est rejouée ; si elle échoue à nouveau, l'utilisateur peut l'abandonner (rollback de l'optimiste).
- **Scénario purge des entrées finalisées**
  - Étant donné des entrées synchronisées avec succès,
  - Quand le rejeu se termine,
  - Alors elles sont retirées de l'outbox pour ne pas grossir indéfiniment la base locale.

---

### 2.3 RGPD Manager — Export & suppression (utilisateur)

#### US-SET-4 — Exporter mes données (droit d'accès / portabilité)
**En tant qu'** utilisateur, **je veux** déclencher un export complet de mes données **afin d'** exercer mon droit d'accès et de portabilité (RGPD).

- **Scénario nominal**
  - Étant donné que je suis authentifié,
  - Quand j'appelle `POST /api/me/export`,
  - Alors une `RgpdOperation` de type `export` est créée (statut `pending`), un job Messenger asynchrone assemble mes données (JSON + pièces), produit une archive, et me fournit un **lien temporaire signé** (à expiration courte) pour la télécharger ; l'opération est journalisée dans `AuditLog`.
- **Scénario contenu de l'export**
  - Étant donné mes données réparties sur plusieurs familles,
  - Quand l'export est généré,
  - Alors il contient mon compte (`full_name`, `phone` déchiffrés pour MOI seulement), mes memberships et droits, mes notes/messages dont je suis auteur, mes préférences de notification, et les données partagées me concernant selon les règles du registre — sans exposer les données personnelles des autres membres au-delà de ce qui est légalement justifié.
- **Scénario lien expiré**
  - Étant donné un lien d'export généré il y a plus que la durée de validité,
  - Quand je tente de télécharger,
  - Alors le lien est refusé (403/410), et je dois relancer un export.
- **Scénario autorisation (autre utilisateur)**
  - Étant donné le lien signé d'un autre utilisateur,
  - Quand un tiers tente de l'utiliser,
  - Alors la signature/scope du lien empêche l'accès (403) ; un export ne révèle jamais les données d'autrui.
- **Scénario rate-limiting**
  - Étant donné des appels répétés à `/api/me/export`,
  - Quand le seuil est dépassé,
  - Alors les appels supplémentaires sont throttlés (429), pour éviter l'abus de génération d'archives.

#### US-SET-4b — Supprimer mon compte (droit à l'effacement)
**En tant qu'** utilisateur, **je veux** supprimer mon compte **afin d'** exercer mon droit à l'effacement, sans casser la cohérence des familles et des fils.

- **Scénario nominal (anonymisation préservant l'intégrité)**
  - Étant donné que je suis authentifié et que je ne suis pas le dernier admin d'une famille,
  - Quand j'appelle `DELETE /api/me`,
  - Alors une `RgpdOperation` de type `erasure` est créée, l'Anonymiseur neutralise mes champs personnels (`full_name`, `phone`, e-mail), mes messages et notes deviennent « auteur anonymisé » (conservés pour ne pas casser les fils), mes memberships sont retirés/anonymisés selon les règles, le compte passe `status` supprimé/anonymisé, l'action est journalisée dans `AuditLog`, et je suis déconnecté.
- **Scénario dernier admin protégé**
  - Étant donné que je suis le **dernier admin** d'une famille,
  - Quand j'appelle `DELETE /api/me`,
  - Alors la suppression est refusée tant que je n'ai pas désigné un autre admin (409 RFC 7807, « Vous êtes le dernier administrateur de la famille X — désignez un autre administrateur avant de supprimer votre compte. »). Le garde-fou du dernier admin (CLAUDE.md) est inviolable, même par le RGPD.
- **Scénario données partagées**
  - Étant donné des événements, exceptions de garde ou décisions que j'ai créés et qui concernent la famille,
  - Quand mon compte est anonymisé,
  - Alors les données partagées indispensables à la cohérence familiale sont conservées avec auteur anonymisé (pas d'effacement destructeur), conformément à cdc §7.
- **Scénario idempotence / double appel**
  - Étant donné un compte déjà anonymisé,
  - Quand `DELETE /api/me` est rappelé (ou rejoué),
  - Alors l'opération est idempotente (aucun double effet, statut renvoyé cohérent).
- **Scénario réversibilité interdite**
  - Étant donné un compte anonymisé,
  - Quand quelqu'un tente de récupérer les champs personnels,
  - Alors c'est impossible (les données chiffrées sont écrasées/neutralisées de façon irréversible).

---

### 2.4 RGPD Manager — Administration technique

> Les routes `/api/admin/rgpd/*` sont réservées à un **rôle d'administration technique distinct des rôles familiaux** et exigent une **authentification renforcée** (cdc §6.2). Aucun rôle familial (parent/admin de famille, beau-parent, enfant) n'y a accès.

#### US-RGPD-1 — Consulter le registre des traitements
**En tant qu'** administrateur technique, **je veux** consulter le registre des données personnelles **afin de** documenter et auditer la conformité.

- **Scénario nominal**
  - Étant donné mon authentification renforcée d'admin technique,
  - Quand j'appelle `GET /api/admin/rgpd/register`,
  - Alors je reçois le registre synthétisé (entité/champ, catégorie, sensibilité dont 🔒, base légale, finalité, durée de conservation, destinataires), aligné sur les attributs déclaratifs des entités et sur `registre-donnees.md`.
- **Scénario autorisation refusée (rôle familial)**
  - Étant donné un parent/admin de famille (rôle familial uniquement),
  - Quand il appelle `GET /api/admin/rgpd/register`,
  - Alors l'accès est refusé (403 RFC 7807).
- **Scénario auth non renforcée**
  - Étant donné un admin technique authentifié mais sans le facteur renforcé requis,
  - Quand il appelle l'endpoint,
  - Alors l'accès est refusé (401/403) tant que l'authentification renforcée n'est pas satisfaite.
- **Scénario incohérence détectée**
  - Étant donné un champ personnel sans entrée de registre,
  - Quand le registre est généré,
  - Alors l'écart est signalé dans le rapport (et le test CI de cohérence échoue — voir §3.6).

#### US-RGPD-2 — Consulter le journal des opérations RGPD
**En tant qu'** administrateur technique, **je veux** consulter le journal des opérations RGPD **afin de** prouver l'exécution des exports, suppressions, anonymisations et purges.

- **Scénario nominal**
  - Étant donné des opérations enregistrées,
  - Quand j'appelle `GET /api/admin/rgpd/operations`,
  - Alors je reçois la liste paginée des `RgpdOperation` (type, sujet, demandeur, statut, mode live/dry-run, horodatage, référence de résultat), corrélées à l'`AuditLog`.
- **Scénario filtrage**
  - Étant donné un grand nombre d'opérations,
  - Quand je filtre par type/statut/période,
  - Alors la liste est filtrée et paginée (API Platform).
- **Scénario immuabilité**
  - Étant donné le caractère append-only de l'`AuditLog`,
  - Quand je consulte le journal,
  - Alors aucune opération de modification/suppression d'entrée n'est exposée (lecture seule).
- **Scénario autorisation refusée**
  - Étant donné un rôle familial,
  - Quand il appelle l'endpoint,
  - Alors 403.

#### US-RGPD-3 — Déclencher une purge planifiée (dry-run par défaut)
**En tant qu'** administrateur technique, **je veux** déclencher une purge selon les règles de rétention, en dry-run par défaut **afin de** vérifier l'impact avant toute suppression réelle.

- **Scénario dry-run (défaut)**
  - Étant donné des données expirées selon les `RetentionRule`,
  - Quand j'appelle `POST /api/admin/rgpd/purge?dryRun=true` (ou sans paramètre, dry-run par défaut),
  - Alors le système calcule et renvoie ce qui SERAIT supprimé/anonymisé (comptes inactifs, données expirées, invitations périmées, exports temporaires, logs au-delà de la rétention), **sans rien modifier**, journalise une `RgpdOperation` mode `dry_run`.
- **Scénario live**
  - Étant donné un dry-run validé,
  - Quand j'appelle `POST /api/admin/rgpd/purge?dryRun=false`,
  - Alors la purge s'exécute réellement (suppression/anonymisation selon règles), de façon idempotente et journalisée (`RgpdOperation` mode `live` + `AuditLog`).
- **Scénario idempotence**
  - Étant donné une purge déjà passée,
  - Quand elle est relancée sur le même périmètre,
  - Alors aucune donnée déjà purgée n'est re-traitée et aucune erreur n'est levée (résultat « 0 élément à purger »).
- **Scénario purge planifiée automatique**
  - Étant donné le Scheduler (cdc §4.2),
  - Quand l'échéance planifiée arrive,
  - Alors la purge s'exécute automatiquement selon les règles, journalisée comme une opération `purge`.
- **Scénario autorisation refusée**
  - Étant donné un rôle familial,
  - Quand il appelle l'endpoint,
  - Alors 403.
- **Scénario protection du dernier admin lors de la purge de comptes inactifs**
  - Étant donné un compte inactif qui est le dernier admin d'une famille,
  - Quand la purge tente de l'anonymiser,
  - Alors il est exclu/signalé comme écart (la purge ne casse pas l'invariant du dernier admin).

#### US-RGPD-4 — Générer un rapport d'état de conformité
**En tant qu'** administrateur technique, **je veux** un rapport d'état de conformité **afin de** connaître les rétentions en vigueur, les prochaines purges et les écarts.

- **Scénario nominal**
  - Étant donné les `RetentionRule` configurées,
  - Quand j'appelle `GET /api/admin/rgpd/compliance-report`,
  - Alors je reçois : rétentions en vigueur, prochaines purges planifiées, écarts détectés (ex. champ personnel sans entrée de registre, données au-delà de la rétention non purgées), hébergement UE confirmé.
- **Scénario export PDF/Markdown du registre**
  - Étant donné le `ComplianceReporter`,
  - Quand je demande l'export du registre des traitements,
  - Alors il est généré au format PDF/Markdown, identique à `registre-donnees.md`.
- **Scénario autorisation refusée**
  - Étant donné un rôle familial,
  - Quand il appelle l'endpoint,
  - Alors 403.

#### US-RGPD-5 — Branchement systématique de l'AuditLog
**En tant qu'** responsable de la conformité, **je veux** que toutes les actions sensibles soient journalisées de façon immuable **afin de** garantir la traçabilité (RGPD).

- **Scénario nominal**
  - Étant donné une action sensible (connexion, changement de droits/rôles, invitation, modification de garde, export/suppression RGPD, accès aux notes — cdc §5.4),
  - Quand elle se produit,
  - Alors une entrée `AuditLog` append-only est écrite (`actor_user_id`, `family_id`, `action`, `entity_type`, `entity_id`, `ip`, `metadata`, `occurred_at`).
- **Scénario immuabilité**
  - Étant donné une entrée `AuditLog`,
  - Quand un acteur (même admin technique) tente de la modifier/supprimer,
  - Alors c'est impossible (append-only, aucune route d'écriture exposée, contrainte applicative et idéalement DB).
- **Scénario rétention des logs**
  - Étant donné la politique de rétention des logs,
  - Quand la purge s'exécute,
  - Alors les logs au-delà de la rétention sont purgés selon `RetentionRule` (journalisé comme purge), sans toucher aux logs encore dans la période.

#### US-RGPD-6 — Vérification du chiffrement au repos des champs 🔒
**En tant qu'** responsable de la sécurité, **je veux** que les champs 🔒 soient effectivement chiffrés en base **afin qu'** un dump SQL brut ne révèle ni identités ni contenus.

- **Scénario nominal**
  - Étant donné les champs 🔒 (`User.full_name`, `User.phone`, `Message.text`, `Note.text`, `Invitation.email`),
  - Quand ils sont persistés,
  - Alors ils sont chiffrés en AES-256-GCM (enveloppe de clé via coffre/KMS UE), et un `SELECT` brut renvoie du ciphertext, pas le clair.
- **Scénario clé absente**
  - Étant donné une clé de chiffrement indisponible/erronée,
  - Quand l'app tente de déchiffrer,
  - Alors l'opération échoue proprement (erreur contrôlée, pas d'exposition partielle), et aucun clair n'est servi.
- **Scénario recherche sur champ chiffré**
  - Étant donné un e-mail d'invitation 🔒,
  - Quand une recherche/unicité est nécessaire,
  - Alors elle s'appuie sur un mécanisme compatible (ex. `token_hash` pour l'unicité, hash déterministe dédié si lookup requis) sans stocker l'e-mail en clair.

---

## 3. Algorithme(s)

### 3.1 Outbox mobile — mise en file, rejeu, idempotence

**Modèle local (Drift) — table `outbox_entry`** : `id` (UUID local), `family_id`, `method`, `endpoint`, `payload` (JSON), `idempotency_key` (UUID, généré à la création), `status` (`pending` | `in_flight` | `synced` | `rejected`), `created_at`, `attempts`, `last_error`, `depends_on` (id d'une autre entrée, nullable), `entity_local_ref` (pour rattacher l'optimiste).

**Mise en file (mutation hors-ligne)**
```
fonction enqueueMutation(method, endpoint, payload, familyId, dependsOn?):
  1. idempotencyKey = uuidV4()
  2. appliquer l'effet OPTIMISTE dans le cache Drift (créer/màj l'entité avec un flag pending)
  3. insérer outbox_entry{status: pending, idempotencyKey, depends_on: dependsOn, ...}
  4. mettre à jour l'UI (badge « en attente de synchro », compteur global +1)
```
Note : si l'appareil est en ligne, le même chemin est utilisé mais le rejeu est déclenché immédiatement (l'outbox est l'unique chemin de mutation côté mobile → cohérence garantie online/offline).

**Rejeu à la reconnexion**
```
fonction flushOutbox():            # déclenché par: retour réseau, ouverture app, timer, action manuelle
  1. si pas de réseau → return
  2. entries = SELECT * FROM outbox_entry WHERE status IN (pending) ORDER BY created_at ASC
  3. pour chaque e dans entries (séquentiel, FIFO):
       a. si e.depends_on existe et son entrée n'est pas 'synced':
            si dépendance 'rejected' → marquer e 'rejected' (orpheline), notifier, continuer
            sinon → suspendre e (laisser pending), continuer
       b. marquer e 'in_flight', attempts += 1
       c. envoyer requête HTTP avec headers:
            Authorization: Bearer <jwt frais>
            X-Family-Id: e.family_id
            Idempotency-Key: e.idempotency_key
            Accept-Language: locale utilisateur
       d. selon réponse:
            2xx        → remplacer l'optimiste par l'état canonique du body ; status 'synced' ; UI badge retiré
            409 conflit→ résolution de conflit (voir 3.2) ; status 'rejected' ; rollback optimiste ; notifier
            403/401    → status 'rejected' ; rollback ; notifier (droits/auth)
            422 valid. → status 'rejected' ; rollback ; notifier (corriger)
            404/410    → status 'rejected' ; rollback ; notifier (élément disparu)
            5xx/timeout→ status 'pending' (retry ultérieur, backoff exponentiel plafonné) ; pas de rollback
  4. purge des entrées 'synced' (et 'rejected' acquittées par l'utilisateur)
```
- **Idempotence** : la `Idempotency-Key` est immuable pour la durée de vie de l'entrée. Un rejeu (timeout puis retry) renvoie la même clé → le serveur dédoublonne.
- **Complexité** : O(n) par flush sur le nombre d'entrées en file, traitées en FIFO. Backoff exponentiel plafonné sur les 5xx/timeout pour borner la charge.
- **Atomicité locale** : chaque transition d'`outbox_entry` + effet sur le cache Drift se fait dans une transaction Drift, pour que crash/redémarrage laisse la file cohérente.

**Idempotence côté serveur** (`Idempotency-Key`, cdc §6.1)
```
middleware idempotency(request):
  si request.method == POST et header Idempotency-Key présent:
     key = (idempotencyKey, userId, X-Family-Id, endpoint)   # scope strict
     si store contient key:
        si statut 'completed' → renvoyer la réponse mémorisée (même code + body)
        si statut 'in_progress' → 409 (rejouer plus tard) OU attente courte
     sinon:
        enregistrer key 'in_progress' (TTL borné, ex. 24-48h)
        exécuter le handler dans une transaction
        mémoriser (code, body) 'completed'
        renvoyer la réponse
```
- Le store d'idempotence a une **rétention bornée** (TTL) et est lui-même purgé par le `PurgeScheduler`.
- La clé est **scopée à l'utilisateur et à la famille** : aucune collision entre utilisateurs.

### 3.2 Résolution de conflits (mobile)

Principe (cdc §4.4) : **autorité serveur**. Trois familles de traitement.

**A. Champs simples — last-write-wins**
```
- Le client envoie la mutation telle quelle (PATCH avec les champs modifiés).
- Le serveur applique l'écriture (la plus récente gagne au moment du traitement), met à jour updated_at.
- Réponse 2xx + état canonique → le client remplace son optimiste.
- Si l'entité a disparu (404) ou n'existe plus de façon cohérente → rejected.
```

**B. Garde (exceptions/échanges de jour) — validation serveur + état canonique**
```
1. Le serveur revalide la demande contre l'état courant:
   - recalcule la priorité exception > règle vacances > base (invariant CLAUDE.md)
   - vérifie qu'aucune exception serveur concurrente ne contredit la demande
2. Si cohérent → applique, régénère/ajuste les CustodyDay impactés, renvoie l'état canonique (2xx).
3. Si conflit (plage déjà modifiée par l'autre parent) → 409 + état canonique ; client: rejected + message « conflit de garde ».
```

**C. Décisions (échanges) — validation serveur + état canonique**
```
1. Le serveur vérifie le statut de l'Exchange:
   - si déjà 'decided' → 409 (décision tranchée), renvoie l'option retenue ; client n'applique pas le vote tardif.
   - si 'open' → enregistre le vote/validation, recalcule le statut, renvoie l'état canonique.
2. Message dans un échange clôturé → règle métier serveur appliquée de façon canonique.
```
- **Ordre de priorité des décisions de conflit** : règle métier serveur (garde, statut décision) > last-write-wins champ simple. Jamais l'inverse.
- Après résolution, si succès, le serveur **publie sur Mercure** (§4.5) pour propager aux autres membres en temps réel ; l'app émettrice converge sur la réponse HTTP, les autres sur le push.

### 3.3 Anonymiseur (préservation de l'intégrité des fils)

Déclencheurs : `DELETE /api/me` (effacement) et expiration d'une `RetentionRule` action `anonymize`.
```
fonction anonymizeUser(userId, mode):           # mode: live | dry_run
  1. charger les RetentionRule/AnonymizationRule applicables (config déclarative).
  2. vérifier garde-fous AVANT toute écriture:
       - si l'utilisateur est dernier admin d'une famille → ABANDON (409), exiger remplacement.
  3. construire le plan champ-par-champ (depuis le registre + règles), pour chaque champ personnel:
       action ∈ { delete, pseudonymize, replace_marker }
       ex. User.full_name → replace_marker(« Membre supprimé »)
           User.email     → pseudonymize(hash non réversible) / delete selon règle
           User.phone     → delete
           Message.text   → CONSERVÉ, auteur ré-attribué « auteur anonymisé » (le texte du fil reste pour la cohérence)
           Note.text      → idem : note conservée, auteur anonymisé
  4. si mode == dry_run → retourner le plan (entités/champs impactés, comptes), NE RIEN écrire.
  5. si mode == live → dans UNE transaction:
       - appliquer le plan (neutraliser/écraser les champs chiffrés de façon irréversible)
       - réattribuer les messages/notes à un membership « anonyme » stable de la famille (préserve les fils)
       - marquer le User status anonymisé/supprimé, invalider sessions/refresh tokens
       - créer RgpdOperation(type=erasure|anonymize, status=done) + AuditLog
  6. idempotent : un utilisateur déjà anonymisé → no-op (déjà neutralisé).
```
- **Invariant clé** : on ne supprime jamais le contenu partagé d'un fil (message/note) ; on remplace l'**auteur** par un marqueur stable (cdc §7, §7.1.3). Les données partagées indispensables à la cohérence familiale (garde, événements créés) sont conservées avec auteur anonymisé.
- **Irréversibilité** : la neutralisation écrase le ciphertext (pas seulement la clé), de sorte qu'aucune récupération n'est possible.

### 3.4 Purge planifiée (idempotente, dry-run)

```
fonction runPurge(mode):                          # mode: dry_run (défaut) | live
  candidates = {}
  pour chaque RetentionRule enabled:
     selon rule.scope:
        comptes inactifs > durée        → users avec last_login_at + durée < now()  (exclure dernier admin)
        données expirées (catégorie)     → entités dont la date d'ancrage + durée < now()
        invitations périmées             → Invitation.status='pending' et expires_at < now()
        exports temporaires expirés      → artefacts d'export au-delà du TTL du lien signé
        logs au-delà de la rétention     → AuditLog.occurred_at + durée < now()
        store idempotence expiré         → clés au-delà du TTL
     candidates[rule] = liste des cibles + action (delete|anonymize)
  si mode == dry_run:
     RgpdOperation(type=purge, mode=dry_run) ; AuditLog ; retourner le rapport (comptes par règle), NE RIEN supprimer.
  sinon (live):
     pour chaque cible (par lots, transactionnel):
        si action == anonymize → anonymizeUser/anonymizeEntity (3.3)
        si action == delete    → suppression (cascade contrôlée, jamais le dernier admin)
     RgpdOperation(type=purge, mode=live, comptes traités) ; AuditLog.
```
- **Idempotence** : la purge ne sélectionne que des cibles « encore expirées et non traitées » ; relancer ne re-traite rien (résultat 0). Les bornes temporelles sont calculées par rapport à `now()` (fuseau Europe/Paris), jamais une date codée en dur.
- **Bornes Europe/Paris** : les durées s'évaluent en jours civils dans le fuseau famille/par défaut, en tenant compte des changements d'heure (voir §4).
- **Garde-fous** : le dernier admin n'est jamais purgé ; signalé comme écart dans le rapport de conformité.
- **Ordre** : dry-run conseillé avant chaque live ; le Scheduler exécute la purge planifiée automatiquement (live) selon configuration, toujours journalisée.

### 3.5 Export RGPD (lien signé temporaire)

```
fonction buildExport(userId):                     # job Messenger async
  1. RgpdOperation(type=export, status=pending).
  2. collecter, selon le registre, toutes les données du sujet:
       - profil (champs 🔒 DÉCHIFFRÉS pour le sujet uniquement)
       - memberships, droits, préférences notif
       - notes/messages dont il est auteur (texte déchiffré)
       - références aux données partagées le concernant (sans exposer le clair d'autrui)
  3. sérialiser en JSON + joindre pièces (avatar…), produire une archive.
  4. stocker l'archive sur le stockage objet UE (S3-compatible) avec un objet à TTL court.
  5. générer un LIEN SIGNÉ TEMPORAIRE (URL signée, expiration courte) scopé au sujet.
  6. RgpdOperation.status = done, result_ref = lien ; AuditLog ; (option Lot 7) e-mail localisé « votre export est prêt ».
```
- **Sécurité du lien** : signé (HMAC/clé), expiration courte, usage scopé au sujet ; vérifié à chaque téléchargement (expiré → 410/403). L'artefact et le lien sont purgés par le `PurgeScheduler` (exports temporaires).
- **Asynchrone** : l'API répond immédiatement (202/opération créée) ; le client est notifié quand prêt.

### 3.6 Test de cohérence du registre (CI)

```
test registreCoherent():
  1. par réflexion, scanner toutes les entités Doctrine et leurs propriétés.
  2. collecter les champs marqués personnels (attribut déclaratif #[PersonalData(...)] / 🔒).
  3. charger le registre déclaré (attributs) et le document registre-donnees.md.
  4. POUR CHAQUE champ personnel:
        s'il n'a PAS d'entrée de registre complète (catégorie, base légale, finalité, durée, destinataires)
        → AJOUTER au set d'écarts.
  5. vérifier la réciproque: entrée de registre pointant un champ inexistant → écart.
  6. vérifier que registre-donnees.md est synchronisé avec les attributs (régénérable sans diff).
  7. si écarts non vide → ÉCHEC CI (message listant les champs en défaut).
```
- Ce test fait partie de la **DoD de chaque lot** (CLAUDE.md) ; il est rendu effectif au Lot 8 et conditionne tout ajout futur de champ personnel.
- Réciprocité : registre ⇔ attributs ⇔ `registre-donnees.md` doivent coïncider exactement.

### 3.7 Cache PWA (service worker)

```
install:   pré-cacher le SHELL (HTML d'enveloppe, CSS/JS AssetMapper versionnés, icônes manifest, page hors-ligne).
activate:  clients.claim ; purger les caches d'anciennes versions (clé = version d'assets).
fetch(req):
  - navigation GET d'un écran déjà visité → stratégie « network-first, fallback cache » :
        tenter le réseau ; si OK → mettre à jour le cache « écrans récents » (borné en taille/âge) ; si KO → servir le cache ; si absent → page hors-ligne.
  - assets statiques versionnés → « cache-first » (immutables par hash).
  - requête de MUTATION (POST/PATCH/PUT/DELETE) hors-ligne →
        NE PAS mettre en file ; renvoyer une réponse synthétique « hors-ligne » interceptée par la couche Stimulus/Turbo → message clair (US-PWA-3). PAS d'outbox web.
  - requête API GET scopée famille → cache cloisonné par X-Family-Id (jamais de mélange de scopes).
online/offline events: basculer la bannière « Hors-ligne » / « Connexion rétablie » (Stimulus).
```
- **Borne du cache « écrans récents »** : LRU plafonnée (nombre d'entrées + âge max) pour ne pas saturer le stockage.
- **Cohérence Turbo** : les fragments Turbo Streams (temps réel Mercure) ne sont pertinents qu'en ligne ; hors-ligne, le SW ne fabrique pas de faux flux.

---

## 4. Cas limites (edge cases)

### 4.1 Données aux bornes (dates, fuseau, heure d'été/hiver, bissextiles)
- **Fuseau Europe/Paris** : toutes les bornes de rétention/purge et les TTL de liens d'export se calculent dans le fuseau famille (défaut Europe/Paris), jamais en UTC naïf ni avec une date codée en dur.
- **Changement d'heure été→hiver / hiver→été** : une durée de rétention exprimée en jours civils reste correcte malgré le décalage horaire ; un export/lien à TTL en heures tient compte du saut DST (pas de fenêtre raccourcie/allongée silencieuse).
- **Année bissextile** : une rétention « N ans » tombant un 29 février se calcule sans erreur (pas de débordement de date).
- **Horodatage outbox** : `created_at` local mobile peut différer de l'heure serveur ; l'ordre de rejeu reste l'ordre d'enfilement local (FIFO), l'autorité de l'état reste serveur.
- **Mutation hors-ligne datée dans le passé/futur** : une date saisie hors-ligne est revalidée par le serveur au rejeu (pas d'acceptation aveugle).

### 4.2 Concurrence & idempotence
- **Double rejeu de la même entrée outbox** (timeout puis retry) : même `Idempotency-Key` → le serveur dédoublonne, pas de doublon créé.
- **Deux appareils du même utilisateur** rejouant des outbox distinctes : chaque clé est unique ; le serveur applique l'autorité serveur ; l'état converge via Mercure.
- **Idempotency-Key réutilisée pour un payload différent** : le serveur détecte l'incohérence (clé déjà vue avec un autre corps) → 409/422 (ne pas servir une réponse mémorisée erronée).
- **Purge concurrente** (manuelle + planifiée en même temps) : un verrou/sélection idempotente évite le double traitement ; la seconde voit « 0 à purger ».
- **Export concurrent** : plusieurs `/api/me/export` du même utilisateur → rate-limit + opérations indépendantes ; chaque lien est scopé/expirant.
- **Anonymisation pendant un export en cours** : l'export en vol pour ce sujet est invalidé/abandonné si le compte est anonymisé (pas de fuite de clair post-anonymisation).

### 4.3 Autorisations (parent / beau-parent / enfant / admin / admin technique / dernier admin)
- **Rôle familial vs admin technique** : aucun rôle familial (parent, admin de famille, beau-parent, enfant) n'accède aux `/api/admin/rgpd/*` (rôle technique distinct + auth renforcée).
- **Enfant** : aucun accès aux dépenses (forcé `none`), pas d'édition de structure ; en offline, une mutation interdite est refusée au rejeu (403) comme en ligne.
- **Beau-parent** : ses droits granulaires s'appliquent identiquement online et au rejeu outbox ; un droit retiré pendant l'offline ⇒ rejet au rejeu.
- **Dernier admin protégé** : `DELETE /api/me` et la purge refusent d'anonymiser/supprimer le dernier admin d'une famille (409 / exclusion + écart de conformité). Inviolable.
- **Export d'autrui** : impossible ; un lien signé est scopé au sujet ; un admin technique n'exporte pas « à la place » d'un utilisateur via `/api/me/export` (endpoint du sujet authentifié).

### 4.4 Scope famille (X-Family-Id)
- **Cache PWA / Drift cloisonné par famille** : jamais de fuite inter-familles, online comme offline.
- **Outbox sans X-Family-Id valide** : si la famille n'est plus accessible (membership retiré) au rejeu, la mutation est rejetée (403).
- **Idempotency-Key scopée (user, famille, endpoint)** : pas de collision entre familles.
- **Famille liée (overlay)** : les données overlay restent RO et limitées à l'agenda ; ni l'export ni l'anonymisation ne fuient des données de la famille liée hors de ce périmètre RO.

### 4.5 Valeurs vides / limites
- **Outbox vide** : `flushOutbox` no-op.
- **Export d'un compte sans données** : produit une archive valide (structure vide cohérente), pas d'erreur.
- **Champ 🔒 vide/null** : non chiffré (rien à chiffrer) ; le registre le documente quand même comme champ personnel potentiel.
- **Cache PWA plein** : éviction LRU ; aucun crash ; les écrans les plus anciens deviennent indisponibles hors-ligne.
- **Très grande file outbox** : rejeu par lots, backoff ; l'UI reste réactive (compteur global).
- **Purge sur base vide** : 0 candidat, opération journalisée « rien à purger ».

### 4.6 Échecs externes (e-mail, API vacances, Mercure hors-ligne, stockage)
- **Mercure indisponible** (mobile/web) : pas de temps réel ; l'app dégrade proprement (pas de plantage) ; au retour, resynchro par fetch (mobile) / rechargement Turbo (web). L'outbox ne dépend pas de Mercure.
- **Stockage objet UE indisponible** lors d'un export : l'`RgpdOperation` passe en `failed`, journalisée ; l'utilisateur peut relancer ; aucun lien partiel servi.
- **E-mail (notification export/suppression) en échec** : l'opération RGPD reste valide (l'e-mail est un canal annexe, Lot 7) ; l'échec d'envoi est journalisé et retenté par Messenger.
- **API vacances** : sans objet direct pour ce lot ; la purge ne touche pas aux `SchoolHoliday` officiels en cours d'horizon (uniquement selon règles de rétention explicites).
- **KMS/coffre indisponible** : pas de déchiffrement → opérations sensibles (export, lecture de champ 🔒) échouent proprement (erreur contrôlée), aucune donnée claire servie ; alerte d'écart de conformité possible.

### 4.7 Conflits offline (mobile)
- **Exception de garde concurrente** : 409, état canonique renvoyé, entrée rejetée, message « conflit de garde » (US-OFF-4).
- **Décision déjà tranchée** : vote tardif refusé (409), option retenue affichée.
- **Entité supprimée pendant l'offline** : 404/409 au rejeu → rejected + message.
- **Dépendance parente échouée** : mutations dépendantes (message dans un échange non créé) mises en échec, pas d'envoi orphelin.
- **Reconnexion partielle/instable** : 5xx/timeout → retry avec backoff, pas de rollback prématuré ; pas de double application grâce à l'idempotence.
- **Conflit de champ simple** : last-write-wins, état canonique renvoyé ; l'app signale si l'optimiste différait.

### 4.8 Sécurité / RGPD
- **Dump SQL brut** : aucun champ 🔒 lisible (ciphertext only) — vérifié par test.
- **Lien d'export rejoué après expiration** : refusé (410/403).
- **AuditLog append-only** : aucune route de modification/suppression ; tentative bloquée.
- **Anonymisation irréversible** : ciphertext écrasé, pas seulement la clé.
- **Réutilisation d'un refresh token après suppression de compte** : invalidé (sessions révoquées à l'anonymisation).
- **Idempotency store** : ne mémorise pas de données 🔒 en clair (référence/hash de réponse, pas de payload sensible en clair persistant au-delà du TTL).
- **Pas de données de santé** : aucun champ typé santé ; l'anonymiseur/registre/export ne créent ni ne traitent de catégorie santé ; les infos enfant restent libres (label/valeur). Bandeau anti-santé déjà sur l'édition enfant (Lot 2).
- **Mineurs** : données d'enfant jamais exposées hors famille (et hors overlay agenda RO) ; l'export d'un parent ne révèle pas les champs personnels d'autres adultes au-delà du justifié.

### 4.9 i18n / localisation
- **Messages offline** (PWA et mobile) externalisés ICU, en `fr-FR` ; ajouter une langue n'exige aucun changement de code.
- **Manifest PWA** : `name`/`short_name`/`description` localisés.
- **E-mails RGPD** (si déclenchés) : templates Twig localisés (Lot 7).
- **Rapports RGPD** (registre, conformité) : libellés localisés ; les dates affichées dans le fuseau famille/Europe/Paris.
- **Pluriels/genre** : compteur « N en attente de synchro » via ICU (pluriel correct fr-FR).

---

## 5. Modèle de données touché

> Tous les objets ont `id` (UUID), `created_at`, `updated_at`. Champs 🔒 = chiffrés au repos (AES-256-GCM, enveloppe KMS UE).

### 5.1 Entités créées/finalisées par ce lot (cdc §5.2)

| Entité | Champs | Notes |
|---|---|---|
| **AuditLog** | `actor_user_id`, `family_id`, `action`, `entity_type`, `entity_id`, `ip`, `metadata` (jsonb), `occurred_at` | **Immuable / append-only**. Aucune route d'écriture/modification exposée. Branché sur toutes les actions sensibles (§5.4). Idéalement protection au niveau DB (révocation UPDATE/DELETE applicatif). |
| **RetentionRule** | `scope` (catégorie/traitement/entité), `duration`, `action` (`delete` \| `anonymize`), `enabled` | Pilote purge et anonymiseur. Peut être en **config versionnée** plutôt qu'en table (cdc §5.2/§7.1) — choix à figer en implémentation, cohérent avec le test de cohérence. |
| **RgpdOperation** | `type` (`export` \| `erasure` \| `purge` \| `anonymize` \| `report`), `subject_user_id` (nullable), `requested_by`, `status`, `mode` (`live` \| `dry_run`), `result_ref`, `occurred_at` | Trace applicative des opérations du RGPD Manager, en complément de `AuditLog`. |

### 5.2 Entités modifiées / consommées (lots précédents)

- **User** : champs 🔒 `full_name`, `phone` vérifiés chiffrés ; `email` (citext) sert l'unicité ; `status` étendu pour l'état anonymisé/supprimé ; `last_login_at` consommé par la purge des comptes inactifs ; sessions/refresh invalidés à l'anonymisation.
- **Message** : `text` 🔒 conservé à l'anonymisation, auteur réattribué « auteur anonymisé ».
- **Note** : `text` 🔒 idem.
- **Invitation** : `email` 🔒 ; `token_hash` (usage unique, expirant) ; purge des invitations périmées (`status=pending`, `expires_at` dépassé).
- **NotificationPref** : inclus dans l'export ; purgé/anonymisé avec le compte.
- **Membership / Right / FamilyLink** : retirés/anonymisés selon règles à la suppression ; garde-fou dernier admin appliqué.

### 5.3 Registre des données personnelles (non-table)
- Porté par **attributs déclaratifs** sur les entités (ex. `#[PersonalData(category, sensitivity, legalBasis, purpose, retention, recipients)]`) + le document `/backend/docs/registre-donnees.md`. Pas de table métier (cdc §5.2 note).
- Champs 🔒 marqués comme sensibilité élevée.

### 5.4 Artefacts hors base (mobile)
- **Outbox Drift** (`outbox_entry`) : table SQLite locale au mobile (cf. §3.1). N'existe pas côté serveur.
- **Idempotency store** : table/clé-valeur serveur à TTL borné (purgée par le `PurgeScheduler`).

### 5.5 Contraintes & relations
- `User.email` **unique** (citext). `Invitation.token_hash` unique. `Membership` unique `(user_id, family_id)` (existant Lot 1, respecté à l'anonymisation).
- `AuditLog` : index sur `(family_id, occurred_at)` et `(actor_user_id, occurred_at)` pour les rapports ; aucune FK destructive (les entités référencées peuvent être anonymisées sans casser le log).
- `RgpdOperation.subject_user_id` nullable (purge globale sans sujet).

---

## 6. Endpoints API concernés

Conventions (cdc §6.1) : REST/JSON sous `/api` ; `Authorization: Bearer <jwt>` ; `X-Family-Id` sur les ressources scopées famille ; `Accept-Language` (sinon préférence utilisateur) ; erreurs **RFC 7807** (`application/problem+json`) localisées ; **`Idempotency-Key`** accepté sur les POST issus de l'outbox ; pagination API Platform.

### 6.1 Compte / RGPD utilisateur

| Méthode | Chemin | Droit requis | Description |
|---|---|---|---|
| POST | `/api/me/export` | auth (sujet) | Déclenche un export RGPD asynchrone ; livré par **lien signé temporaire**. Rate-limité. Idempotence possible. |
| DELETE | `/api/me` | auth (sujet) | Effacement/anonymisation du compte en cascade (anonymiseur). Refus si **dernier admin** (409). Idempotent. |
| GET/PATCH | `/api/me` | auth | Profil (champs 🔒 déchiffrés pour le sujet) ; consommé par export. |
| GET/PUT | `/api/me/notification-prefs` | auth | Préférences notif ; incluses à l'export. |

### 6.2 Administration technique RGPD (rôle technique distinct + auth renforcée)

| Méthode | Chemin | Rôle | Description |
|---|---|---|---|
| GET | `/api/admin/rgpd/register` | admin technique | Registre des traitements (rapport) — aligné sur les attributs/`registre-donnees.md`. |
| GET | `/api/admin/rgpd/operations` | admin technique | Journal des opérations RGPD (`RgpdOperation`), paginé/filtrable, lecture seule. |
| POST | `/api/admin/rgpd/purge?dryRun=true` | admin technique | Déclenche une purge ; **dry-run par défaut** ; `dryRun=false` pour live. Idempotent, journalisé. |
| GET | `/api/admin/rgpd/compliance-report` | admin technique | Rapport d'état de conformité (rétentions, prochaines purges, écarts, hébergement UE). |

> Les `/api/admin/rgpd/*` ne sont JAMAIS accessibles aux rôles familiaux (parent/admin de famille, beau-parent, enfant). Auth renforcée requise.

### 6.3 Idempotence sur les POST mutables (outbox)
- Tous les `POST` susceptibles d'être issus de l'outbox mobile (création d'événement, message, exception de garde, vote/décision, note…) acceptent `Idempotency-Key` (cdc §6.1). Le serveur dédoublonne par clé scopée `(user, famille, endpoint)`.

### 6.4 Exemple — refus du dernier admin (RFC 7807)
```http
DELETE /api/me
409  application/problem+json
{ "type":"/errors/last-admin", "title":"Suppression impossible",
  "detail":"Vous êtes le dernier administrateur de la famille « Famille Dupont ». Désignez un autre administrateur avant de supprimer votre compte." }
```

### 6.5 Exemple — conflit de garde au rejeu outbox (RFC 7807)
```http
POST /api/families/{id}/custody/exceptions
Idempotency-Key: 4f1c…
X-Family-Id: {id}
409  application/problem+json
{ "type":"/errors/custody-conflict", "title":"Conflit de garde",
  "detail":"Cette période a déjà été modifiée par l'autre parent.",
  "canonicalState": { "...": "état serveur des jours impactés" } }
```

---

## 7. Invariants & règles métier (pertinents pour ce lot)

1. **Scope famille obligatoire** (`X-Family-Id`) : caches PWA et Drift cloisonnés par famille ; idempotency-key scopée ; aucune requête métier non scopée, online comme offline.
2. **Autorisation par Voters** : les droits par domaine (`custody`, `agenda`, `children_notes`, `expenses`, `exchanges`, `family_settings`) s'appliquent identiquement au rejeu outbox ; un droit retiré ⇒ rejet (403). Le rôle `parent` force `edit` ; l'`enfant` n'a jamais `expenses` ni édition de structure.
3. **Dernier admin protégé** : `DELETE /api/me` et la purge n'anonymisent/suppriment jamais le dernier admin d'une famille (409 / exclusion + écart). Inviolable, y compris par le RGPD.
4. **Priorité de la garde** : exception > règle vacances > base, recalcul des stats sur les jours effectifs — respectée par la validation serveur des conflits de garde (état canonique).
5. **Champs chiffrés au repos** (🔒 : `User.full_name`, `User.phone`, `Message.text`, `Note.text`, `Invitation.email`) : AES-256-GCM ; un dump SQL brut ne révèle ni identités ni contenus ; anonymisation irréversible (ciphertext écrasé).
6. **Registre des données personnelles** : tout champ personnel a une entrée (attribut déclaratif + `registre-donnees.md`) ; **test CI de cohérence** échoue sinon. DoD du lot.
7. **Dépenses = v2, masquées** : `Expense`/`ExpenseShare` non actives en v1 ; le registre/anonymiseur les couvriront par extension au Lot 10 sans rupture.
8. **i18n** : toutes les chaînes (UI offline, manifest, rapports, e-mails) externalisées ICU ; seule `fr-FR` livrée ; ajout de langue sans changement de code.
9. **Dates non codées en dur** : la date de référence des maquettes (23 juin 2026) n'est jamais une constante ; toutes les bornes temporelles s'évaluent par rapport à `now()` dans le fuseau famille (défaut **Europe/Paris**), DST/bissextiles gérés.
10. **Pas de données de santé** : aucun champ typé santé créé/traité ; bandeau anti-santé maintenu (Lot 2).
11. **`/infra/INSTALL.md`** : mis à jour si une dépendance change (composant PWA Symfony, lib chiffrement/KMS, lib Idempotency, etc.) — DoD.
12. **AuditLog immuable** (append-only) : toutes les actions sensibles journalisées ; aucune écriture/suppression exposée.
13. **Offline web dégradé uniquement** : pas d'outbox web ; les mutations hors-ligne sont bloquées et signalées (décision verrouillée cdc §4.3). L'offline riche est exclusivement mobile.
14. **Autorité serveur** pour la résolution de conflits offline ; last-write-wins seulement sur champs simples ; garde/décisions arbitrées par le serveur avec état canonique et refus signalé.
15. **Hébergement UE** : artefacts d'export sur stockage objet UE ; clés jamais en base.

---

## 8. Definition of Done (DoD)

### 8.1 Tests
- **Unitaires (PHPUnit)** : règles de rétention/anonymisation, **Anonymiseur** (préservation des fils, irréversibilité, idempotence, garde-fou dernier admin), **PurgeScheduler** (dry-run vs live, idempotence, bornes Europe/Paris/DST), génération du lien d'export signé, middleware d'idempotence (dédoublonnage, clé scopée, incohérence payload).
- **Fonctionnels / API** : `/api/me/export` (async, lien signé, expiration, refus tiers, rate-limit), `DELETE /api/me` (anonymisation, dernier admin 409, idempotence), `/api/admin/rgpd/*` (auth renforcée, refus rôles familiaux 403, dry-run/live, registre, opérations, conformité), `Idempotency-Key` sur POST mutables, conflits de garde/décision (409 + état canonique).
- **E2E web (Playwright)** : installabilité PWA (manifest + SW enregistré), consultation hors-ligne d'écrans récents en RO + bannière, **blocage explicite des mutations hors-ligne** (message clair), reconnexion, mise à jour de SW. Accessibilité de base + responsive desktop/mobile.
- **Mobile (Flutter)** : tests unitaires (mappers, logique outbox, résolution de conflits), tests de widgets (badges « en attente/synchronisé/rejeté », bannière hors-ligne), **tests d'intégration parcours offline → resynchro outbox** (rejeu ordonné, idempotence, conflit garde/décision refusé, droit retiré rejeté).
- **Test de cohérence du registre** : présent et **vert en CI** ; échoue si un champ personnel n'a pas d'entrée (réciprocité attributs ⇔ `registre-donnees.md`).
- **Test de chiffrement au repos** : un dump SQL d'un champ 🔒 ne contient pas le clair (ciphertext only) pour `full_name`, `phone`, `Message.text`, `Note.text`, `Invitation.email`.

### 8.2 Migrations
- Migrations Doctrine versionnées pour `AuditLog`, `RgpdOperation`, `RetentionRule` (si en base), store d'idempotence, index de rapports, extension du `status` User (anonymisé/supprimé). Réversibles, testées sur base de test isolée.

### 8.3 OpenAPI
- `/api/me/export`, `DELETE /api/me`, `/api/admin/rgpd/{register,operations,purge,compliance-report}` documentés (paramètres `dryRun`, en-tête `Idempotency-Key`, réponses RFC 7807 dont 409 dernier admin / conflit garde). OpenAPI exporté à jour ; client Dart régénéré depuis `/contracts`.

### 8.4 i18n
- Toutes les chaînes du lot externalisées ICU `fr-FR` : bannières/messages offline (web + mobile), manifest PWA, libellés des rapports RGPD, e-mails RGPD éventuels, badges de synchro (pluriels). Aucun texte en dur. Ajout de langue sans code.

### 8.5 Registre des données personnelles
- `/backend/docs/registre-donnees.md` **à jour et régénérable** ; chaque champ personnel/🔒 du périmètre v1 a son entrée (catégorie, sensibilité, base légale, finalité, durée, destinataires dont familles liées/sous-traitants). Test de cohérence vert.

### 8.6 INSTALL.md
- `/infra/INSTALL.md` mis à jour pour toute nouvelle dépendance (composant PWA Symfony / service worker, lib chiffrement applicatif + accès KMS/coffre UE, store d'idempotence, stockage objet S3 UE pour exports, configuration du Scheduler de purge), bare-metal pas à pas, vérification de bon fonctionnement.

### 8.7 Analyse statique & format
- PHPStan (niveau élevé) propre ; `php-cs-fixer` appliqué ; `dart analyze` et `dart format` propres côté Flutter.

### 8.8 Conformité design system (§8)
- PWA : icônes/splash, écran et bannière hors-ligne, prompt d'installation conformes à la palette (terracotta `#C2643D`, teal `#2E7D71`, fonds crème `#E5E0D6`/`#F4F1EA`, encre `#292621`), typographies (`Bricolage Grotesque` titres, `Hanken Grotesk` corps), formes (rayons 14–20px, pastilles 999px). Mobile : badges de synchro et bannière hors-ligne en style iOS « liquid glass », déclinés Material sur Android, palette respectée.

### 8.9 Critères d'acceptation du lot (cdc §9, Lot 8)
- PWA installable ; mobile utilisable hors-ligne et resynchronisé ; export/suppression conformes (anonymisation préservant l'intégrité des fils) ; purge dry-run + live testées ; registre généré et cohérent ; **un dump SQL ne révèle pas les champs sensibles** ; AuditLog branché partout ; endpoints RGPD opérationnels et protégés (auth renforcée).
