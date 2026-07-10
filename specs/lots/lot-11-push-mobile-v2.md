# Lot 11 — Push mobile (v2)

> Spécification détaillée d'implémentation. Source de vérité : `specs/cdc.md` (§2.9, §4.4, §4.5, §5.2, §6, §9 Lot 11) et `CLAUDE.md` (invariants verrouillés). En cas d'ambiguïté de rendu, les maquettes HTML/iOS font foi.
>
> Ce lot est le **dernier de la séquence** (§9). Il est marqué **v2**. Il **n'introduit aucun changement d'architecture** : il étend le pipeline de notifications du **Lot 7** (déclencheurs + `NotificationPref`) avec un nouveau **canal `push`** et la gestion des **tokens d'appareil** (APNs / FCM).

---

## 0. Objectif & périmètre

### 0.1 Objectif

Ajouter le **canal de notification push mobile** (Apple Push Notification service — APNs, et Firebase Cloud Messaging — FCM) au système de notifications existant, afin que les utilisateurs de l'**app mobile Flutter** (iOS/Android) reçoivent en temps quasi réel les notifications déjà câblées en e-mail au Lot 7, **sans avoir l'app ouverte**.

Le lot couvre :
1. **Enregistrement, rafraîchissement et révocation des tokens d'appareil** (multi-appareils par utilisateur, multi-plateforme).
2. **Extension de `NotificationPref`** au canal `push` (déjà prévu dans l'énumération `channel (email|push)` de §5.2) : opt-in **par catégorie ET par canal**.
3. **Routage push** réutilisant **intégralement le pipeline de déclencheurs du Lot 7** (Symfony Messenger), avec un nouveau « notifier » de canal `push`.
4. **Respect de l'opt-in et de la permission OS** : aucune notification push si l'utilisateur n'a pas consenti côté OS et/ou a désactivé la préférence applicative.
5. **Nettoyage des tokens invalides** sur retour d'échec des passerelles (APNs/FCM), de façon idempotente.
6. **Quiet-hours et locale** : un push respecte les mêmes plages silencieuses et la même langue ICU que l'e-mail (Lot 7).

### 0.2 Dans le périmètre

- Entité **`DeviceToken`** (nouvelle, voir §5) : enregistrement d'un jeton d'appareil rattaché à un `User`, avec plateforme et état.
- Endpoints **`POST /api/me/devices`**, **`DELETE /api/me/devices/{id}`** (et `GET` de listage), pour enregistrer/lister/révoquer un appareil.
- Extension de **`GET/PUT /api/me/notification-prefs`** pour porter les toggles du canal `push` (le modèle `NotificationPref` le permet déjà : un enregistrement par `(user, category, channel)`).
- Adaptateur **`PushSender`** isolé (interface) + implémentations **APNs** et **FCM**, encapsulant la communication avec les passerelles (clé/secret en coffre, jamais en base ni en dur).
- Branchement du canal `push` dans le **dispatcher de notifications** du Lot 7 (le même message Messenger fan-out vers les canaux activés).
- **Flutter** : demande de permission OS (opt-in), récupération du token (FCM/APNs via `firebase_messaging`/plugin équivalent), enregistrement/rafraîchissement auprès du backend, gestion de la réception (foreground/background/terminé), **deep-link** vers l'écran concerné (échange, jour de garde, événement…), et écran de réglages reflétant l'état de la permission.
- **Tests** : unitaires (sélection des destinataires/canaux, nettoyage des tokens, anti-doublon, quiet-hours), fonctionnels API (devices, prefs, idempotence), Flutter (permission, enregistrement, deep-link), et fakes de passerelle (pas d'appel réseau réel APNs/FCM en CI).

### 0.3 Hors périmètre

- **Web push** (PWA `Push API`/`Web Push`/VAPID) : **hors lot**. Le cdc réserve le push au **mobile** (§1.3 « Notifications push **mobile** v2 » ; §2.9 « push mobile en v2 »). Le web reste sur l'e-mail (Lot 7) et le temps réel Mercure/Turbo (Lot 6/8). Ne pas l'implémenter sans demande explicite.
- **Nouveaux types d'événements notifiables** : aucun. Le lot n'ajoute pas de déclencheur métier ; il ajoute un **canal**. Les événements restent ceux de §2.9 / Lot 7.
- **Notifications transactionnelles riches** (images, actions interactives natives, catégories APNs personnalisées au-delà du strict nécessaire au deep-link) : non requis. À garder minimal.
- **Dépenses (Lot 10, v2)** : la catégorie `expenses` existe dans `NotificationPref`. Le canal push pour `expenses` est **fonctionnellement défini ici** mais reste **inactif tant que le module dépenses n'est pas activé** pour la famille (cohérent avec « Dépenses = v2, masquées » et l'activation par module du Lot 10). Aucun code spécifique dépenses n'est ajouté ici.

### 0.4 Contexte v1 / v2

Le push mobile fait partie de la **v2** (§1.3). Le canal `email` (v1, Lot 7) reste la voie par défaut. À l'issue de ce lot, un utilisateur mobile peut recevoir **e-mail ET/OU push** selon ses préférences, sans duplication abusive entre canaux (voir §3 anti-doublon).

---

## 1. Dépendances

### 1.1 Lots prérequis (avant)

| Lot | Pourquoi il est requis |
|---|---|
| **Lot 0 — Fondations** | Monorepo, auth JWT, scope `X-Family-Id`, Voters, i18n ICU, Mailer, Messenger, client Flutter généré depuis l'OpenAPI, couche état (Riverpod/Bloc), CI (PHPUnit/Playwright/`flutter test`/PHPStan/`dart analyze`), squelette RGPD Manager + `registre-donnees.md`. Le push s'enregistre via l'auth utilisateur et le client mobile généré. |
| **Lot 7 — Notifications e-mail** | **Dépendance directe et structurante.** Lot 11 **étend** ce lot : il réutilise les **déclencheurs** (changement de garde, événement d'agenda, message/décision dans un échange, invitation), le **dispatcher Messenger**, le **modèle `NotificationPref`** (catégories), la **gestion du quiet-hours** et la **résolution de locale/ICU**. Sans le Lot 7, il n'y a pas de pipeline à étendre. |
| **Lots 1–6 (transitivement via Lot 7)** | Les déclencheurs notifiables proviennent de Familles/membres/invitations (Lot 1), Enfants/notes (Lot 2), Garde (Lot 3), Agenda (Lot 4), Échanges temps réel (Lot 6). Ces événements existent déjà avant le Lot 7 ; le Lot 11 ne les recrée pas. |
| **Lot 9 — CI/CD & build iOS sans Mac** *(dépendance pratique, non fonctionnelle)* | Les notifications push APNs **ne fonctionnent pas sur simulateur iOS** : leur validation exige un build signé installé sur un device réel (TestFlight). Le Lot 9 fournit ce pipeline sans nécessiter de Mac local. Le Lot 11 pourrait théoriquement être livré sans lui, mais **ne serait pas testable sur iOS** en pratique. |

> **Remarque** : le Lot 8 (PWA/offline/RGPD) **n'est pas un prérequis fonctionnel** du push, mais le **RGPD Manager** (Lot 8) doit déjà connaître le registre des données personnelles, car le nouveau champ personnel/technique `DeviceToken.token` y est inscrit (voir §5.4 et §7). Comme la séquence livre le Lot 8 avant le Lot 11, le registre et l'anonymiseur existent déjà ; ce lot **ajoute une entrée** au registre et **une règle d'anonymisation/purge** pour `DeviceToken` (DoD).

### 1.2 Lots débloqués (après)

Aucun. Le **Lot 11 est le dernier lot** de la séquence (§9). Il ne conditionne aucun lot ultérieur. Il **clôt** la matrice de périmètre v2 (§1.3 : « Notifications push mobile » devient ✅).

---

## 2. User stories complètes

> Convention : nouvelles US préfixées **`US-PUSH-*`** (le cdc ne nomme pas d'US dédiées push, §2.9 et Lot 11 décrivant le périmètre). Les US **réutilisées** du cdc (`US-SET-1`) sont rappelées et étendues. Critères d'acceptation en **Étant donné / Quand / Alors**, plusieurs scénarios par US (nominal, erreur, autorisation, temps réel/OS).
>
> Rappel d'autorisation : le canal push relève des **préférences de l'utilisateur** (`/api/me/...`), donc **scopé à l'utilisateur authentifié**, **non** à une famille. Les Voters de domaine (§2.2) **ne gouvernent pas** l'opt-in lui-même ; ils gouvernent **l'éligibilité d'un destinataire à un événement** (déjà appliqué au Lot 7 : on ne notifie un membre que s'il a le droit `view`/`edit` sur le domaine concerné). Voir §7.

### US-PUSH-1 — Enregistrer un appareil pour recevoir des push

**En tant que** utilisateur de l'app mobile, **je veux** enregistrer mon appareil (token APNs/FCM) auprès du serveur **afin de** recevoir des notifications push même app fermée.

**Critères d'acceptation**

- **Scénario nominal — premier enregistrement**
  - *Étant donné* un utilisateur authentifié ayant accordé la permission de notification OS
  - *Quand* l'app envoie `POST /api/me/devices` avec `{ platform: "ios"|"android", token, appVersion?, deviceModel?, locale? }`
  - *Alors* le serveur crée un `DeviceToken` lié à l'utilisateur avec `status=active`, renvoie `201 Created` avec l'`id`, et le token (valeur sensible) n'apparaît jamais en clair dans un dump SQL (chiffré au repos, §5.4).

- **Scénario nominal — ré-enregistrement du même token (idempotence)**
  - *Étant donné* un utilisateur dont l'appareil a déjà un `DeviceToken` actif pour ce token
  - *Quand* l'app renvoie `POST /api/me/devices` avec le même `token`
  - *Alors* le serveur ne crée pas de doublon : il met à jour `last_seen_at` (et `app_version`/`locale` si fournis) et renvoie `200 OK` (ou `201` idempotent) avec l'enregistrement existant ; il n'y a **jamais** deux `DeviceToken` actifs avec le même `token` (contrainte d'unicité §5.3).

- **Scénario — token déjà rattaché à un autre utilisateur (réassignation d'appareil)**
  - *Étant donné* un appareil dont le token était enregistré pour l'utilisateur A (ex. compte précédent sur ce téléphone)
  - *Quand* l'utilisateur B se connecte et envoie le même `token`
  - *Alors* le serveur **révoque** l'ancien enregistrement (status `revoked`/réassignation, voir §3.1) et crée/réassigne le token à B, garantissant qu'un push n'est jamais livré au mauvais compte.

- **Scénario d'erreur — payload invalide**
  - *Étant donné* un utilisateur authentifié
  - *Quand* il envoie un `platform` hors énumération, un `token` vide, ou un body malformé
  - *Alors* le serveur renvoie `422 Unprocessable Entity` (ou `400`) en `application/problem+json` (RFC 7807), message localisé `fr-FR`, sans créer d'enregistrement.

- **Scénario d'autorisation — non authentifié**
  - *Étant donné* une requête sans `Authorization: Bearer` valide
  - *Quand* `POST /api/me/devices` est appelé
  - *Alors* le serveur renvoie `401 Unauthorized` (RFC 7807). Aucun `X-Family-Id` n'est requis (ressource liée à l'utilisateur, pas à une famille).

### US-PUSH-2 — Rafraîchir/mettre à jour le token d'appareil

**En tant que** utilisateur mobile, **je veux** que mon token soit automatiquement mis à jour quand l'OS le fait tourner **afin de** continuer à recevoir mes push sans rien faire.

**Critères d'acceptation**

- **Scénario nominal — rotation de token**
  - *Étant donné* un `DeviceToken` actif et un évènement OS « token rafraîchi » (callback FCM/APNs)
  - *Quand* l'app envoie `POST /api/me/devices` avec le **nouveau** token et un identifiant stable d'installation (`installation_id`) si disponible
  - *Alors* le serveur retrouve l'enregistrement par `installation_id` (ou crée un nouveau), met l'ancien token en `revoked` et active le nouveau ; un seul token actif par installation physique.

- **Scénario nominal — heartbeat / changement de locale**
  - *Étant donné* un appareil enregistré dont l'utilisateur change la langue de l'app
  - *Quand* l'app renvoie `POST /api/me/devices` (ou un `PATCH`) avec une nouvelle `locale`
  - *Alors* `DeviceToken.locale` est mis à jour ; cette locale **secondaire** sert de repli si l'utilisateur n'a pas de locale globale (§3.4).

- **Scénario d'erreur — token expiré au moment de l'envoi**
  - *Étant donné* un token marqué actif mais que la passerelle rejette à l'envoi (`Unregistered`/`410`/`NotRegistered`)
  - *Quand* le `PushSender` reçoit ce retour
  - *Alors* le token passe en `invalid` (puis purgé), sans nouvelle tentative sur ce token (voir §3.5). Aucun appel API utilisateur n'est requis pour ce nettoyage (côté serveur, asynchrone).

### US-PUSH-3 — Révoquer un appareil (logout / appareil perdu)

**En tant que** utilisateur, **je veux** révoquer un appareil **afin de** ne plus recevoir de push dessus (déconnexion, téléphone perdu/volé).

**Critères d'acceptation**

- **Scénario nominal — logout**
  - *Étant donné* un utilisateur connecté sur un appareil enregistré
  - *Quand* il se déconnecte et l'app appelle `DELETE /api/me/devices/{id}` (ou par token)
  - *Alors* le `DeviceToken` passe en `revoked` (ou est supprimé selon §5.4), aucun push futur n'y est plus routé, réponse `204 No Content`.

- **Scénario nominal — révocation à distance**
  - *Étant donné* un utilisateur listant ses appareils via `GET /api/me/devices`
  - *Quand* il révoque un appareil qu'il n'a plus
  - *Alors* l'appareil cible cesse d'être éligible aux envois ; opération journalisée (`AuditLog`, action `device.revoked`).

- **Scénario d'autorisation — appareil d'un autre utilisateur**
  - *Étant donné* un `DeviceToken` appartenant à l'utilisateur A
  - *Quand* l'utilisateur B tente `DELETE /api/me/devices/{idDeA}`
  - *Alors* le serveur renvoie `403 Forbidden` (ou `404` pour ne pas révéler l'existence), sans modifier l'enregistrement. Un utilisateur ne peut agir que sur **ses** appareils.

- **Scénario — suppression de compte (RGPD)**
  - *Étant donné* un utilisateur déclenchant `DELETE /api/me` (effacement RGPD, Lot 8)
  - *Quand* la cascade s'applique
  - *Alors* tous ses `DeviceToken` sont **supprimés** (pas anonymisés : ce sont des identifiants techniques sans valeur de cohérence de fil, §7), et plus aucun push ne lui est adressé.

### US-PUSH-4 — Choisir de recevoir les push par catégorie (opt-in applicatif)

*(Extension de **US-SET-1** du cdc : « éditer mon compte (profil, langue, notifications par catégorie, sécurité) ».)*

**En tant que** utilisateur, **je veux** activer/désactiver les push **par catégorie** (garde, agenda, échanges, invitation, [dépenses si module actif]) indépendamment de l'e-mail **afin de** ne recevoir que ce qui m'importe sur mon téléphone.

**Critères d'acceptation**

- **Scénario nominal — activation par catégorie**
  - *Étant donné* un utilisateur sur l'écran Paramètres › Notifications
  - *Quand* il active le toggle « Push » pour la catégorie `exchanges` via `PUT /api/me/notification-prefs`
  - *Alors* un enregistrement `NotificationPref(user, category=exchanges, channel=push, enabled=true)` est persistant ; les futurs messages d'échange déclenchent un push (si appareil enregistré et permission OS accordée).

- **Scénario nominal — découplage email/push**
  - *Étant donné* un utilisateur ayant `email=on` et `push=off` pour `agenda`
  - *Quand* un événement d'agenda est créé
  - *Alors* il reçoit l'e-mail mais **aucun** push ; les deux canaux sont indépendants.

- **Scénario — défaut à l'activation initiale du canal push**
  - *Étant donné* un utilisateur qui n'a jamais réglé ses préférences push
  - *Quand* le système doit décider d'envoyer un push
  - *Alors* le comportement par défaut suit la règle d'**opt-in explicite** : push **désactivé par défaut** tant que (a) la permission OS n'est pas accordée et (b) aucune préférence push n'a été enregistrée, sauf valeur de seed définie au Lot 7 pour `email`. (Voir §3.3 : on ne pousse jamais sans opt-in OS effectif.)

- **Scénario d'erreur — catégorie inconnue**
  - *Étant donné* un body `PUT` contenant une catégorie hors énumération `custody|agenda|expenses|exchanges|invitation`
  - *Quand* la requête est traitée
  - *Alors* `422` RFC 7807 localisé, aucune préférence modifiée.

- **Scénario — catégorie `expenses` alors que le module est inactif**
  - *Étant donné* une famille où `expenses_enabled=false`
  - *Quand* un utilisateur active le push `expenses`
  - *Alors* la préférence est **enregistrée** (le toggle peut exister) mais **aucun déclencheur** dépense n'existe tant que le module est inactif (Lot 10) ⇒ aucun push `expenses` n'est émis. Optionnellement le toggle est masqué dans l'UI si aucune des familles de l'utilisateur n'a le module actif.

### US-PUSH-5 — Recevoir un push et atterrir au bon endroit (deep-link)

**En tant que** utilisateur mobile, **je veux** taper sur une notification push **afin d'**ouvrir directement l'écran concerné (l'échange, le jour de garde, l'événement…).

**Critères d'acceptation**

- **Scénario nominal — app fermée**
  - *Étant donné* un utilisateur ayant opté-in pour `exchanges`
  - *Quand* un nouveau message arrive dans un échange et l'app est fermée
  - *Alors* l'OS affiche une notification localisée (titre/corps en `fr-FR`) ; au tap, l'app s'ouvre **sur la famille concernée** (bascule du `X-Family-Id` si nécessaire) et **sur le fil d'échange** ciblé (deep-link via `data`/`payload`).

- **Scénario nominal — app au premier plan**
  - *Étant donné* l'app ouverte sur un autre écran
  - *Quand* un push arrive
  - *Alors* selon la plateforme, une bannière in-app discrète peut s'afficher **sans** dupliquer la mise à jour temps réel Mercure déjà reçue (anti-doublon UI, §3.6) ; aucune redirection forcée.

- **Scénario — payload incomplet/obsolète**
  - *Étant donné* un push pointant vers une ressource supprimée entre-temps (échange clôturé/supprimé)
  - *Quand* l'utilisateur tape la notification
  - *Alors* l'app ouvre un écran de repli (liste de la catégorie ou accueil) avec un message clair, sans crash.

- **Scénario — push reçu mais utilisateur sans droit sur la ressource**
  - *Étant donné* qu'entre l'émission et l'ouverture, les droits du membre ont changé (ex. retrait du droit `exchanges`)
  - *Quand* l'app tente d'ouvrir la ressource
  - *Alors* l'API renvoie `403`/`404` (Voters), l'app affiche un message localisé et redirige vers l'accueil ; aucune donnée n'est exposée.

### US-PUSH-6 — Respect de la permission OS et de l'opt-in

**En tant que** utilisateur, **je veux** que l'app ne m'envoie de push que si j'ai accepté la permission système **afin de** garder le contrôle.

**Critères d'acceptation**

- **Scénario — permission OS refusée**
  - *Étant donné* un utilisateur ayant refusé la permission de notification dans l'OS
  - *Quand* l'app démarre
  - *Alors* aucun token n'est enregistré (ou l'enregistrement est marqué inéligible) ; l'écran Paramètres › Notifications affiche un état « Push désactivés au niveau système » avec un lien vers les réglages OS ; aucune préférence push n'a d'effet.

- **Scénario — révocation de la permission OS après enregistrement**
  - *Étant donné* un appareil précédemment enregistré dont l'utilisateur retire la permission OS
  - *Quand* l'app détecte le changement (au prochain lancement) ou que la passerelle rejette l'envoi
  - *Alors* l'app appelle la révocation (`DELETE /api/me/devices/{id}`) et/ou le serveur invalide le token au premier échec ; l'utilisateur ne reçoit plus de push.

- **Scénario — quiet-hours actifs**
  - *Étant donné* un utilisateur avec une plage silencieuse 22h–7h (Europe/Paris) configurée au Lot 7
  - *Quand* un déclencheur survient à 23h30 heure de Paris
  - *Alors* le push est **supprimé ou différé** selon la politique du Lot 7 (cohérence e-mail/push), et n'est jamais émis pendant la plage silencieuse (sauf catégorie marquée critique — il n'y en a pas en v2). Voir §3.4.

### US-PUSH-7 — Multi-appareils

**En tant que** utilisateur possédant plusieurs appareils (téléphone + tablette), **je veux** recevoir mes push sur tous mes appareils actifs **afin de** ne rien rater.

**Critères d'acceptation**

- **Scénario nominal — fan-out multi-appareils**
  - *Étant donné* un utilisateur avec 2 `DeviceToken` actifs (1 iOS, 1 Android)
  - *Quand* un déclencheur éligible survient
  - *Alors* un push est envoyé à **chacun** des tokens actifs via la passerelle adéquate (APNs pour iOS, FCM pour Android), avec gestion indépendante des échecs par token.

- **Scénario — un token invalide parmi plusieurs**
  - *Étant donné* un utilisateur avec 3 tokens dont 1 invalide
  - *Quand* l'envoi multi-tokens s'exécute
  - *Alors* les 2 valides reçoivent le push, le 3e est marqué `invalid` et nettoyé ; aucun échec global ne bloque les autres.

### US-PUSH-8 — Cohérence entre canaux (anti-doublon)

**En tant que** utilisateur, **je veux** ne pas être spammé par e-mail ET push pour le même événement de façon redondante et incohérente **afin de** garder une expérience propre.

**Critères d'acceptation**

- **Scénario — fan-out cohérent**
  - *Étant donné* un déclencheur unique (ex. une décision validée)
  - *Quand* le dispatcher fan-out vers `email` + `push`
  - *Alors* **un seul** push par appareil et **un seul** e-mail sont émis pour cet événement (clé d'idempotence par `(notification_event_id, channel, target)`, §3.6) ; un retry Messenger ne produit pas de doublon.

- **Scénario — temps réel déjà vu**
  - *Étant donné* l'app au premier plan ayant déjà affiché la mise à jour via Mercure
  - *Quand* le push correspondant arrive
  - *Alors* l'app n'empile pas une seconde notification visible redondante (corrélation par identifiant d'événement dans le payload).

---

## 3. Algorithme(s)

> Tous les traitements serveur tournent dans le pipeline **Messenger** (asynchrone, transport Doctrine/Redis), réutilisant le dispatcher du **Lot 7**. Le push n'est jamais émis dans le cycle requête HTTP métier (latence + résilience). Fuseau **Europe/Paris** par défaut ; aucune date codée en dur.

### 3.1 Enregistrement / réassignation d'un token (`POST /api/me/devices`)

```
ENTREE: user (authentifié), { platform, token, installation_id?, app_version?, device_model?, locale? }
1. Valider platform ∈ {ios, android} ; token non vide ; longueur/format plausible.
   - sinon → 422 RFC 7807 (localisé).
2. Normaliser token (trim ; conserver tel quel sinon).
3. Calculer token_hash = HMAC-SHA256(pepper_KMS, token)   // index déterministe pour lookup sans déchiffrer
4. TRANSACTION (verrou sur l'index token_hash) :
   a. existing = findActiveByTokenHash(token_hash)
   b. SI existing ET existing.user_id == user.id :
         existing.last_seen_at = now(Europe/Paris en UTC)
         maj app_version/device_model/locale si fournis
         → 200 OK (idempotent), pas de doublon
   c. SI existing ET existing.user_id != user.id :       // réassignation d'appareil
         existing.status = 'revoked' ; existing.revoked_at = now
         (journaliser device.reassigned)
         → continuer en (d)
   d. SI installation_id fourni :
         prev = findByInstallation(user.id, installation_id) actif
         SI prev existe ET prev.token_hash != token_hash :
            prev.status = 'revoked'         // rotation de token sur la même installation
   e. Créer DeviceToken { user, platform, token(chiffré 🔒), token_hash,
         installation_id?, app_version?, device_model?, locale?,
         status='active', last_seen_at=now }
   f. → 201 Created
5. Journaliser AuditLog(device.registered).      // pas de token en clair dans metadata
```

- **Idempotence** : garantie par l'unicité `token_hash` parmi les tokens **actifs** (§5.3) + lookup déterministe via HMAC (le champ chiffré GCM n'est pas indexable par égalité, d'où le `token_hash`).
- **Complexité** : O(1) par requête (lookup indexé), verrou sur la clé `token_hash` pour éviter la course de double création concurrente (deux requêtes simultanées du même appareil au boot).
- **Sécurité** : le token est un identifiant sensible (peut servir à pousser du contenu vers l'appareil) ⇒ stocké **chiffré au repos** (AES-256-GCM applicatif, §5.4) ; le `token_hash` (HMAC avec pepper en coffre) sert uniquement à l'unicité/lookup, non réversible.

### 3.2 Révocation (`DELETE /api/me/devices/{id}` ou par token au logout)

```
1. Charger DeviceToken par id.
2. SI device.user_id != currentUser.id → 404 (ne pas révéler) / 403.
3. device.status = 'revoked' ; device.revoked_at = now.
   (option config : suppression dure immédiate — voir §5.4 politique de purge)
4. Journaliser AuditLog(device.revoked).
5. → 204 No Content.
```

### 3.3 Décision d'envoi push pour un déclencheur (extension du dispatcher Lot 7)

Le Lot 7 produit, pour chaque évènement métier notifiable, un **`NotificationEvent`** (identité stable `notification_event_id`, catégorie, famille concernée, ressource, données de rendu i18n). Le dispatcher calcule les **destinataires éligibles** puis fan-out par canal. Le Lot 11 ajoute le bloc « canal push ».

```
ENTREE: NotificationEvent E { id, category, family_id, resource_ref, payload_i18n, occurred_at }
1. destinataires = résoudreDestinatairesEligibles(E)         // logique Lot 7, inchangée
   // = membres de family_id qui (a) ne sont pas l'auteur de l'action,
   //   (b) ont le DROIT (Voter) view/edit sur le domaine de la catégorie,
   //   (c) pour child : jamais expenses, jamais structure (déjà filtré).
2. POUR CHAQUE destinataire U dans destinataires :
   2.1 SI NOT prefEnabled(U, category, channel='push') → SKIP push pour U.
   2.2 SI dansQuietHours(U, E.occurred_at) :
          appliquer politique Lot 7 (différer jusqu'à fin de plage OU supprimer).
          SI supprimer → SKIP.
   2.3 devices = DeviceToken actifs de U (status='active').
   2.4 SI devices vide → SKIP (opt-in OS effectif requis : pas de token = pas de permission).
   2.5 locale = résoudreLocale(U, device)    // §3.4
   2.6 contenu = rendreICU(E.payload_i18n, locale)   // titre court + corps + data{deep-link}
   2.7 POUR CHAQUE device dans devices :
          idemKey = hash(E.id, 'push', device.id)
          SI déjà envoyé(idemKey) → SKIP (anti-doublon, §3.6)
          enfiler PushMessage{ device, contenu, idemKey }   // Messenger
3. (e-mail traité par le bloc Lot 7 en parallèle, fan-out indépendant)
```

- **Ordre de priorité** : l'**autorisation (Voter)** prime sur la préférence ; la **préférence** prime sur l'envoi ; le **quiet-hours** prime sur l'émission. Un destinataire non éligible (droit) n'est jamais évalué pour le push.
- **Complexité** : O(D × K) avec D = destinataires éligibles, K = appareils actifs par destinataire (généralement 1–3). Borné et faible.

### 3.4 Résolution de locale et quiet-hours

```
résoudreLocale(U, device):
   1. SI U.locale défini → U.locale          // préférence compte (source primaire, §4.6 cdc)
   2. SINON SI device.locale défini → device.locale
   3. SINON → locale par défaut 'fr-FR'
   (Le push n'a pas d'en-tête Accept-Language ; la locale vient du compte/appareil.)

dansQuietHours(U, occurred_at):
   1. SI U n'a pas de quiet-hours → false
   2. tz = U.timezone OU famille.timezone OU 'Europe/Paris'
   3. local = occurred_at converti dans tz   // gère heure d'été/hiver via base tz IANA
   4. retourner local.heure ∈ [quiet_start, quiet_end[  (plage pouvant chevaucher minuit)
```

- **Fuseau** : conversion via la base de fuseaux IANA (`Europe/Paris`), donc **transitions été/hiver gérées nativement** (pas d'offset codé en dur). Une plage 22h–7h est évaluée en heure **locale** : à la bascule d'heure, la durée réelle peut varier d'une heure — comportement attendu et accepté.

### 3.5 Envoi vers la passerelle et nettoyage des tokens invalides (`PushSender`)

```
HANDLER PushMessage{ device, contenu, idemKey } :
1. SI estDéjàTraité(idemKey) → ACK (idempotent, no-op).   // §3.6
2. sender = (device.platform == 'ios') ? ApnsSender : FcmSender
3. result = sender.send(device.token_déchiffré, contenu)   // déchiffrement en mémoire uniquement
4. SELON result :
   - SUCCESS:
        marquerEnvoyé(idemKey) ; device.last_success_at = now ; ACK.
   - INVALID_TOKEN  (APNs '410 Unregistered' / 'BadDeviceToken' ;
                     FCM 'UNREGISTERED' / 'INVALID_ARGUMENT' token) :
        device.status = 'invalid' ; device.invalidated_at = now
        journaliser device.invalidated ; ACK (pas de retry).
   - TOKEN_UPDATED (FCM canonical id) :
        traiter comme rotation : révoquer ancien, créer/activer nouveau ; ACK.
   - RATE_LIMIT / TEMP_ERROR (APNs 429/500/503 ; FCM 'UNAVAILABLE'/'INTERNAL') :
        NACK avec backoff exponentiel + jitter (retry Messenger borné, ex. 5 essais)
        au-delà du plafond → DLQ + journaliser ; ne PAS invalider le token.
   - AUTH_ERROR (clé APNs/FCM invalide/expirée) :
        échec opérationnel : NACK, alerte ; ne PAS invalider de tokens utilisateurs.
5. FIN.
```

- **Idempotence d'envoi** : `marquerEnvoyé(idemKey)` est posé **après** succès ; un retry après timeout réseau peut produire un envoi en double rare — acceptable et borné (APNs/FCM dédupliquent partiellement via `apns-collapse-id`/`collapse_key` que l'on positionne sur `E.id`). On privilégie « au moins une fois » sans bombarder.
- **Nettoyage** : seuls les retours **définitifs** (`INVALID_TOKEN`) invalident le token. Les erreurs temporaires ne suppriment **jamais** un token (sinon perte de joignabilité). Un job de purge (Scheduler) supprime périodiquement les tokens `invalid`/`revoked` au-delà d'une rétention courte (§5.4, RGPD).
- **Collapse/coalescing** : positionner `apns-collapse-id` / FCM `collapse_key` = `E.id` (ou clé de regroupement par ressource) pour éviter l'empilement de notifications redondantes côté OS.

### 3.6 Anti-doublon / idempotence entre canaux et retries

```
Clé d'idempotence d'émission :
   email : (E.id, 'email', user.id)
   push  : (E.id, 'push',  device.id)

- Stockée dans une table légère NotificationDelivery (ou cache Redis avec TTL),
  posée AVANT/au succès d'envoi.
- Un retry Messenger (NACK→requeue) re-vérifie la clé ⇒ pas de second envoi.
- Le payload push embarque E.id (data 'eventId') ⇒ le client mobile peut corréler
  avec un évènement déjà reçu via Mercure (app au premier plan) et NE PAS empiler.
```

- **Concurrence** : si le même `NotificationEvent` est traité par deux workers (rare), la pose de la clé d'idempotence en base (contrainte d'unicité) ou en Redis (`SET NX`) sérialise — un seul gagne l'émission par `(event, channel, target)`.

### 3.7 Flutter — cycle de vie permission / token

```
Au login / au démarrage app :
1. lirePermissionOS()
   - SI 'notDetermined' → ne PAS demander tout de suite ; demander au moment opportun
     (après une action de valeur, pas au premier écran — bonne pratique iOS).
2. SI permission 'granted' :
   token = await messaging.getToken()      // FCM ; sur iOS, APNs token via FCM
   POST /api/me/devices { platform, token, installation_id, app_version, locale }
3. Abonner onTokenRefresh → ré-POST /api/me/devices (rotation, US-PUSH-2).
4. Handlers de réception :
   - onMessage (foreground)        → bannière in-app discrète, anti-doublon Mercure.
   - onMessageOpenedApp (tap)      → router deep-link (eventId/resource → écran).
   - getInitialMessage (cold start)→ deep-link au démarrage.
   - onBackgroundMessage           → handler isolé minimal (pas de logique lourde).
5. Au logout :
   DELETE /api/me/devices/{id}     // ou par token ; puis messaging.deleteToken().
6. Écran Paramètres › Notifications :
   - reflète l'état permission OS (granted/denied/notDetermined)
   - toggles par catégorie (canal push) ⇄ PUT /api/me/notification-prefs
   - si denied → CTA « Activer dans les réglages système ».
```

---

## 4. Cas limites (edge cases)

### 4.1 Dates, fuseaux, heure d'été/hiver, bissextiles
- **Quiet-hours autour de la bascule d'heure** : à la transition de mars (saut de 2h→3h) ou d'octobre (recul 3h→2h), une plage silencieuse 22h–7h est évaluée en heure **locale Europe/Paris** via la base IANA ; la durée réelle varie d'une heure le jour de bascule. **Comportement attendu** : aucun calcul d'offset en dur, on se fie à la conversion `tz`.
- **Évènement à cheval sur minuit** : un déclencheur à 23h59 reste évalué sur la date locale du moment ; aucune accumulation différée au lendemain hors plage.
- **Année bissextile** : aucun impact spécifique (le push ne calcule pas de garde) ; les `last_seen_at`/`occurred_at` sont des timestamps UTC, le 29/02 ne pose pas de cas particulier.
- **Horodatage `occurred_at`** : toujours stocké en UTC, converti à l'affichage/évaluation ; jamais de date codée en dur (pas de « 23 juin 2026 »).

### 4.2 Concurrence & idempotence
- **Double enregistrement simultané au boot** : deux requêtes `POST /api/me/devices` quasi simultanées avec le même token ⇒ verrou sur `token_hash` ⇒ une crée, l'autre met à jour ; jamais de doublon actif.
- **Rotation de token concurrente à un envoi en cours** : un push enfilé sur l'ancien token peut partir juste après la révocation ⇒ retour `INVALID_TOKEN` géré proprement (nettoyage), sans erreur visible.
- **Retry Messenger après crash worker** : la clé d'idempotence `(E.id, push, device.id)` empêche le double push.
- **Deux workers traitant le même `NotificationEvent`** : sérialisation par contrainte d'unicité / `SET NX` Redis ; un seul émet.

### 4.3 Autorisations (parent / beau-parent / enfant / admin / dernier admin)
- **Enfant — catégorie `expenses`** : jamais notifié (droit `expenses` forcé `none`) ; aucun push `expenses` à un compte enfant, même si le toggle existait par erreur.
- **Beau-parent sans droit `exchanges`** : non éligible aux push d'échange (filtré au niveau destinataires, §3.3, Voter).
- **Changement de droit entre émission et tap** : retrait du droit ⇒ ouverture deep-link refusée par l'API (`403/404`), message localisé, repli accueil (US-PUSH-5).
- **Dernier admin** : le push ne touche pas la protection du dernier admin (aucune mutation de rôle ici) ; aucune régression à introduire. Une notification d'invitation/changement de rôle (Lot 7) reste gouvernée par les règles existantes.
- **Préférences = ressource utilisateur, pas familiale** : `/api/me/devices` et `/api/me/notification-prefs` ne requièrent **pas** `X-Family-Id` ; un Voter de domaine ne s'applique pas à l'opt-in lui-même (seulement à l'éligibilité du destinataire).

### 4.4 Scope famille (`X-Family-Id`)
- **Token multi-famille** : un `DeviceToken` est lié à un **`User`**, pas à une famille. Un utilisateur membre de plusieurs familles reçoit les push de toutes ses familles éligibles ; le payload porte le `family_id` pour basculer le contexte à l'ouverture.
- **Famille liée (overlay)** : l'overlay est **lecture seule sur l'agenda** (§2.1) ; il ne génère **aucun** déclencheur de notification supplémentaire. Aucun push n'est émis pour des événements de la famille liée (pas de fuite hors agenda RO).
- **Requête métier non scopée** : interdite (invariant). Les endpoints devices/prefs sont des ressources `me`, non métier-familiales — conformes.

### 4.5 Valeurs vides / limites
- **Aucun appareil enregistré** : opt-in push activé mais 0 token actif ⇒ aucun push (silencieux, pas d'erreur). L'e-mail reste émis selon ses propres préférences.
- **Token chaîne vide / trop long** : rejeté `422`.
- **`installation_id` absent** : géré (on retombe sur l'unicité par `token_hash` ; la rotation est alors détectée par la passerelle plutôt que par installation).
- **Toutes catégories push désactivées** : aucun push, même avec des appareils valides.
- **Payload trop volumineux** (limites APNs ~4 Ko / FCM ~4 Ko data) : tronquer le corps localisé, conserver le `data{deep-link}` essentiel ; ne jamais inclure de contenu sensible en clair dans le payload (voir §4.8).

### 4.6 Échecs externes (passerelles, e-mail, API vacances, Mercure)
- **APNs/FCM indisponible (5xx, timeout)** : NACK + backoff exponentiel + jitter, plafond d'essais, puis DLQ + alerte ; tokens **non** invalidés ; l'e-mail (autre canal) n'est pas affecté.
- **Clé APNs/credential FCM expirée** : échec d'auth opérationnel ⇒ alerte/monitoring, pas d'invalidation des tokens utilisateurs ; à corriger côté coffre/config (jamais en dur).
- **E-mail en panne (Lot 7)** : indépendant ; un canal qui échoue n'empêche pas l'autre.
- **API vacances / Mercure hors-ligne** : sans rapport direct avec le push ; un push « changement de garde » reste émis si le déclencheur a été produit (Lot 3/7), indépendamment de l'état Mercure web.
- **Rejet `INVALID_TOKEN`** : invalidation immédiate du token concerné uniquement, sans bloquer les autres tokens du même utilisateur.

### 4.7 Conflits offline (mobile)
- **Token enregistré hors-ligne** : la requête `POST /api/me/devices` est mise dans l'**outbox** (avec `Idempotency-Key`, §6) et rejouée à la reconnexion ; pas de doublon grâce à l'idempotence serveur (§3.1).
- **Logout hors-ligne** : la révocation est enfilée ; tant qu'elle n'est pas rejouée, le serveur peut encore router des push à un token qui sera révoqué dès reconnexion (fenêtre étroite acceptée). En cas d'appareil réellement perdu, l'utilisateur révoque depuis un **autre** appareil (`GET/DELETE /api/me/devices`).
- **Push reçu pendant que l'app était hors-ligne** : à la réouverture, l'app rafraîchit depuis l'API (autorité serveur) ; le deep-link ouvre l'état canonique, pas un état périmé du cache.

### 4.8 Sécurité / RGPD
- **Pas de contenu sensible dans le payload** : le corps du push **ne contient pas** de texte de message/note chiffré (champs 🔒) en clair lisible par un tiers/log de passerelle. On utilise un libellé générique localisé (ex. « Nouveau message dans … ») + `data{eventId, resourceType, resourceId, familyId}` ; le contenu réel est récupéré par l'app après ouverture, via l'API authentifiée. **Jamais de données de santé** (inexistantes de toute façon) ni d'identité chiffrée 🔒 dans le payload.
- **Token = donnée personnelle (technique)** : inscrit au registre des données personnelles (§7.1 cdc) ; chiffré au repos (🔒) ; HMAC pour l'index. Un dump SQL ne révèle pas les tokens.
- **Suppression de compte (RGPD)** : tous les `DeviceToken` du `User` sont **supprimés** en cascade (identifiants techniques, pas de valeur de cohérence de fil ⇒ pas d'anonymisation, contrairement aux messages/notes).
- **Purge des tokens dormants** : tokens `invalid`/`revoked` purgés après une rétention courte ; tokens `active` jamais utilisés depuis longtemps (heartbeat absent au-delà d'un seuil) candidats à invalidation/purge (réduction de surface).
- **Journalisation** : `device.registered|revoked|reassigned|invalidated` dans `AuditLog` (append-only), **sans** token en clair dans `metadata` (seulement l'id/plateforme/hash partiel).
- **Pas de révélation d'énumération** : agir sur un appareil tiers renvoie `404` plutôt que de confirmer l'existence.

### 4.9 i18n / localisation
- **Locale absente** : repli `device.locale` puis `fr-FR` (§3.4).
- **Format ICU** : titres/corps des push externalisés en ressources de traduction (Symfony côté serveur pour le rendu du payload, ARB côté Flutter pour les libellés purement client), pluriels/genre gérés ICU. Ajouter une langue = nouveau fichier, **aucun code**.
- **Locale par défaut** : `fr-FR` ; fuseau `Europe/Paris`. Seule `fr-FR` est livrée (architecture multilingue prête).
- **Caractères/longueur** : troncature respectant les graphèmes (emoji, accents) pour ne pas casser l'affichage natif.

### 4.10 Plateforme / appareil
- **iOS sans APNs token (simulateur)** : enregistrement impossible ⇒ géré sans crash ; l'app affiche l'état « push indisponible sur cet appareil ».
- **Android sans Google Play Services** : FCM indisponible ⇒ pas d'enregistrement, état clair en Paramètres.
- **Mise à jour d'app changeant `app_version`** : `POST /api/me/devices` met à jour `app_version` ; pas de nouvel enregistrement si même token/installation.
- **Réinstallation de l'app** : nouveau token (souvent nouveau `installation_id`) ⇒ nouvel enregistrement ; l'ancien finit invalidé par la passerelle.

---

## 5. Modèle de données touché

### 5.1 Entités modifiées

- **`NotificationPref`** *(existant, Lot 7 — §5.2)* : `user_id`, `category (custody|agenda|expenses|exchanges|invitation)`, `channel (email|push)`, `enabled`.
  - **Aucun changement de schéma** : l'énumération `channel` inclut déjà `push`. Le Lot 11 **exploite** la valeur `push` (création de lignes `channel='push'`). Unicité logique `(user_id, category, channel)`.

### 5.2 Entités créées

- **`DeviceToken`** *(nouvelle)* — un jeton de notification push par installation/appareil, rattaché à un `User`.

| Champ | Type | Notes |
|---|---|---|
| `id` | UUID | PK |
| `user_id` | UUID FK → User | propriétaire ; cascade delete à la suppression de compte |
| `platform` | enum `ios` \| `android` | passerelle cible (APNs/FCM) |
| `token` | string **🔒** | jeton APNs/FCM, **chiffré au repos** (AES-256-GCM applicatif) |
| `token_hash` | string (HMAC-SHA256, pepper KMS) | **index déterministe** pour unicité/lookup (le champ chiffré GCM n'est pas indexable) |
| `installation_id` | string (nullable) | identifiant stable d'installation app, pour détecter les rotations de token |
| `app_version` | string (nullable) | diagnostic |
| `device_model` | string (nullable) | diagnostic |
| `locale` | string (nullable) | locale secondaire (repli §3.4) |
| `status` | enum `active` \| `revoked` \| `invalid` | cycle de vie |
| `last_seen_at` | timestamptz (nullable) | dernier heartbeat/enregistrement |
| `last_success_at` | timestamptz (nullable) | dernier envoi réussi |
| `revoked_at` / `invalidated_at` | timestamptz (nullable) | traçabilité du cycle de vie |
| `created_at` / `updated_at` | timestamptz | communs (§5.2 cdc) |

- **`NotificationDelivery`** *(nouvelle, légère — facultatif si choix Redis)* — clé d'idempotence d'émission par canal/cible.

| Champ | Type | Notes |
|---|---|---|
| `id` | UUID | PK |
| `notification_event_id` | UUID/string | identité du `NotificationEvent` (Lot 7) |
| `channel` | enum `email` \| `push` | canal |
| `target_ref` | string | `user_id` (email) ou `device_id` (push) |
| `sent_at` | timestamptz | horodatage d'émission |

> Alternative d'implémentation conforme : porter l'anti-doublon dans **Redis** (`SET NX` + TTL) plutôt qu'en table. À fixer au moment de l'implémentation ; les deux respectent l'invariant d'idempotence.

### 5.3 Contraintes d'unicité & index

- `DeviceToken` : **unicité partielle** sur `token_hash` parmi `status='active'` (index unique partiel PostgreSQL `WHERE status='active'`) → garantit « au plus un token actif par valeur ».
- Index sur `(user_id, status)` pour le fan-out (récupérer les tokens actifs d'un utilisateur).
- Index sur `(user_id, installation_id)` (partiel actif) pour la détection de rotation.
- `NotificationDelivery` : unicité `(notification_event_id, channel, target_ref)` → idempotence d'émission.

### 5.4 Chiffrement, registre & rétention

- **🔒 `DeviceToken.token`** : chiffré au repos (AES-256-GCM, enveloppe de clé en coffre UE), comme les autres champs 🔒 (§5.3 cdc). Un dump SQL brut ne révèle pas les tokens.
- **Registre des données personnelles** : ajouter une **entrée** pour `DeviceToken` (catégorie : **technique/identifiant d'appareil** ; sensibilité 🔒 sur `token` ; base légale : intérêt légitime/exécution du service de notification consenti ; finalité : acheminement des notifications push ; durée : tant que l'appareil est actif + rétention courte des `revoked`/`invalid` ; destinataires : passerelles APNs/FCM, **sous-traitants à documenter** au registre). Attribut déclaratif sur l'entité + mise à jour de `/backend/docs/registre-donnees.md`. **Le test CI de cohérence du registre doit passer** (DoD).
- **Rétention/anonymisation/purge** (RGPD Manager, Lot 8) : règle de **suppression** (pas anonymisation) des `DeviceToken` `revoked`/`invalid` au-delà d'un seuil court ; suppression en cascade à l'effacement de compte. Idempotent, journalisé, dry-run possible.

### 5.5 Relations

```
USER ||--o{ DEVICE_TOKEN : "appareils"
USER ||--o{ NOTIFICATION_PREF : "préférences (email|push)"
```

---

## 6. Endpoints API concernés

### 6.1 Conventions applicables

- REST/JSON sous `/api`, **OpenAPI** servi par le backend (source du client Dart généré).
- **Auth** : `Authorization: Bearer <jwt>` obligatoire.
- **`X-Family-Id`** : **non requis** pour les ressources `/api/me/devices` et `/api/me/notification-prefs` (ressources utilisateur, non scopées famille). Le fan-out push résout les familles côté serveur.
- **Erreurs** : RFC 7807 (`application/problem+json`), messages localisés.
- **Idempotence** : header `Idempotency-Key` accepté sur `POST /api/me/devices` (rejeu outbox offline) ; idempotence métier en plus via `token_hash` (§3.1).

### 6.2 Endpoints

| Méthode | Chemin | Droit / Rôle | Description |
|---|---|---|---|
| GET | `/api/me/devices` | auth (self) | Lister mes appareils enregistrés (sans exposer le token en clair ; renvoyer id/plateforme/statut/last_seen) |
| POST | `/api/me/devices` | auth (self) | Enregistrer/rafraîchir un appareil `{platform, token, installation_id?, app_version?, device_model?, locale?}` ; idempotent |
| DELETE | `/api/me/devices/{id}` | auth (self, propriétaire) | Révoquer un appareil (logout / perdu) |
| GET | `/api/me/notification-prefs` | auth (self) | *(existant Lot 7)* Lire mes préférences ; **inclut désormais le canal `push`** |
| PUT | `/api/me/notification-prefs` | auth (self) | *(existant Lot 7)* Mettre à jour les toggles par `(category, channel)`, incluant `channel='push'` |

> Les déclencheurs (garde, agenda, échanges, invitation) ne changent **pas** d'endpoint : ils émettent le même `NotificationEvent` (Lot 7) ; le canal push est branché **dans le dispatcher**, pas via de nouvelles routes métier.

### 6.3 Exemple — enregistrer un appareil

```http
POST /api/me/devices
Authorization: Bearer <jwt>
Idempotency-Key: 5f1c…   (optionnel, rejeu outbox)
Content-Type: application/json
{
  "platform": "ios",
  "token": "<apns-or-fcm-token>",
  "installationId": "inst-abc-123",
  "appVersion": "2.0.0",
  "locale": "fr-FR"
}
→ 201 Created  { "id": "…", "platform": "ios", "status": "active" }
```

Erreur (payload invalide) :
```http
422  application/problem+json
{ "type":"/errors/validation", "title":"Requête invalide",
  "detail":"Le champ 'platform' doit valoir 'ios' ou 'android'." }
```

---

## 7. Invariants & règles métier

Invariants du cdc / `CLAUDE.md` **pertinents pour ce lot** :

1. **Scope famille & ressources `me`** : les push relèvent de préférences/appareils **utilisateur**, non scopés famille — `X-Family-Id` non requis sur ces endpoints. Le fan-out résout les familles côté serveur ; aucune requête **métier** non scopée n'est introduite.
2. **Autorisation par Voters** : l'**éligibilité d'un destinataire** à un événement reste gouvernée par les Voters de domaine (`custody`/`agenda`/`children_notes`/`exchanges`/`expenses`) au niveau `view|edit` (logique Lot 7, réutilisée tel quel). Un enfant n'a **jamais** d'accès `expenses` ⇒ jamais de push `expenses`. Le rôle `parent` force `edit` partout.
3. **Dernier admin protégé** : aucune mutation de rôle ici ; ne pas régresser.
4. **Priorité de la garde** : non touchée par ce lot (le push ne modifie jamais la garde). L'overlay famille liée reste RO et **ne déclenche aucune notification**.
5. **Pas de données de santé** : aucun champ santé créé ; aucun contenu sensible dans le payload push.
6. **Champs chiffrés au repos (🔒)** : `DeviceToken.token` chiffré AES-256-GCM ; aucun champ 🔒 (texte de message/note, identités) n'est inclus en clair dans un payload push ni dans les logs de passerelle.
7. **Registre des données personnelles** : ajout obligatoire d'une entrée pour `DeviceToken` (attribut déclaratif + `registre-donnees.md`) ; le test CI de cohérence doit passer. Fait partie de la DoD.
8. **Dépenses = v2 / activation par module** : le canal push `expenses` n'émet rien tant que le module dépenses n'est pas activé (Lot 10) ; aucun code dépense ajouté ici.
9. **i18n** : toutes les chaînes (titres/corps push, UI réglages) externalisées en ICU ; seule `fr-FR` livrée, ajout de langue sans code.
10. **Dates non codées en dur / fuseau** : `Europe/Paris` par défaut, conversions via base IANA (été/hiver), pas de « 23 juin 2026 » en dur.
11. **Idempotence & autorité serveur** : `Idempotency-Key` sur les POST outbox ; anti-doublon par clé `(event, channel, target)` ; le serveur reste l'autorité (le push ne porte qu'un déclencheur, l'état canonique vient de l'API).
12. **Architecture verrouillée** : aucun framework JS web ajouté ; pas de web push ; backend Symfony/Messenger, mobile Flutter, client généré depuis l'OpenAPI — inchangé.
13. **`/infra/INSTALL.md`** : mise à jour obligatoire si de nouvelles dépendances/credentials sont introduits (clés APNs, credentials FCM, plugin Flutter `firebase_messaging`/équivalent, variables d'environnement).

---

## 8. Definition of Done (DoD)

Reprend la **DoD générale par lot** (§9 cdc) appliquée au périmètre Lot 11 :

- **Tests unitaires (backend)** :
  - Résolution des destinataires éligibles × canal `push` (réutilise Lot 7) avec respect des Voters (enfant/`expenses` exclu, beau-parent sans droit exclu).
  - Décision d'envoi : opt-in par catégorie/canal, quiet-hours (incluant bascule été/hiver Europe/Paris), résolution de locale (compte → appareil → `fr-FR`).
  - Cycle de vie token : enregistrement idempotent, réassignation, rotation par `installation_id`, invalidation sur `INVALID_TOKEN`, non-invalidation sur erreur temporaire.
  - Anti-doublon : clé `(event, channel, target)` ; pas de double émission au retry.
  - `PushSender` testé avec des **fakes** APNs/FCM (aucun appel réseau réel en CI).
- **Tests fonctionnels / API** :
  - `GET/POST/DELETE /api/me/devices` (auth, idempotence `Idempotency-Key`, 401/403/404/422, jamais de token en clair dans la réponse).
  - `GET/PUT /api/me/notification-prefs` avec `channel='push'`.
  - Scoping : endpoints `me` sans `X-Family-Id` ; refus d'agir sur l'appareil d'autrui.
- **Tests E2E web (Playwright)** : le push étant mobile-only, l'E2E web couvre **uniquement** que l'écran Paramètres › Notifications web (s'il expose des toggles) reste cohérent et n'expose pas de push web ; sinon, non-régression du parcours notifications existant. (Pas de web push.)
- **Tests Flutter** : permission OS (granted/denied/notDetermined) ; enregistrement/rotation du token (`POST /api/me/devices` mocké) ; réception (foreground/background/cold start) ; **deep-link** vers échange/garde/agenda ; logout → révocation ; écran réglages reflète l'état permission ; comportement hors-ligne (outbox du `POST /api/me/devices`, idempotence).
- **Migrations** : création `DeviceToken` (+ `NotificationDelivery` si retenu), index uniques partiels, FK cascade ; migrations versionnées fournies et réversibles.
- **OpenAPI** : à jour (nouveaux endpoints devices ; champ `push` documenté pour notification-prefs) ; **client Dart régénéré** depuis le schéma.
- **i18n (fr-FR)** : libellés de notification push (titres/corps) et UI réglages externalisés en ICU (Symfony + ARB Flutter) ; aucun texte en dur ; ajout de langue sans code.
- **Registre des données personnelles** : entrée `DeviceToken` ajoutée (attribut + `registre-donnees.md`) ; **test CI de cohérence vert**. Règle de rétention/purge des tokens définie (RGPD Manager) ; cascade de suppression à l'effacement de compte testée.
- **Sécurité / RGPD** : `DeviceToken.token` chiffré (🔒) — un dump SQL ne révèle pas les tokens ; aucun contenu sensible dans les payloads push ; `AuditLog` branché (`device.registered|revoked|reassigned|invalidated`) sans token en clair ; credentials APNs/FCM en coffre (jamais en base ni en dur).
- **`/infra/INSTALL.md`** : mis à jour (dépendances/credentials APNs & FCM, plugin Flutter, variables d'environnement, étapes de configuration).
- **Analyse statique** : PHPStan (niveau élevé) + `php-cs-fixer` propres côté backend ; `dart analyze` + `dart format` propres côté mobile.
- **Conformité design system (§8)** : écran Paramètres › Notifications conforme (typos `Bricolage Grotesque`/`Hanken Grotesk`, palette terracotta/teal, mobile iOS « liquid glass », toggles inset r:26) ; états « push désactivés au niveau système » lisibles et localisés.
- **Anti-doublon multi-canal** : vérifié e-mail/push sur le même événement (un seul de chaque par cible) et corrélation Mercure côté app (pas d'empilement redondant au premier plan).
