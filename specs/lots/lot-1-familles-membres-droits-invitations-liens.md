# Lot 1 — Familles, membres, rôles & droits, invitations, liens

> Document de conception détaillé dérivé de `specs/cdc.md` (source de vérité) et des invariants verrouillés de `CLAUDE.md`. En cas de divergence visuelle, les maquettes HTML du bundle Claude Design font foi. Aucune décision d'architecture verrouillée (cdc §0) n'est remise en cause ici.

---

## 0. Objectif & périmètre

### 0.1 Objectif

Mettre en place le **socle multi-famille** de CoParentPlanner : modélisation et gestion complète des **familles**, des **membres** (utilisateurs rattachés à une famille avec rôle + droits), du **système d'autorisation par Voters** scopé à la **famille active** (header `X-Family-Id`), du **cycle de vie d'invitation par e-mail** (token haché, usage unique, expirant) et des **liens entre familles** (relation bidirectionnelle logique, **sans overlay** — l'overlay est porté par le Lot 5).

Ce lot transforme l'authentification nue du Lot 0 (un utilisateur isolé) en un système collaboratif structuré : à l'issue du Lot 1, un utilisateur peut créer/rejoindre plusieurs familles, basculer entre elles, et tous les lots suivants (Enfants, Garde, Agenda, Échanges…) s'appuieront sur le scope famille et les Voters mis en place ici.

### 0.2 Dans le périmètre

- Entités **Family**, **Membership**, **Right**, **Invitation**, **FamilyLink** (création des migrations Doctrine).
- **Sélecteur de famille** (web + mobile) et persistance de la famille active.
- **Scope famille** : middleware/résolveur validant le header `X-Family-Id` contre les memberships actifs de l'utilisateur authentifié.
- **Voters** par domaine de droit : `custody`, `agenda`, `children_notes`, `expenses`, `exchanges`, `family_settings`, niveaux `none | view | edit`, opérant sur le couple (Membre, Ressource). Câblage de la logique de résolution (forçage `parent = edit`, interdits `child`).
- Écran **Paramètres › Compte & Famille** (volet famille) : nom, type (`separated | intact`), modules activés, jour & heure d'échange de garde (champs **stockés** mais non exploités avant le Lot 3), fuseau, zone scolaire & académie.
- **Gestion des membres** : lister, voir rôle + droits, modifier rôle/admin/droits, retirer un membre.
- **Invitation par e-mail** : création (e-mail + rôle + droits proposés), envoi async (Messenger + Mailer), récapitulatif des droits avant envoi, acceptation via token, révocation, expiration.
- **Droits granulaires** beau-parent / enfant : attribution à l'invitation **et** modification ultérieure par un parent/admin.
- **Protection du dernier admin** : interdiction de retrait et de rétrogradation.
- **Liens entre familles** : création (`pending` → `active`), suppression (déliaison), nature bidirectionnelle logique, **sans aucun affichage overlay**.
- Câblage **i18n** (toutes chaînes UI + e-mails externalisées, ICU, `fr-FR` livrée).
- **AuditLog** des actions sensibles introduites par ce lot (changements de droits/rôles, invitations, liens, retraits de membres).
- Mise à jour du **registre des données personnelles** pour les nouveaux champs personnels introduits (`Invitation.email` 🔒, `Membership` et `FamilyLink` qui référencent des personnes).

### 0.3 Hors périmètre (reporté)

- **Authentification / onboarding compte** (US-AUTH-1/2, US-ONB-1 partie « créer un compte ») : livrés au **Lot 0**. Le Lot 1 réutilise l'utilisateur authentifié ; il couvre la partie « créer ma **première famille** » et « rejoindre une famille via une invitation » de US-ONB-1.
- **Overlay** des familles liées (affichage RO des événements de la famille liée, couleur `#9C6FB0`) → **Lot 5**.
- **Profils enfants & notes** (Child, Note) → **Lot 2**. En conséquence, le rattachement `Membership.child_id` d'un compte-enfant à un profil `Child` ne peut être **résolu** qu'au Lot 2 ; le Lot 1 prévoit la colonne nullable mais ne crée pas de profil Child.
- **Garde** (CustodyPattern, moteur, vacances) → **Lot 3**. Les champs de réglage `handover_weekday`, `handover_time`, `school_zone`, `academy` sont **saisis et persistés** dès le Lot 1 (volet réglages famille) mais ne déclenchent aucun calcul.
- **Notifications e-mail configurables** (NotificationPref, déclencheurs) → **Lot 7**. L'e-mail d'**invitation** est néanmoins envoyé dès le Lot 1 (c'est un e-mail transactionnel, pas une notification préférentielle).
- **Dépenses** (Expense / ExpenseShare) → **Lot 9 (v2)**, masquées. Le domaine de droit `expenses` existe dans le modèle de Voter mais reste **inerte** (toujours `none` pour enfant, jamais d'onglet/d'API active).
- **PWA / offline riche** → **Lot 8**. Le mobile peut mettre en cache la liste des familles/membres en lecture seule, mais l'outbox d'écriture complète relève du Lot 8.

### 0.4 Contexte v1 / v2

Tout le contenu du Lot 1 est **v1**. Le domaine `expenses` est réservé pour la v2 : présent comme énumération de domaine de droit afin de ne pas modifier l'API plus tard, mais sans onglet, sans endpoint actif, et **forcé à `none` pour les enfants** (invariant). Les notifications **push** (canal `push`) sont v2 et hors lot.

---

## 1. Dépendances

### 1.1 Lots prérequis (avant)

| Lot | Pourquoi il est requis |
|---|---|
| **Lot 0 — Fondations** | Fournit : le monorepo, le squelette Symfony + API Platform + Doctrine + PostgreSQL, l'**authentification JWT complète** (login/refresh/reset), l'entité **User**, les **Voters squelette** (à étoffer ici), le composant **i18n** (Translation/ICU) + Mailer + MailHog, le **front web Symfony UX** (AssetMapper/Turbo/Stimulus, thème §8, shell applicatif **et sélecteur de famille** déjà amorcé), le **client Flutter** généré depuis l'OpenAPI + couche offline (Drift/outbox), le **harnais de tests** (PHPUnit, Playwright, Flutter, PHPStan), et le **squelette du RGPD Manager** + `registre-donnees.md` initialisé. Sans le Lot 0, aucune notion d'utilisateur authentifié ni d'infrastructure de test/CI. |

### 1.2 Lots débloqués (après)

Le Lot 1 est **bloquant pour tous les lots métier suivants**, car il pose le scope famille et les Voters :

| Lot débloqué | Ce qu'il consomme du Lot 1 |
|---|---|
| **Lot 2 — Enfants & notes** | Voter `children_notes`, scope famille, `Membership` (auteur des notes, compte-enfant rattaché à `Child`). |
| **Lot 3 — Garde** | Voter `custody`, `Family.type == separated`, réglages jour/heure d'échange/zone/académie saisis ici, `Membership` des parents A/B. |
| **Lot 4 — Agenda** | Voter `agenda`, scope famille, `Membership` (responsable d'événement : parent **ou** beau-parent). |
| **Lot 5 — Familles liées & overlay** | `FamilyLink` (statut `active`) créé ici ; l'overlay s'appuie sur le lien sans le redéfinir. |
| **Lot 6 — Échanges** | Voter `exchanges`, scope famille, `Membership` (auteur/votant). |
| **Lot 7 — Notifications e-mail** | Déclencheur **invitation** (déjà câblé ici), `Membership`, NotificationPref par utilisateur. |
| **Lot 8 — RGPD Manager** | `Invitation.email` 🔒 (anonymisation), `Membership` (anonymisation « membre supprimé » sans casser les fils), AuditLog des changements de droits déjà branché. |
| **Lot 9 — Dépenses (v2)** | Voter `expenses` (déjà modélisé, inerte en v1), `Membership` (payeur). |

---

## 2. User stories complètes

> Convention Gherkin : **Étant donné / Quand / Alors**. Les IDs existants du cdc sont réutilisés ; les compléments nécessaires sont préfixés `US-FAM-*` (familles), `US-MBR-*` (membres/droits), `US-INV-*` (invitations), `US-LINK-*` (liens), `US-SCOPE-*` (scope/sélecteur). Sauf mention contraire, toute action métier exige un header `X-Family-Id` valide (voir §3.4) et l'utilisateur authentifié.

### 2.1 Onboarding & création de famille

#### US-ONB-1a — Créer ma première famille
**En tant qu'**utilisateur authentifié sans famille, **je veux** créer ma première famille (nom + type) **afin de** commencer à coordonner mes enfants.

- **Scénario nominal — famille séparée**
  - **Étant donné** un utilisateur authentifié n'appartenant à aucune famille
  - **Quand** il soumet `POST /api/families` avec `{name:"Famille Durant", type:"separated"}`
  - **Alors** une `Family` est créée avec `type=separated`, `timezone="Europe/Paris"` par défaut
  - **Et** un `Membership` est créé pour lui avec `role=parent`, `is_admin=true`, `status=active`
  - **Et** ses 6 droits sont **forcés/ignorés à `edit`** (rôle parent), `family_settings=edit` car admin
  - **Et** la réponse `201` renvoie la famille + le membership créateur
  - **Et** une entrée `AuditLog` (`action="family.created"`) est écrite.

- **Scénario nominal — famille unie**
  - **Étant donné** le même utilisateur
  - **Quand** il crée une famille `{type:"intact"}`
  - **Alors** la famille est créée sans aucune notion de garde (le domaine `custody` restera sans objet ; aucun onglet Garde n'apparaîtra), et `handover_weekday`/`handover_time`/`school_zone`/`academy` sont nullables/ignorés.

- **Scénario — validation des entrées**
  - **Étant donné** un utilisateur authentifié
  - **Quand** il soumet un `name` vide ou un `type` hors `{separated, intact}`
  - **Alors** réponse `422` (ou `400`) en `application/problem+json` avec message localisé (`fr-FR`), aucune famille créée.

- **Scénario — non authentifié**
  - **Étant donné** une requête sans `Authorization: Bearer`
  - **Quand** elle appelle `POST /api/families`
  - **Alors** réponse `401`, aucune création.

#### US-ONB-1b — Rejoindre une famille via une invitation
**En tant qu'**invité (avec ou sans compte), **je veux** rejoindre une famille via un lien d'invitation **afin de** participer à la coordination.
> Détaillé en US-INV-3.

### 2.2 Sélecteur de famille & scope

#### US-SCOPE-1 — Lister mes familles & basculer entre elles
**En tant qu'**utilisateur appartenant à plusieurs familles, **je veux** lister mes familles et choisir la famille active **afin de** travailler dans le bon contexte.

- **Scénario nominal**
  - **Étant donné** un utilisateur membre actif de 3 familles
  - **Quand** il appelle `GET /api/families`
  - **Alors** la réponse liste exactement ces 3 familles, avec pour chacune son rôle, `is_admin`, et le type de famille
  - **Et** elle exclut toute famille où son `Membership.status != active` (ex. invitation en attente le concernant).

- **Scénario — sélection persistée (web)**
  - **Étant donné** le sélecteur de famille du shell (Lot 0)
  - **Quand** l'utilisateur choisit « Famille Durant »
  - **Alors** la famille active est persistée (cookie/session web et préférence côté mobile) et toutes les requêtes suivantes portent `X-Family-Id` correspondant.

- **Scénario — utilisateur sans aucune famille**
  - **Étant donné** un utilisateur fraîchement inscrit
  - **Quand** il appelle `GET /api/families`
  - **Alors** la réponse est une liste vide (`[]`) et l'UI propose « Créer une famille » ou « Rejoindre via invitation » (US-ONB-1a / US-INV-3).

#### US-SCOPE-2 — Rejeter une requête hors scope
**En tant que** système, **je veux** rejeter toute requête métier dont le `X-Family-Id` ne correspond pas à un membership actif **afin de** garantir l'isolation entre familles.

- **Scénario — header manquant sur ressource scopée**
  - **Étant donné** une requête authentifiée vers une ressource scopée (ex. `GET /api/families/{id}/members`) sans header `X-Family-Id`
  - **Quand** elle atteint le résolveur de scope
  - **Alors** réponse `400` (`/errors/missing-family-scope`) en problem+json localisé.

- **Scénario — header pointant une famille étrangère**
  - **Étant donné** un utilisateur membre des familles F1, F2
  - **Quand** il envoie `X-Family-Id: F3` (où il n'est pas membre actif)
  - **Alors** réponse `403` (`/errors/forbidden-family-scope`), aucune fuite (ne pas révéler si F3 existe : message générique).

- **Scénario — incohérence path vs header**
  - **Étant donné** une requête `GET /api/families/F1/members` avec `X-Family-Id: F2`
  - **Quand** le résolveur compare l'id du chemin et le header
  - **Alors** réponse `400` (`/errors/family-scope-mismatch`) ; le scope du chemin et le header doivent coïncider.

- **Scénario — membership invité (non actif)**
  - **Étant donné** un utilisateur ayant un `Membership.status=invited` sur F4 (invitation pas encore acceptée)
  - **Quand** il envoie `X-Family-Id: F4`
  - **Alors** réponse `403` : un membership `invited` n'ouvre pas le scope (seul `active` le fait).

### 2.3 Réglages de la famille (Paramètres › Compte & Famille)

#### US-SET-2a — Éditer les réglages de la famille
**En tant qu'**admin, **je veux** éditer le nom, le type, les modules activés, le fuseau, la zone scolaire & l'académie, le jour & l'heure d'échange **afin de** configurer la famille.

- **Scénario nominal (admin)**
  - **Étant donné** un admin de F1 avec `family_settings=edit`
  - **Quand** il `PATCH /api/families/F1` `{name:"Famille Durant-Bernard", timezone:"Europe/Paris", schoolZone:"C", academy:"Créteil"}`
  - **Alors** les champs sont mis à jour, `updated_at` rafraîchi, `AuditLog` (`family.settings.updated`) écrit
  - **Et** la réponse `200` renvoie la famille mise à jour.

- **Scénario — autorisation refusée (Voter `family_settings`)**
  - **Étant donné** un parent **non admin** (ou un beau-parent, ou un enfant) de F1
  - **Quand** il `PATCH /api/families/F1`
  - **Alors** réponse `403` problem+json (`detail: "Droit 'family_settings:edit' requis."`), aucun changement.

- **Scénario — changement de type interdit en présence de données dépendantes**
  - **Étant donné** une famille `separated` (le Lot 1 ne contient pas encore de données de garde)
  - **Quand** un admin tente de basculer `type` vers `intact`
  - **Alors** au Lot 1 le changement est autorisé (aucune dépendance) ; **note d'évolution** : aux lots ≥3, si des `CustodyPattern`/`CustodyDay` existent, le changement doit être bloqué ou confirmé (hors périmètre Lot 1, à documenter dans la migration future). Le champ `expenses_enabled` reste optionnel (v2).

- **Scénario — fuseau invalide**
  - **Étant donné** un admin
  - **Quand** il envoie `timezone:"Mars/Olympus"`
  - **Alors** `422`, validation IANA, message localisé. Aucune date n'est codée en dur ; `Europe/Paris` par défaut.

- **Scénario — type incohérent / valeurs aux bornes**
  - **Étant donné** un admin
  - **Quand** `schoolZone` n'appartient pas à `{A,B,C}` ou `name` dépasse la longueur max
  - **Alors** `422` localisé.

#### US-SET-2b — Consulter les réglages de la famille (lecture)
**En tant que** membre actif, **je veux** consulter les réglages de la famille **afin de** comprendre son contexte.

- **Scénario nominal**
  - **Étant donné** n'importe quel membre actif (parent/beau-parent/enfant)
  - **Quand** il `GET /api/families/F1`
  - **Alors** il reçoit les réglages **non sensibles** ; les champs réservés à l'édition (modules) sont visibles en lecture. Le Voter `family_settings` autorise `view` à tout membre actif et `edit` au seul admin.

### 2.4 Gestion des membres, rôles & droits

#### US-CH-2 / US-MBR-1 — Voir les membres, leurs rôles et leurs droits
**En tant que** membre actif, **je veux** voir qui appartient à la famille, avec quel rôle et quels droits **afin de** savoir qui a accès à quoi.

- **Scénario nominal**
  - **Étant donné** un membre actif de F1
  - **Quand** il `GET /api/families/F1/members`
  - **Alors** il reçoit la liste des memberships actifs (et invitations en attente listées séparément si admin), chacun avec `role`, `is_admin`, `display_color`, et la matrice de droits par domaine
  - **Et** le `full_name` 🔒 et `phone` 🔒 sont **déchiffrés à la volée** et renvoyés uniquement aux membres autorisés (jamais dans un dump brut).

- **Scénario — perspective enfant**
  - **Étant donné** un membre `child` de F1
  - **Quand** il liste les membres
  - **Alors** il voit les membres mais **ne voit pas** les détails réservés (ex. e-mails d'invitation en attente) ; il ne voit jamais le volet d'édition des droits.

#### US-MBR-2 — Modifier le rôle / admin / droits d'un membre
**En tant qu'**admin/parent (droit `family_settings=edit`), **je veux** modifier le rôle, l'attribut admin et les droits granulaires d'un membre **afin d'**ajuster ses accès.

- **Scénario nominal — ajuster les droits d'un beau-parent**
  - **Étant donné** un admin de F1 et un membre `step_parent`
  - **Quand** il `PATCH /api/memberships/{id}` `{rights:{agenda:"edit", children_notes:"view", custody:"none", exchanges:"view"}}`
  - **Alors** les `Right` correspondants sont mis à jour (création si absent), `AuditLog` (`membership.rights.updated`) écrit avec l'avant/après
  - **Et** le Voter applique immédiatement les nouveaux droits aux requêtes suivantes.

- **Scénario — promotion parent (droits forcés)**
  - **Étant donné** un membre `step_parent`
  - **Quand** un admin le passe `role=parent`
  - **Alors** tous ses droits sont **ignorés et résolus à `edit`** (sauf `family_settings` qui dépend de `is_admin`), même si la requête envoie des niveaux inférieurs ; ceux-ci sont silencieusement écrasés/normalisés.

- **Scénario — interdits enfant (forçage)**
  - **Étant donné** un membre `child`
  - **Quand** un admin tente `rights:{expenses:"view"}` ou `rights:{children_notes:"edit"}` ou `rights:{family_settings:"view"}`
  - **Alors** `expenses` est **forcé à `none`** (invariant v2), `family_settings` à `none`, l'édition de structure (custody/membres/réglages) reste impossible ; les domaines autorisés pour un enfant se limitent à `view|none` pour `custody`, `agenda`, `exchanges`, et `none` par défaut pour `children_notes`. Une demande hors de cet ensemble est rejetée `422` ou normalisée à la valeur permise (comportement choisi : **normalisée**, et journalisée).

- **Scénario — autorisation refusée**
  - **Étant donné** un beau-parent (même avec `agenda=edit`)
  - **Quand** il `PATCH /api/memberships/{id}`
  - **Alors** `403` (`family_settings:edit` requis) ; seuls admin/parent-admin peuvent gérer les membres. *(Note : §2.2 indique `family_settings=edit (si admin)` pour parent ; un parent non-admin n'édite pas la structure.)*

- **Scénario — rétrogradation du dernier admin (refusée)**
  - **Étant donné** F1 avec un **seul** admin
  - **Quand** cet admin (ou un autre acteur) tente `PATCH` `{isAdmin:false}` ou `{role:"step_parent"}` sur ce dernier admin
  - **Alors** `409` (`/errors/last-admin-protected`) problem+json localisé, aucun changement (invariant **dernier admin protégé**).

- **Scénario — rétrogradation d'un admin parmi plusieurs (autorisée)**
  - **Étant donné** F1 avec 2 admins
  - **Quand** un admin retire `is_admin` à l'autre
  - **Alors** `200`, il reste ≥1 admin, `AuditLog` écrit.

- **Scénario — auto-modification cohérente**
  - **Étant donné** un admin unique
  - **Quand** il tente de se rétrograder lui-même
  - **Alors** `409` (dernier admin protégé) ; il doit d'abord promouvoir un autre admin.

#### US-MBR-3 — Retirer un membre
**En tant qu'**admin, **je veux** retirer un membre de la famille **afin de** gérer la composition.

- **Scénario nominal**
  - **Étant donné** un admin et un membre `step_parent`
  - **Quand** il `DELETE /api/memberships/{id}`
  - **Alors** le membership passe à `status` retiré (soft-removal) ou est supprimé ; ses `Right` sont supprimés ; l'utilisateur perd l'accès à F1 ; `AuditLog` (`membership.removed`) écrit
  - **Et** les contenus partagés (futurs messages/notes des lots suivants) ne sont **pas** supprimés (anonymisation au Lot 8, pas ici).

- **Scénario — retrait du dernier admin (refusé)**
  - **Étant donné** F1 avec un seul admin
  - **Quand** on tente de retirer ce dernier admin
  - **Alors** `409` (`/errors/last-admin-protected`).

- **Scénario — auto-retrait du dernier admin (refusé)**
  - **Étant donné** un admin unique
  - **Quand** il tente de se retirer lui-même
  - **Alors** `409` ; il doit promouvoir/inviter un autre admin d'abord. *(Un membre non-admin peut, lui, quitter la famille librement — voir US-MBR-4.)*

- **Scénario — autorisation refusée**
  - **Étant donné** un beau-parent
  - **Quand** il tente `DELETE /api/memberships/{id}`
  - **Alors** `403` (`family_settings:edit` requis).

#### US-MBR-4 — Quitter une famille (auto-retrait)
**En tant que** membre non-administrateur, **je veux** quitter une famille **afin de** ne plus y être rattaché.

- **Scénario nominal**
  - **Étant donné** un beau-parent de F1 (non admin)
  - **Quand** il `DELETE /api/memberships/{son-id}` (cible = lui-même)
  - **Alors** son membership est retiré, il perd l'accès, `AuditLog` (`membership.left`) écrit.

- **Scénario — dernier admin ne peut quitter**
  - **Étant donné** l'unique admin
  - **Quand** il tente de quitter
  - **Alors** `409` (dernier admin protégé) ; cohérent avec US-MBR-3.

#### US-MBR-5 — Personnaliser sa couleur de membre
**En tant que** membre, **je veux** définir ma couleur d'affichage (`display_color`) **afin d'**être identifiable dans les vues (parent A terracotta, parent B teal, etc.).

- **Scénario nominal**
  - **Étant donné** un membre
  - **Quand** il `PATCH /api/memberships/{son-id}` `{displayColor:"#2E7D71"}`
  - **Alors** la couleur est enregistrée ; en l'absence de valeur, une couleur par défaut du design system §8 est attribuée (terracotta `#C2643D` / teal `#2E7D71`).

### 2.5 Invitations

#### US-SET-2c / US-INV-1 — Inviter une personne
**En tant qu'**admin, **je veux** inviter une personne par e-mail (rôle + droits proposés) **afin d'**ajouter un membre.

- **Scénario nominal — beau-parent**
  - **Étant donné** un admin de F1
  - **Quand** il `POST /api/families/F1/invitations` `{email:"jean@ex.fr", role:"step_parent", rights:{agenda:"edit", children_notes:"view", custody:"none", exchanges:"view"}}`
  - **Alors** une `Invitation` est créée : `email` 🔒 chiffré, `role=step_parent`, `proposed_rights` (jsonb), `token_hash` (hash du token, jamais le token en clair), `expires_at` (ex. +7 jours), `status=pending`, `invited_by`
  - **Et** un message Messenger est dispatché pour envoyer l'e-mail localisé contenant le lien à **usage unique** `…/invitations/accept?token=<clair>`
  - **Et** la réponse `201` renvoie l'invitation **sans** le token en clair (et l'e-mail masqué le cas échéant)
  - **Et** `AuditLog` (`invitation.created`) écrit.

- **Scénario — récapitulatif des droits avant envoi (UI)**
  - **Étant donné** l'écran d'invitation
  - **Quand** l'admin compose l'invitation
  - **Alors** l'UI affiche un **récapitulatif des droits** (par domaine) avant le bouton « Envoyer » (CA §3.8).

- **Scénario — invitation enfant (droits contraints)**
  - **Étant donné** un admin invitant `role:"child"`
  - **Quand** il propose `rights:{expenses:"view"}` ou `children_notes:"edit"`
  - **Alors** les droits sont **normalisés** aux interdits enfant (`expenses=none` forcé, `family_settings=none`, édition de structure impossible) avant persistance, et le récapitulatif reflète les droits réellement appliqués.

- **Scénario — rôle parent invité**
  - **Étant donné** un admin invitant `role:"parent"`
  - **Quand** l'invitation est créée
  - **Alors** `proposed_rights` est ignoré (parent = `edit` partout) ; `is_admin` peut être proposé optionnellement (un second parent peut devenir admin à l'acceptation selon le champ proposé).

- **Scénario — e-mail déjà membre actif**
  - **Étant donné** que `jean@ex.fr` est déjà membre actif de F1
  - **Quand** l'admin l'invite à nouveau
  - **Alors** `409` (`/errors/already-member`) localisé, aucune invitation doublon.

- **Scénario — invitation en attente déjà existante (idempotence métier)**
  - **Étant donné** une invitation `pending` non expirée pour ce couple (email, famille)
  - **Quand** l'admin réinvite le même e-mail
  - **Alors** soit l'invitation existante est **renvoyée/régénérée** (nouveau token, ancien invalidé), soit `409` selon la politique retenue ; **comportement choisi** : régénérer (révoque l'ancienne, en crée une nouvelle), journalisé.

- **Scénario — validation e-mail**
  - **Étant donné** un admin
  - **Quand** il envoie un e-mail mal formé
  - **Alors** `422` localisé, aucune invitation.

- **Scénario — autorisation refusée**
  - **Étant donné** un parent non-admin ou un beau-parent
  - **Quand** il `POST …/invitations`
  - **Alors** `403` (`family_settings:edit` requis).

- **Scénario — rate-limiting**
  - **Étant donné** un admin qui envoie de nombreuses invitations en peu de temps
  - **Quand** le seuil de rate-limit (sécurité §7) est franchi
  - **Alors** `429` (`Too Many Requests`) localisé.

#### US-INV-2 — Lister / révoquer les invitations
**En tant qu'**admin, **je veux** voir les invitations en attente et pouvoir les révoquer **afin de** garder le contrôle.

- **Scénario nominal — lister**
  - **Étant donné** un admin
  - **Quand** il consulte les invitations `pending` de F1 (via `GET /api/families/F1/members` volet invitations, ou endpoint dédié)
  - **Alors** il voit e-mail (déchiffré pour lui), rôle, droits proposés, date d'expiration, statut — **jamais** le token en clair.

- **Scénario — révoquer**
  - **Étant donné** une invitation `pending`
  - **Quand** l'admin la révoque
  - **Alors** `status=revoked`, le token devient inutilisable, `AuditLog` (`invitation.revoked`) écrit.

- **Scénario — autorisation refusée**
  - **Étant donné** un non-admin
  - **Quand** il tente de lister/révoquer
  - **Alors** `403`.

#### US-INV-3 / US-ONB-1b — Accepter une invitation
**En tant qu'**invité (utilisateur existant ou nouveau), **je veux** accepter l'invitation via le lien **afin de** rejoindre la famille avec le rôle et les droits prévus.

- **Scénario nominal — utilisateur déjà connecté**
  - **Étant donné** un token valide non expiré et un utilisateur authentifié dont l'e-mail correspond à l'invitation
  - **Quand** il `POST /api/invitations/accept` `{token:"<clair>"}`
  - **Alors** le serveur **hache le token** et le compare à `token_hash`, vérifie `status=pending` et `expires_at > now`
  - **Et** crée un `Membership` (`role`, `is_admin` selon proposition, `status=active`) + les `Right` issus de `proposed_rights` (normalisés selon le rôle)
  - **Et** l'invitation passe à `status=accepted` (**usage unique** : le token ne peut plus être réutilisé)
  - **Et** la nouvelle famille apparaît dans `GET /api/families` de l'invité
  - **Et** `AuditLog` (`invitation.accepted`, `membership.created`) écrit.

- **Scénario — invité sans compte (parcours d'inscription)**
  - **Étant donné** un token valide pour un e-mail sans compte
  - **Quand** l'invité ouvre le lien
  - **Alors** l'UI propose de **créer le compte** (réutilise US-AUTH-1 du Lot 0) puis enchaîne l'acceptation ; le `Membership` est créé après création/vérification du compte. *(L'acceptation peut requérir l'authentification : `POST /api/invitations/accept` est `public+token` mais lie au compte connecté.)*

- **Scénario — token expiré**
  - **Étant donné** un token dont `expires_at < now`
  - **Quand** il est soumis
  - **Alors** `410 Gone` (ou `422` `/errors/invitation-expired`) localisé ; aucune création ; l'invitation passe à `status=expired` (ou est déjà passée par la purge planifiée).

- **Scénario — token déjà utilisé (usage unique)**
  - **Étant donné** une invitation `accepted`
  - **Quand** le même token est resoumis
  - **Alors** `409`/`410` (`/errors/invitation-already-used`), aucune nouvelle adhésion.

- **Scénario — token révoqué**
  - **Étant donné** une invitation `revoked`
  - **Quand** le token est soumis
  - **Alors** `410`/`409` localisé, refus.

- **Scénario — token invalide / falsifié**
  - **Étant donné** un token qui ne correspond à aucun `token_hash`
  - **Quand** il est soumis
  - **Alors** réponse **non énumérante** (message générique « invitation invalide ou expirée »), pour ne pas révéler l'existence d'invitations ; `401`/`410` selon convention.

- **Scénario — e-mail du compte ≠ e-mail invité**
  - **Étant donné** un token pour `jean@ex.fr` et un utilisateur connecté `paul@ex.fr`
  - **Quand** il tente d'accepter
  - **Alors** `403` (`/errors/invitation-email-mismatch`) localisé ; l'invitation reste `pending`. *(Politique : l'acceptation est liée à l'e-mail invité pour éviter le détournement de lien.)*

- **Scénario — déjà membre actif**
  - **Étant donné** un invité déjà membre actif de la famille
  - **Quand** il accepte
  - **Alors** `409` (`already-member`) ; l'invitation est marquée `accepted`/`revoked` sans créer de doublon (unicité `(user_id, family_id)`).

- **Scénario — concurrence / double clic**
  - **Étant donné** deux requêtes d'acceptation simultanées avec le même token
  - **Quand** elles s'exécutent en parallèle
  - **Alors** une seule réussit (transaction + verrou/contrainte d'unicité), l'autre reçoit `409`/`410` ; un seul `Membership` est créé.

### 2.6 Liens entre familles

#### US-SET-2d / US-LINK-1 — Lier une famille à une autre
**En tant qu'**admin, **je veux** proposer un lien entre ma famille et une autre **afin de** préparer le partage RO de l'agenda (overlay au Lot 5).

- **Scénario nominal — proposition**
  - **Étant donné** un admin de F1 et l'identifiant (ou un code/handle) de F2
  - **Quand** il `POST /api/families/F1/links` `{linkedFamilyId:"F2"}`
  - **Alors** un `FamilyLink` est créé `{family_id:F1, linked_family_id:F2, status:"pending", created_by:...}`
  - **Et** la nature est **bidirectionnelle logique** : le lien est unique pour la paire {F1,F2} indépendamment du sens (voir §3.6) ; une requête depuis F2 voit le lien `pending`
  - **Et** `AuditLog` (`familylink.created`) écrit. **Aucun overlay** n'est activé (hors lot).

- **Scénario — acceptation par l'autre famille**
  - **Étant donné** un `FamilyLink` `pending` et un admin de F2
  - **Quand** il accepte le lien
  - **Alors** `status=active` ; le lien est exploitable par le Lot 5 (overlay). `AuditLog` (`familylink.activated`).

- **Scénario — lien déjà existant**
  - **Étant donné** un lien `pending` ou `active` entre F1 et F2
  - **Quand** un admin tente de recréer le même lien (dans un sens ou l'autre)
  - **Alors** `409` (`/errors/link-already-exists`), aucun doublon (contrainte d'unicité normalisée sur la paire).

- **Scénario — auto-lien interdit**
  - **Étant donné** F1
  - **Quand** un admin tente `linkedFamilyId:"F1"`
  - **Alors** `422` (`/errors/self-link-forbidden`).

- **Scénario — autorisation refusée**
  - **Étant donné** un non-admin de F1
  - **Quand** il tente de créer un lien
  - **Alors** `403` (`family_settings:edit` requis). De plus, l'acceptation côté F2 exige `family_settings:edit` sur F2.

- **Scénario — famille cible inexistante / non autorisée**
  - **Étant donné** un `linkedFamilyId` inexistant ou que l'admin n'a pas le droit de cibler
  - **Quand** il propose le lien
  - **Alors** réponse non énumérante (`404`/`422` générique) pour ne pas révéler l'existence d'autres familles ; politique : la cible doit être désignée par un **code de partage** plutôt que par devinette d'UUID (à préciser à l'implémentation ; au minimum, ne pas confirmer l'existence d'une famille étrangère).

#### US-LINK-2 — Délier deux familles
**En tant qu'**admin, **je veux** supprimer un lien **afin de** cesser le partage.

- **Scénario nominal**
  - **Étant donné** un lien `active` ou `pending` entre F1 et F2
  - **Quand** un admin de l'une des deux familles `DELETE /api/families/F1/links/{linkId}` (ou `DELETE …/links?linkedFamilyId=F2`)
  - **Alors** le lien est supprimé (ou `status` neutralisé), l'overlay (Lot 5) cesse d'être possible, `AuditLog` (`familylink.removed`) écrit
  - **Et** la déliaison vaut pour les deux familles (effet bidirectionnel).

- **Scénario — autorisation refusée**
  - **Étant donné** un non-admin
  - **Quand** il tente de délier
  - **Alors** `403`.

- **Scénario — lien inexistant**
  - **Étant donné** aucun lien entre F1 et F3
  - **Quand** un admin tente de le délier
  - **Alors** `404` (`/errors/link-not-found`) localisé.

#### US-LINK-3 — Lister les familles liées
**En tant que** membre actif, **je veux** voir les familles liées à ma famille active **afin de** comprendre quels foyers sont rattachés.

- **Scénario nominal**
  - **Étant donné** un membre actif de F1 et un lien `active` avec F2
  - **Quand** il consulte les liens (volet réglages ou `GET …/links`)
  - **Alors** il voit F2 (nom, statut du lien) **sans** accéder aux données internes de F2 (pas d'overlay, pas de membres de F2, pas d'enfants de F2). Seuls le nom et le statut sont exposés.

---

## 3. Algorithme(s)

> Conventions : toutes les écritures multi-entités s'exécutent dans une **transaction Doctrine** ; le fuseau par défaut est `Europe/Paris` ; `now` est l'horloge serveur (UTC en base, présenté localisé). Aucune date n'est codée en dur.

### 3.1 Résolution d'autorisation par Voter `(Membre, Ressource, Domaine) → none|view|edit`

Un **Voter par domaine** (`CustodyVoter`, `AgendaVoter`, `ChildrenNotesVoter`, `ExpensesVoter`, `ExchangesVoter`, `FamilySettingsVoter`) est interrogé par Symfony sur des attributs `VIEW` / `EDIT` (et opérations dérivées `CREATE`/`UPDATE`/`DELETE` mappées sur `EDIT`). La logique commune est factorisée dans un service `RightResolver`.

```
fonction resolveLevel(membership, domain) -> {none, view, edit}:
    # 0. Préconditions
    si membership == null OU membership.status != active:
        retourner none
    # 1. Forçage par rôle (priorité absolue)
    si membership.role == parent:
        si domain == family_settings:
            retourner (membership.is_admin ? edit : view)   # parent non-admin : lecture des réglages
        sinon:
            retourner edit                                   # parent = edit partout ailleurs
    # 2. Interdits enfant (forçage)
    si membership.role == child:
        si domain in {expenses, family_settings}:
            retourner none                                   # jamais d'accès dépenses ni réglages
        si domain == children_notes:
            niveau = lireRight(membership, domain) ou none   # none par défaut
            retourner min(niveau, view)                      # enfant ne peut pas éditer les notes
        si domain in {custody, agenda, exchanges}:
            niveau = lireRight(membership, domain) ou none
            retourner min(niveau, view)                      # enfant : view|none uniquement
    # 3. step_parent : droits granulaires lus tels quels, sauf interdits
    si membership.role == step_parent:
        si domain == family_settings:
            retourner none                                   # beau-parent : jamais family_settings
        si domain == expenses:
            niveau = lireRight(membership, domain) ou none   # autorisé en v2 si module actif ; inerte v1
            retourner (module_expenses_actif ? niveau : none)
        sinon:
            retourner lireRight(membership, domain) ou none

fonction voteOnAttribute(attribute, subject, token):
    membership = currentMembership(token.user, activeFamily(subject))   # via X-Family-Id
    si membership == null: retourner DENY
    domain = domainOf(this Voter)
    si subject est une ressource d'une AUTRE famille que activeFamily:
        retourner DENY                                       # isolation : jamais cross-family
    niveau = resolveLevel(membership, domain)
    selon attribute:
        VIEW   -> ACCÈS si niveau in {view, edit}
        EDIT   -> ACCÈS si niveau == edit
    sinon DENY
```

**Propriétés** :
- **Complexité** : O(1) par décision (une lecture du membership + une lecture des droits, idéalement préchargés). Les `Right` peuvent être indexés en map `domain → level` au chargement du membership pour éviter N requêtes.
- **Idempotence** : la résolution est une fonction pure des données ; appeler le Voter plusieurs fois donne le même résultat.
- **Sécurité par défaut** : tout chemin non couvert retourne `none`/`DENY` (deny-by-default). Le Voter refuse aussi toute ressource d'une famille différente de la famille active (défense en profondeur, en plus du résolveur de scope §3.4).
- **Priorité** : `rôle` (forçage parent/child) > `Right` explicite > défaut `none`.

### 3.2 Protection du dernier admin (retrait / rétrogradation)

Toute opération susceptible de réduire le nombre d'admins passe par `assertNotLastAdmin`.

```
fonction countActiveAdmins(family) -> int:
    retourner COUNT(Membership m WHERE m.family = family
                    AND m.status = active
                    AND m.is_admin = true)

fonction assertNotLastAdmin(membership_cible, operation):
    # operation ∈ {remove, demote_admin, demote_role}
    si membership_cible.is_admin != true:
        retourner OK            # retirer/rétrograder un non-admin n'affecte pas le quota
    # On verrouille en base les memberships admin de la famille (SELECT ... FOR UPDATE)
    admins = SELECT ... FOR UPDATE (memberships actifs admin de la famille)
    si count(admins) <= 1 ET membership_cible ∈ admins:
        lever LastAdminProtectedException   # -> 409 /errors/last-admin-protected
    retourner OK
```

Appelé **dans la transaction** de :
- `DELETE /api/memberships/{id}` (retrait ou auto-départ) — opération `remove`.
- `PATCH /api/memberships/{id}` avec `isAdmin:false` — opération `demote_admin`.
- `PATCH /api/memberships/{id}` avec `role` passant de `parent` à `step_parent`/`child` **alors que** le membre est admin (un admin est nécessairement parent — invariant `is_admin ⇒ role = parent` formalisé en §3.7 ; le rétrograder en rôle non-parent retire de facto l'admin) — opération `demote_role`.

**Concurrence** : le `SELECT … FOR UPDATE` sur les admins de la famille sérialise les tentatives concurrentes de retrait/rétrogradation, empêchant que deux requêtes simultanées retirent chacune « l'autre » admin et laissent la famille sans admin.

### 3.3 Cycle de vie d'une invitation

```
ÉTAT: pending -> {accepted | expired | revoked}

A. CRÉATION  (POST /api/families/{id}/invitations) — transaction
   1. Autoriser : Voter family_settings:edit sur la famille.
   2. Valider e-mail (format) ; normaliser (lowercase, trim).
   3. Si déjà membre actif (email -> user -> membership actif) -> 409 already-member.
   4. Si invitation pending non expirée pour (famille, email) -> révoquer l'ancienne (status=revoked).
   5. Normaliser proposed_rights selon role (forçage parent=edit ; interdits child ; step_parent libre sauf family_settings).
      5bis. Valider l'invariant is_admin => role=parent (§3.7) : si proposed_admin=true ET role != parent
            -> 422 admin-requires-parent-role (l'incohérence est bloquée dès la création de l'invitation,
            jamais à l'acceptation). proposed_admin n'est conservé que pour role=parent.
   6. Générer token_clair = random(>=256 bits, base64url).
   7. token_hash = hash(token_clair)            # hash résistant (ex. sha-256 + sel applicatif ou hash de mot de passe)
   8. expires_at = now + TTL (config, ex. 7 jours).
   9. Persister Invitation{email🔒, role, proposed_rights, token_hash, expires_at, status=pending, invited_by}.
  10. Dispatch message Messenger SendInvitationEmail{invitationId, token_clair en mémoire transitoire}.
      -> le worker envoie l'e-mail localisé contenant le lien ; token_clair n'est JAMAIS persisté.
  11. AuditLog(invitation.created). Réponse 201 sans token_clair.

B. ENVOI E-MAIL (worker Messenger)
   - Rendu template Twig localisé (locale de l'invité si connue, sinon fr-FR).
   - Lien: {APP_URL}/invitations/accept?token={token_clair}.
   - En cas d'échec SMTP: retry Messenger (backoff) ; au-delà, marquer en échec + AuditLog ; ne PAS exposer le token.

C. ACCEPTATION  (POST /api/invitations/accept {token}) — transaction
   1. token_hash = hash(token reçu).
   2. inv = SELECT Invitation WHERE token_hash = token_hash  FOR UPDATE.
   3. Si inv introuvable -> réponse non énumérante (410/401 générique).
   4. Si inv.status != pending -> 409/410 (already-used | revoked).
   5. Si inv.expires_at <= now -> inv.status = expired ; 410 invitation-expired.
   6. user = utilisateur authentifié (ou parcours d'inscription préalable, US-INV-3).
   7. Si lower(user.email) != decrypt(inv.email) -> 403 invitation-email-mismatch.
   8. Si membership actif (user, famille) existe déjà -> 409 already-member (inv.status=accepted).
   9. Créer Membership{user, famille, role=inv.role, is_admin=inv.proposed_admin?, status=active}.
  10. Créer Right[] depuis inv.proposed_rights (normalisés via RightResolver).
  11. inv.status = accepted.                    # usage unique : aucune réutilisation possible
  12. AuditLog(invitation.accepted, membership.created). 201 + membership.

D. RÉVOCATION  (action admin)
   - inv.status = revoked si inv.status == pending ; sinon no-op idempotent. AuditLog(invitation.revoked).

E. EXPIRATION  (purge planifiée — Scheduler/Messenger)
   - Périodiquement : SELECT Invitation WHERE status=pending AND expires_at <= now -> status=expired.
   - Idempotent ; journalisé. (La purge fine RGPD des invitations périmées relève du Lot 8 ;
     ici on se contente de marquer expired pour refuser proprement les acceptations tardives.)
```

**Sécurité** : le token en clair n'existe **jamais** en base (seul `token_hash` est stocké), ni dans les logs, ni dans les réponses API. La comparaison se fait sur le hash (résistance au timing : comparaison constante). Usage **unique** garanti par la transition `pending → accepted` sous verrou. **Non énumérant** : un token invalide et un token expiré donnent des messages indistinguables côté client lorsque c'est pertinent pour la sécurité.

### 3.4 Validation du scope famille (`X-Family-Id` vs memberships)

Implémentée comme un **résolveur de contexte** (event listener / argument resolver) exécuté **avant** les Voters, sur toute route scopée.

```
fonction resolveFamilyScope(request, user):
    si route NON scopée (auth/compte, accept invitation public, etc.):
        retourner null            # pas de scope requis
    familyIdHeader = request.header("X-Family-Id")
    si familyIdHeader absent:
        lever MissingFamilyScope        # 400 /errors/missing-family-scope
    si la route a un {id} de famille dans le chemin ET id != familyIdHeader:
        lever FamilyScopeMismatch       # 400 /errors/family-scope-mismatch
    membership = SELECT Membership
                 WHERE user = user AND family = familyIdHeader AND status = active
    si membership == null:
        lever ForbiddenFamilyScope      # 403 /errors/forbidden-family-scope (non énumérant)
    request.attributes.set("activeMembership", membership)
    request.attributes.set("activeFamily", membership.family)
    retourner membership
```

**Propriétés** :
- **Deny-by-default** : sans membership **actif** correspondant, accès refusé (un `invited` ne suffit pas).
- **Cohérence path/header** : empêche d'agir sur F1 (chemin) en se réclamant de F2 (header).
- **Non énumérant** : un `X-Family-Id` inconnu et un `X-Family-Id` d'une famille étrangère renvoient le même `403` générique (ne pas révéler l'existence d'autres familles).
- **Ordre** : scope résolu **avant** Voter ; le Voter s'appuie sur `activeMembership` déjà chargé (évite une seconde requête).
- **Complexité** : une requête indexée sur `(user_id, family_id, status)`.

### 3.5 Création de la première famille (atomique)

```
fonction createFamily(user, {name, type, ...}) — transaction:
   1. Valider name (non vide, longueur) et type ∈ {separated, intact}.
   2. family = new Family{name, type, timezone:"Europe/Paris" par défaut, settings:{}}.
   3. membership = new Membership{user, family, role:parent, is_admin:true, status:active,
                                  display_color: couleur par défaut §8}.
   4. Créer les Right du créateur : ignorés (parent => forcé edit) ; family_settings résolu via is_admin.
      (On peut matérialiser des Right explicites OU s'appuyer sur le forçage RightResolver ; choix:
       NE PAS matérialiser pour un parent — la résolution se fait par rôle, source unique de vérité.)
   5. AuditLog(family.created).
   6. Commit ; 201 {family, membership}.
```

### 3.6 Lien bidirectionnel logique entre familles

Le lien est **stocké une seule fois** mais **interprété dans les deux sens**. Pour garantir l'unicité indépendante du sens, on **normalise la paire** (clé canonique = paire ordonnée des deux UUID).

```
fonction canonicalPair(fa, fb) -> (low, high):
    retourner (min(fa, fb), max(fa, fb))      # ordre lexicographique des UUID

fonction createLink(actorAdmin, sourceFamily, linkedFamilyId) — transaction:
   1. Autoriser : family_settings:edit sur sourceFamily.
   2. si linkedFamilyId == sourceFamily.id -> 422 self-link-forbidden.
   3. (low, high) = canonicalPair(sourceFamily.id, linkedFamilyId).
   4. si EXISTS FamilyLink avec canonical(low,high) -> 409 link-already-exists.
   5. Persister FamilyLink{family_id:sourceFamily, linked_family_id:linkedFamilyId,
                           status:pending, created_by:actor,
                           pair_low:low, pair_high:high}.   # pair_low/high : index unique
   6. AuditLog(familylink.created).

fonction activateLink(actorAdmin, link) :
   - Autoriser : family_settings:edit sur la famille « destinataire » (l'autre que created_by côté).
   - link.status = active ; AuditLog(familylink.activated).

fonction removeLink(actorAdmin, link):
   - Autoriser : family_settings:edit sur l'une des deux familles du lien.
   - Supprimer (ou neutraliser) link ; effet bidirectionnel ; AuditLog(familylink.removed).

fonction linkedFamiliesOf(family):
   - SELECT FamilyLink WHERE (family_id = family OR linked_family_id = family) AND status = active
   - mapper vers « l'autre famille » de chaque lien.
```

**Propriétés** :
- **Unicité bidirectionnelle** : contrainte unique sur `(pair_low, pair_high)` empêche tout doublon quel que soit le sens de création. (Alternative documentée : une contrainte applicative `LEAST/GREATEST`.)
- **Aucun overlay** : `linkedFamiliesOf` ne renvoie que l'identité (nom, statut) de l'autre famille ; **aucune** donnée interne (membres, enfants, agenda) n'est exposée au Lot 1. L'overlay RO de l'agenda est strictement Lot 5.
- **Symétrie de la déliaison** : supprimer le lien le supprime pour les deux familles (un seul enregistrement).

### 3.7 Normalisation & validation à l'écriture d'un membership (invariant `is_admin ⇒ role = parent`)

Le cdc §2.2 pose qu'**un admin est nécessairement un parent** (l'attribut `admin` est « un attribut booléen additionnel porté par un parent »). Cet invariant n'est pas seulement déduit dans le raisonnement de `demote_role` (§3.2) : il est **formalisé comme une règle de validation à l'écriture**, appliquée à **toute** mutation d'un `Membership` — création (création de famille §3.5, acceptation d'invitation §3.3) **et** modification (`PATCH /api/memberships/{id}`, §2.4 US-MBR-2). Cette fonction s'exécute **dans la transaction**, **avant** `assertNotLastAdmin` (§3.2) et avant la persistance.

```
fonction normalizeAndValidateMembership(payload_role, payload_is_admin, payload_rights) -> (role, is_admin, rights) | erreur:
    # payload_* = valeurs demandées (création ou PATCH) ; tout champ absent garde la valeur courante.
    role     = payload_role
    is_admin = payload_is_admin ?? false

    # 1. INVARIANT central : is_admin implique role = parent.
    si is_admin == true ET role != parent:
        # Deux politiques possibles ; comportement choisi : REJET explicite (déterministe, traçable),
        # plutôt qu'une normalisation silencieuse de is_admin -> false.
        lever AdminRequiresParentRole          # -> 422 /errors/admin-requires-parent-role (localisé)
        # Alternative documentée : normaliser is_admin = false et journaliser (non retenue : masquerait
        # une intention erronée de l'appelant ; le rejet force une requête cohérente).

    # 2. Normaliser les droits selon le rôle (réutilise la logique RightResolver §3.1) :
    rights = normalizeRights(role, payload_rights)
    #   - role == parent      : proposed_rights ignorés ; résolution par rôle (= edit partout,
    #                            family_settings = view|edit selon is_admin) ; aucun Right matérialisé requis.
    #   - role == step_parent : family_settings -> none (forcé) ; expenses inerte v1 ; reste lu tel quel.
    #   - role == child       : expenses -> none (forcé), family_settings -> none (forcé),
    #                            children_notes plafonné à view (jamais edit),
    #                            custody/agenda/exchanges plafonnés à view ; valeurs hors borne normalisées
    #                            à la valeur permise et journalisées (cf. §2.4 « interdits enfant »).

    retourner (role, is_admin, rights)
```

**Points d'application** :
- **Création de famille** (§3.5) : le créateur est `role=parent, is_admin=true` → invariant trivialement respecté.
- **Acceptation d'invitation** (§3.3, étape 9) : `is_admin = inv.proposed_admin?` n'est honoré que si `inv.role == parent` ; une invitation `role ∈ {step_parent, child}` portant `proposed_admin=true` est **rejetée à la création de l'invitation** (mêmes 422, voir §3.3 étape 5 / §4.3) — donc aucune invitation incohérente ne peut atteindre l'acceptation.
- **`PATCH /api/memberships/{id}`** : combinaisons traitées —
  - `{isAdmin:true}` sur un membre déjà `parent` → OK.
  - `{isAdmin:true}` sur un `step_parent`/`child` **sans** passer aussi `role:"parent"` → `422 admin-requires-parent-role`, aucun changement.
  - `{role:"parent", isAdmin:true}` simultanés → OK (la requête est cohérente).
  - `{role:"step_parent"}` (ou `child`) sur un membre **actuellement admin** → relève de `demote_role` (§3.2) : l'admin est retiré de fait ; si c'est le **dernier** admin → `409 last-admin-protected` (la validation d'invariant passe, mais `assertNotLastAdmin` bloque).

**Propriétés** :
- **Déterminisme & traçabilité** : l'incohérence `is_admin=true` + rôle non-parent est **rejetée** (`422`) et non corrigée en silence, pour ne pas masquer une intention erronée de l'appelant. Le choix est documenté ; l'alternative (normaliser `is_admin=false`) est explicitement écartée.
- **Source unique de l'invariant** : la même fonction couvre création et modification ; impossible de créer en base un `Membership` `is_admin=true` avec un rôle ≠ `parent`. Un **CHECK applicatif** (et, si supporté, une contrainte de base `is_admin = false OR role = 'parent'`) double la garantie au niveau du schéma.
- **Ordre** : invariant `is_admin ⇒ parent` vérifié **avant** `assertNotLastAdmin` ; la protection du dernier admin ne s'applique qu'à des états déjà cohérents.

---

## 4. Cas limites (edge cases)

### 4.1 Données aux bornes (dates, fuseau, heure d'été/hiver, bissextile)

- **Fuseau par défaut** : à la création d'une famille, `timezone = "Europe/Paris"` si non fourni ; jamais codé en dur ailleurs. La date courante (`now`) provient de l'horloge serveur, jamais d'une constante (pas de 2026-06-23 en dur).
- **Expiration d'invitation au passage heure d'été/hiver** : `expires_at` est stocké en instant absolu (UTC). Le calcul `now + TTL` traverse correctement les changements DST ; un TTL de 7 jours reste 7×24 h quelle que soit la transition (pas de dérive d'une heure perçue côté UI car comparaison sur instants).
- **Année bissextile** : un TTL exprimé en jours/heures n'est pas affecté ; si un futur réglage exprimait une durée en « mois », utiliser une arithmétique calendaire robuste (non requis au Lot 1).
- **Heure/jour d'échange de garde** saisis dans les réglages : stockés tels quels (jour de semaine + heure locale famille) **sans interprétation** au Lot 1 ; aucune génération. Validation : heure 00:00–23:59, jour ∈ lundi..dimanche.
- **`expires_at` exactement égal à `now`** : considéré expiré (comparaison `<=`).

### 4.2 Concurrence & idempotence

- **Double acceptation simultanée d'une invitation** : transaction + `SELECT … FOR UPDATE` sur l'invitation + contrainte d'unicité `(user_id, family_id)` ⇒ une seule réussit ; l'autre `409/410`.
- **Retrait concurrent de deux admins** : `assertNotLastAdmin` sous `FOR UPDATE` sérialise ⇒ impossible de descendre sous 1 admin.
- **Création concurrente du même lien (sens opposés)** : contrainte unique `(pair_low, pair_high)` ⇒ une réussit, l'autre `409`.
- **Idempotency-Key sur POST issus de l'outbox mobile** (création de famille, invitation, lien) : si fourni, rejouer la même clé renvoie la **même** réponse sans créer de doublon (table d'idempotence ou contrôle applicatif). Sans clé, comportement standard.
- **Réinvitation du même e-mail** : régénération (ancienne révoquée) — idempotent au sens « un seul `pending` actif par (famille, email) ».
- **PATCH de droits concurrents** : last-write-wins sur la matrice de droits du membership ; l'`AuditLog` conserve l'historique avant/après pour traçabilité.

### 4.3 Autorisations (parent / beau-parent / enfant / admin / dernier admin)

- **Parent non-admin** : `edit` sur custody/agenda/children_notes/exchanges, mais **pas** `family_settings:edit` (lecture seule des réglages) ; ne peut ni inviter, ni gérer les membres, ni gérer les liens.
- **Beau-parent demandant family_settings** : toujours `none` (forcé), même si un admin tentait de lui accorder `edit` (normalisé à `none`).
- **Enfant** : `expenses` et `family_settings` toujours `none` ; `children_notes` jamais `edit` ; `custody/agenda/exchanges` au plus `view`. Jamais d'édition de structure (garde, membres, réglages, invitations, liens).
- **Admin qui se rétrograde / se retire en étant seul admin** : refusé (`409 last-admin-protected`).
- **Dernier admin via promotion d'un autre** : autorisé seulement si un autre membre **parent** est promu admin **avant** ou dans la même transaction.
- **Promotion enfant → parent** : possible (un enfant devenu majeur, p.ex.) ; à la promotion, droits forcés à `edit`, interdits levés. Inversement, parent → enfant interdit s'il est le dernier admin.
- **`is_admin=true` demandé sur un rôle non-parent** *(invariant `is_admin ⇒ role = parent`, §3.7)* : un `PATCH /api/memberships/{id}` portant `{isAdmin:true}` sur un membre `step_parent` ou `child` **sans** passer simultanément `{role:"parent"}` est **rejeté** par `normalizeAndValidateMembership` → `422 /errors/admin-requires-parent-role` (localisé), aucun changement (ni rôle, ni admin). À l'inverse, `{role:"parent", isAdmin:true}` envoyés ensemble forment une requête cohérente et sont acceptés. Même règle à la **création d'invitation** : `role ∈ {step_parent, child}` + `proposed_admin=true` → `422` au moment du `POST …/invitations` (l'incohérence ne peut donc jamais atteindre l'acceptation). Comportement **rejet** (et non normalisation silencieuse de `is_admin=false`) retenu pour traçabilité ; cf. §3.7.
- **Acceptation d'une invitation `role=parent` + `proposed_admin=true` alors que la famille n'a encore aucun autre admin** : cas de transition d'état **distinct** (et non un échec). L'invité devient `parent` + `admin` ; le nombre d'admins actifs passe de N à N+1. `assertNotLastAdmin` (§3.2) ne déclenche **jamais** ici (elle ne protège que les opérations qui *réduisent* le quota d'admins, pas une adhésion qui l'augmente). Cas concret : une famille séparée n'a temporairement plus qu'un parent fondateur admin et invite le second parent en `parent` + `proposed_admin=true` → à l'acceptation, la famille compte 2 admins ; aucun blocage. Variante : invitation `role=parent` + `proposed_admin=false` → l'invité devient `parent` non-admin (`family_settings=view`), le quota d'admins est inchangé, toujours ≥1 grâce à l'admin existant. `AuditLog(membership.created)` mentionne `is_admin` retenu.
- **Voter sur ressource d'une autre famille** : `DENY` systématique (isolation cross-family), même pour un admin de plusieurs familles.
- **Action sur un membership d'une autre famille** : `404`/`403` ; on ne révèle pas l'existence du membership étranger.

### 4.4 Scope famille (X-Family-Id)

- **Header absent** sur route scopée → `400 missing-family-scope`.
- **Header = UUID malformé** → `400` (validation de format) avant lookup.
- **Header pointant une famille inexistante** → `403` non énumérant (même réponse qu'une famille étrangère).
- **Header valide mais membership `invited`/retiré** → `403` (seul `active` ouvre le scope).
- **Incohérence path `{id}` vs header** → `400 family-scope-mismatch`.
- **Multi-onglets web** : chaque requête porte le `X-Family-Id` correspondant à la famille active de l'onglet (le sélecteur écrit le contexte) ; deux onglets sur deux familles ne se mélangent pas.
- **Routes non scopées** (`/api/me`, `/api/families` liste/création, `/api/invitations/accept`) : ne requièrent pas `X-Family-Id` ; ne pas l'imposer.

### 4.5 Valeurs vides / limites

- **Nom de famille** vide / espaces uniquement → `422`. Longueur max raisonnable (ex. 120) ; au-delà → `422`.
- **E-mail d'invitation** vide/mal formé → `422` ; e-mail très long → `422`.
- **`proposed_rights` vide** pour un beau-parent → tous domaines `none` par défaut (membre sans accès, valide mais inutile : avertir l'UI).
- **`display_color`** absente → couleur par défaut du design system §8 ; valeur non hexadécimale → `422`.
- **Famille sans aucun membre actif** : ne peut pas survenir si dernier admin protégé ; une famille vide est un état interdit (toute famille a ≥1 admin actif).
- **Liste de familles vide** pour un nouvel utilisateur → `[]`, UI d'amorçage.

### 4.6 Échecs externes (e-mail, API vacances, Mercure hors-ligne)

- **Échec d'envoi de l'e-mail d'invitation** : l'invitation est **déjà persistée** (`pending`) ; le worker Messenger retente avec backoff. L'admin voit l'invitation listée même si l'e-mail tarde ; possibilité de « renvoyer ». Le token reste valide jusqu'à expiration. Aucun token exposé dans les logs d'erreur.
- **API vacances scolaires** : **sans objet au Lot 1** (Garde = Lot 3). Aucun appel ici.
- **Mercure hors-ligne** : le Lot 1 n'a pas de mise à jour temps réel critique (pas de fil de messages, pas de garde). Les topics Mercure pertinents (échanges, garde, agenda, notes) appartiennent aux lots ultérieurs. Si une notification temps réel « membre ajouté » était souhaitée, elle est **best-effort** : l'absence de Mercure ne bloque aucune écriture (l'état canonique reste en base, rafraîchi au prochain chargement Turbo/poll).

### 4.7 Conflits offline (mobile)

- **Création de famille hors-ligne** : mise en outbox avec `Idempotency-Key` ; rejouée à la reconnexion ; en cas de doublon (clé déjà vue), le serveur renvoie l'état canonique.
- **Invitation hors-ligne** : idem outbox ; si l'e-mail invité est devenu membre entre-temps → le serveur répond `409 already-member`, signalé clairement côté mobile (l'opération outbox est marquée résolue/abandonnée, pas rejouée indéfiniment).
- **Modification de droits hors-ligne en conflit** : autorité serveur ; **last-write-wins** sur la matrice de droits ; l'utilisateur mobile voit l'état serveur après resynchro. Aucune fusion partielle silencieuse de la matrice.
- **Acceptation d'invitation hors-ligne** : déconseillée (nécessite la validation serveur du token) ; si tentée, l'outbox la rejoue en ligne ; un token expiré entre-temps → erreur claire.

### 4.8 Sécurité / RGPD

- **Énumération d'e-mails / familles / invitations** : messages non énumérants partout (login déjà couvert Lot 0 ; ici : token invalide vs expiré indistinguables si pertinent, famille étrangère = `403` générique, invitation introuvable = message générique).
- **Token d'invitation** : ≥256 bits d'entropie, base64url ; **jamais** stocké/loggué en clair ; seul `token_hash` en base ; comparaison résistante au timing ; usage unique ; expirant.
- **Champs chiffrés au repos** : `Invitation.email` 🔒, et (hérités) `User.full_name` 🔒, `User.phone` 🔒 affichés dans la liste des membres → déchiffrement applicatif AES-256-GCM à la lecture, jamais lisibles dans un dump SQL brut.
- **Rate-limiting** : sur création d'invitations (anti-spam) et sur tentatives d'acceptation (anti-bruteforce de token) → `429` au-delà du seuil.
- **AuditLog immuable** : toute action sensible (création/modif/retrait de membre, changement de droits/rôle/admin, invitation créée/révoquée/acceptée, lien créé/activé/supprimé, réglages famille modifiés) écrit une entrée append-only (`actor_user_id`, `family_id`, `action`, `entity_type`, `entity_id`, `ip`, `metadata`).
- **Mineurs** : aucune donnée d'enfant n'est exposée hors de la famille ; un lien de famille n'expose **aucune** donnée d'enfant (l'overlay du Lot 5 ne portera que l'agenda, jamais les profils enfants).
- **Pas de données de santé** : **aucun** champ santé n'est introduit dans ce lot (Family/Membership/Right/Invitation/FamilyLink ne contiennent aucune donnée de santé). Le bandeau anti-données-de-santé concerne l'écran Enfant (Lot 2), pas ce lot.
- **Registre des données personnelles** : ajouter une entrée pour `Invitation.email` (catégorie « contact », sensibilité 🔒, base légale, finalité « invitation à rejoindre une famille », durée = TTL invitation + rétention, destinataires) ; documenter que `Membership`/`Right`/`FamilyLink` référencent des personnes (lien user↔famille). Mettre à jour `/backend/docs/registre-donnees.md` ; le test CI de cohérence doit passer.
- **Suppression de compte (Lot 8)** : prévoir que l'anonymisation transformera l'auteur (`invited_by`, `created_by`, `author`) en « membre supprimé » sans casser les liens/audits ; le Lot 1 ne réalise pas la suppression mais ne doit pas empêcher cette anonymisation (pas de FK rigide non nullable bloquante).

### 4.9 i18n / localisation

- **Toutes les chaînes** UI (sélecteur de famille, écran Paramètres, gestion membres, formulaire d'invitation, récapitulatif des droits, messages d'erreur problem+json) et **tous les e-mails** (invitation) sont externalisés, format **ICU**, locale `fr-FR` livrée ; ajouter une langue = nouveau fichier de ressources sans changement de code.
- **E-mail d'invitation** rendu dans la locale de l'invité si connue (préférence utilisateur si compte existe), sinon `fr-FR`.
- **Pluriels / genre** ICU pour « X membre(s) », « X invitation(s) en attente ».
- **Dates affichées** (expiration d'invitation, `created_at`) localisées au fuseau famille (`Europe/Paris` par défaut), jamais en dur.
- **Erreurs RFC 7807** : `title`/`detail` localisés.

---

## 5. Modèle de données touché

> Base commune à toutes les entités : `id` (UUID), `created_at`, `updated_at`. 🔒 = chiffré au repos (AES-256-GCM applicatif, §5.3 cdc).

### 5.1 Entités créées par ce lot

#### Family
| Champ | Type | Notes |
|---|---|---|
| `name` | string | requis, non vide |
| `type` | enum `separated`\|`intact` | requis |
| `timezone` | string (IANA) | défaut `Europe/Paris` |
| `school_zone` | enum `A`\|`B`\|`C` (nullable) | saisi au Lot 1, exploité au Lot 3 |
| `academy` | string (nullable) | idem |
| `expenses_enabled` | bool | défaut `false` (v2) |
| `settings` | jsonb | modules activés, jour/heure d'échange de garde (`handover_weekday`, `handover_time`) stockés ici ou en colonnes dédiées ; **non exploités** avant Lot 3 |

#### Membership
| Champ | Type | Notes |
|---|---|---|
| `user_id` | FK User | requis |
| `family_id` | FK Family | requis |
| `role` | enum `parent`\|`step_parent`\|`child` | requis |
| `is_admin` | bool | additionnel ; cumulable avec `parent` |
| `child_id` | FK Child (nullable) | compte-enfant ↔ profil ; **résolu au Lot 2** (colonne créée nullable ici) |
| `display_color` | string (hex) | défaut §8 |
| `status` | enum `active`\|`invited` (+ état retiré logique) | seul `active` ouvre le scope |
| **Unicité** | `(user_id, family_id)` | un seul membership par couple |
| **CHECK** | `is_admin = false OR role = 'parent'` | invariant `is_admin ⇒ role = parent` (§3.7) au niveau base + validation applicative |

#### Right
| Champ | Type | Notes |
|---|---|---|
| `membership_id` | FK Membership | requis |
| `domain` | enum `custody`\|`agenda`\|`children_notes`\|`expenses`\|`exchanges`\|`family_settings` | |
| `level` | enum `none`\|`view`\|`edit` | parent : ignoré/forcé `edit` ; child : interdits forcés |
| **Unicité** | `(membership_id, domain)` | un niveau par domaine |

> Pour un membre `parent`, les `Right` peuvent ne pas être matérialisés (résolution par rôle = source unique de vérité, cf. §3.5). Le résolveur ne dépend jamais d'un `Right` pour un parent.

#### Invitation
| Champ | Type | Notes |
|---|---|---|
| `family_id` | FK Family | requis |
| `email` | string 🔒 | **chiffré au repos** ; normalisé lowercase |
| `role` | enum `parent`\|`step_parent`\|`child` | |
| `proposed_rights` | jsonb | matrice `domain→level` (normalisée selon rôle) ; `proposed_admin` optionnel |
| `token_hash` | string | **hash** du token ; jamais le token en clair |
| `expires_at` | timestamptz | instant absolu ; comparaison `<=` = expiré |
| `status` | enum `pending`\|`accepted`\|`expired`\|`revoked` | usage unique |
| `invited_by` | FK Membership/User | acteur (nullable-friendly pour anonymisation Lot 8) |
| **Unicité partielle** | un seul `pending` non expiré par `(family_id, email_hash)` | régénération si réinvitation |

> Indexer sur `token_hash` (unique) pour le lookup d'acceptation. L'unicité « un pending par (famille,email) » peut s'appuyer sur un hash déterministe de l'e-mail (`email_hash`) distinct du chiffrement réversible 🔒, afin de garder l'unicité sans déchiffrer.

#### FamilyLink
| Champ | Type | Notes |
|---|---|---|
| `family_id` | FK Family | famille initiatrice |
| `linked_family_id` | FK Family | famille cible |
| `status` | enum `pending`\|`active` | (+ supprimé logique à la déliaison) |
| `created_by` | FK Membership/User | acteur |
| `pair_low` / `pair_high` | UUID | paire canonique normalisée (`min`/`max`) |
| **Unicité** | `(pair_low, pair_high)` | empêche tout doublon quel que soit le sens |
| **Contrainte** | `family_id != linked_family_id` | pas d'auto-lien |

### 5.2 Entités référencées mais non créées ici

- **User** (Lot 0) : `full_name` 🔒, `phone` 🔒, `email`, `locale` — lus (déchiffrés) pour la liste des membres.
- **Child** (Lot 2) : `Membership.child_id` pointe vers lui (colonne nullable créée ici, FK activée/peuplée au Lot 2).
- **AuditLog** (Lot 0/RGPD) : alimenté par ce lot (immuable, append-only).
- **NotificationPref** (Lot 7) : non touché ici (l'e-mail d'invitation est transactionnel).

### 5.3 Champs chiffrés 🔒 introduits ou utilisés par ce lot

- `Invitation.email` 🔒 (introduit ici).
- `User.full_name` 🔒, `User.phone` 🔒 (hérités, affichés dans la liste des membres → déchiffrement applicatif à la lecture).

---

## 6. Endpoints API concernés

> Conventions transverses (cdc §6.1) : REST/JSON sous `/api` ; `Authorization: Bearer <jwt>` ; header **`X-Family-Id`** validé contre les memberships actifs sur toute ressource scopée ; erreurs **RFC 7807** (`application/problem+json`) localisées ; **`Idempotency-Key`** accepté sur les POST issus de l'outbox offline ; pagination Hydra API Platform sur les listes.

### 6.1 Familles, membres, droits, invitations, liens

| Méthode | Chemin | Scope `X-Family-Id` | Droit / Voter requis | Description |
|---|---|:--:|---|---|
| GET | `/api/families` | non | auth | Liste **mes** familles (memberships `active`). |
| POST | `/api/families` | non | auth | Créer une famille (le créateur devient `parent` + `admin`). |
| GET | `/api/families/{id}` | oui | `family_settings:view` (tout membre actif) | Détail / réglages. |
| PATCH | `/api/families/{id}` | oui | `family_settings:edit` (admin) | Modifier nom/type/modules/fuseau/zone/académie/jour-heure d'échange. |
| GET | `/api/families/{id}/members` | oui | membre actif | Lister les membres (+ invitations en attente si admin). |
| PATCH | `/api/memberships/{id}` | oui (de la famille du membership) | `family_settings:edit` (admin) ; self-`display_color` autorisé au propriétaire | Rôle, `is_admin`, droits, couleur. Protège le dernier admin. |
| DELETE | `/api/memberships/{id}` | oui | `family_settings:edit` (admin) **ou** auto-départ (non-admin) | Retirer un membre / quitter. Protège le dernier admin. |
| POST | `/api/families/{id}/invitations` | oui | `family_settings:edit` (admin) | Inviter (e-mail + rôle + droits) → e-mail async, token haché. |
| GET | `/api/families/{id}/invitations` | oui | `family_settings:edit` (admin) | Lister les invitations (sans token clair). *(Ajout pour US-INV-2.)* |
| DELETE | `/api/invitations/{id}` | oui | `family_settings:edit` (admin) | Révoquer une invitation `pending`. *(Ajout pour US-INV-2.)* |
| POST | `/api/invitations/accept` | non (public+token) | token valide + utilisateur authentifié dont l'e-mail correspond | Accepter → crée le `Membership` + `Right`. Usage unique, expirant. |
| GET | `/api/families/{id}/links` | oui | membre actif | Lister les familles liées (nom + statut, **sans données internes**). *(Ajout pour US-LINK-3.)* |
| POST | `/api/families/{id}/links` | oui | `family_settings:edit` (admin) | Proposer un lien (`pending`). |
| POST | `/api/families/{id}/links/{linkId}/activate` | oui | `family_settings:edit` (admin de la famille destinataire) | Activer un lien `pending`. *(Ajout pour US-LINK-1, cycle pending→active.)* |
| DELETE | `/api/families/{id}/links/{linkId}` | oui | `family_settings:edit` (admin de l'une des familles) | Délier (effet bidirectionnel). |

### 6.2 Compte (rappel, principalement Lot 0 ; utilisé par l'onboarding d'invitation)

| Méthode | Chemin | Scope | Rôle | Description |
|---|---|:--:|---|---|
| GET/PATCH | `/api/me` | non | auth | Profil, locale (pour rendre l'e-mail d'invitation localisé). |
| POST | `/api/auth/register` | non | public | Création de compte (parcours d'acceptation d'invitation d'un invité sans compte). |
| POST | `/api/auth/login` | non | public | Connexion (préalable à l'acceptation). |

### 6.3 Exemple de réponse d'erreur (autorisation refusée)

```http
403 application/problem+json
{ "type":"/errors/forbidden", "title":"Action non autorisée",
  "detail":"Droit 'family_settings:edit' requis." }
```

```http
409 application/problem+json
{ "type":"/errors/last-admin-protected", "title":"Dernier administrateur protégé",
  "detail":"Impossible de retirer ou rétrograder le dernier administrateur de la famille." }
```

---

## 7. Invariants & règles métier

Invariants du cdc / `CLAUDE.md` **pertinents pour ce lot**, à respecter sans exception :

1. **Scope famille obligatoire** : toute ressource métier est scopée via `X-Family-Id`, validé contre les memberships **actifs**. Jamais de requête métier non scopée (sauf routes compte/auth/acceptation publiques explicitement non scopées). Cohérence path/header imposée.
2. **Autorisation par Voters** : un Voter par domaine (`custody`, `agenda`, `children_notes`, `expenses`, `exchanges`, `family_settings`), niveaux `none|view|edit`, sur (Membre, Ressource). Vérifié sur **chaque** ressource. **Parent = `edit` partout** (sauf `family_settings` lié à `is_admin`). **Enfant** : jamais `expenses` (forcé `none`), jamais d'édition de structure, jamais d'édition des notes. **Beau-parent** : `family_settings` toujours `none`.
3. **Dernier admin protégé** : un dernier admin ne peut être ni retiré ni rétrogradé (ni par lui-même, ni par un autre). Sérialisation concurrente garantie.
3bis. **`is_admin ⇒ role = parent`** *(§3.7)* : un membre `is_admin=true` est nécessairement `role=parent`. Validé à **chaque** écriture de `Membership` (création de famille, acceptation d'invitation, `PATCH`) **et** à la **création d'invitation** (`proposed_admin` n'est honoré que pour `role=parent`). Toute combinaison `is_admin=true` + rôle non-parent est **rejetée** (`422 /errors/admin-requires-parent-role`), jamais normalisée en silence. Doublé d'un CHECK applicatif (et contrainte de base `is_admin = false OR role = 'parent'` si supportée).
4. **Champs chiffrés au repos** : `Invitation.email` 🔒 (+ `User.full_name`/`phone` 🔒 à la lecture) chiffrés AES-256-GCM ; un dump SQL brut ne révèle ni identités ni e-mails d'invitation. Token d'invitation **haché** (jamais en clair).
5. **Registre des données personnelles** : chaque nouveau champ personnel (`Invitation.email`, références personnelles de `Membership`/`FamilyLink`) ⇒ entrée de registre (attribut déclaratif sur l'entité + `/backend/docs/registre-donnees.md`). Le test CI de cohérence doit passer (fait partie de la DoD du lot).
6. **Pas de données de santé** : aucun champ santé introduit ici. (Le bandeau anti-santé concerne l'écran Enfant — Lot 2.)
7. **Dépenses = v2, masquées** : domaine `expenses` présent dans le modèle de Voter mais **inerte** ; aucun onglet ni API active ; enfant forcé `none`.
8. **i18n** : toutes les chaînes UI + e-mails externalisées dès le départ, format ICU ; `fr-FR` livrée ; ajout de langue sans changement de code. Fuseau par défaut `Europe/Paris`.
9. **Dates non codées en dur** : `now` = horloge serveur ; pas de 23 juin 2026 en dur ; expirations en instants absolus traversant correctement les transitions DST.
10. **Sécurité applicative** : tokens d'invitation à usage unique, expirants, hachés ; rate-limiting (invitations / acceptations) ; validation stricte des entrées ; autorisation par Voters sur chaque ressource ; messages non énumérants.
11. **AuditLog immuable** : journaliser changements de droits/rôles, invitations (créée/révoquée/acceptée), liens (créé/activé/supprimé), retraits de membres, modifications de réglages famille.
12. **`/infra/INSTALL.md`** : mis à jour si une dépendance change (peu probable au Lot 1, mais critère de DoD à vérifier).
13. **Priorité de la garde** : non applicable au Lot 1 (aucun calcul de garde), mais les **réglages** jour/heure d'échange/zone/académie sont **persistés** sans interprétation, prêts pour le Lot 3 ; ne rien régénérer ici.

---

## 8. Definition of Done (DoD)

Le Lot 1 est terminé lorsque **tous** les critères suivants sont verts :

### 8.1 Fonctionnel (conformité cdc §3.1 hors auth, §3.8 hors garde/dépenses)
- [ ] Création de première famille (séparée/unie) ; le créateur est `parent` + `admin`.
- [ ] Sélecteur de famille (web + mobile) ; bascule de famille active persistée ; `X-Family-Id` câblé partout.
- [ ] Écran **Paramètres › Compte & Famille** (volet famille) : édition nom/type/modules/fuseau/zone/académie/jour-heure d'échange (réservée admin).
- [ ] Gestion des membres : liste, rôles + matrice de droits, modification (rôle/admin/droits/couleur), retrait, auto-départ.
- [ ] Invitation e-mail : création (e-mail+rôle+droits), **récapitulatif des droits avant envoi**, e-mail localisé avec **lien à usage unique et expirant**, liste, révocation, acceptation créant le `Membership` + `Right`.
- [ ] Droits granulaires beau-parent/enfant appliqués **effectivement par les Voters** (parent forcé `edit` ; interdits enfant respectés).
- [ ] **Protection du dernier admin** vérifiée (retrait + rétrogradation, y compris auto-action et concurrence).
- [ ] **Liens entre familles** : proposer (`pending`), activer (`active`), lister (sans données internes), délier (bidirectionnel). **Aucun overlay**.

### 8.2 Tests (cdc §4.9, DoD générale §9)
- [ ] **Unitaires (PHPUnit)** : `RightResolver` / Voters (matrice rôle×domaine×niveau, forçages parent/child), `assertNotLastAdmin`, **`normalizeAndValidateMembership` (invariant `is_admin ⇒ role=parent`, §3.7 : rejet `422` sur `is_admin=true` + rôle non-parent, acceptation de `{role:parent, isAdmin:true}` conjoint)**, cycle de vie invitation (hash, expiration, usage unique), normalisation de la paire de lien.
- [ ] **Fonctionnels / API (Symfony/API Platform)** : scoping `X-Family-Id` (header manquant/étranger/mismatch/invited), droits refusés (`403`), dernier admin (`409`), **invariant admin/parent (`PATCH {isAdmin:true}` sur step_parent/child → `422 admin-requires-parent-role` ; invitation `role∈{step_parent,child}` + `proposed_admin=true` → `422`)**, **acceptation d'invitation `role=parent` + `proposed_admin=true` sans autre admin → l'invité devient admin, aucun blocage `assertNotLastAdmin`**, acceptation d'invitation (nominal + expiré + déjà utilisé + email mismatch + concurrence), création/déliaison de lien (doublon `409`, auto-lien `422`). Base de test isolée + fixtures (famille séparée multi-membres + famille unie liée).
- [ ] **E2E web (Playwright)** : parcours « créer une famille → inviter → accepter → vérifier l'accès » ; « ajuster les droits d'un beau-parent → vérifier accès refusé/accordé » ; « tenter de retirer le dernier admin → blocage » ; responsive desktop/mobile + accessibilité de base du formulaire d'invitation et du sélecteur de famille.
- [ ] **Flutter** : tests unitaires (mappers Membership/Right/Invitation/FamilyLink), tests de widgets (sélecteur de famille, écran membres, formulaire d'invitation), intégration de base (bascule de famille → entête `X-Family-Id`).

### 8.3 Qualité & livrables (DoD générale §9)
- [ ] **Migrations Doctrine** fournies (Family, Membership, Right, Invitation, FamilyLink + index/contraintes d'unicité : `(user_id,family_id)`, `(membership_id,domain)`, `token_hash` unique, `(pair_low,pair_high)` unique, unicité partielle invitation `pending` ; **CHECK `is_admin = false OR role = 'parent'`** sur Membership, §3.7 ; `family_id != linked_family_id` sur FamilyLink).
- [ ] **OpenAPI à jour** (API Platform) : tous les endpoints §6 décrits, schémas, codes d'erreur RFC 7807 ; client Flutter régénéré et aligné.
- [ ] **i18n (fr-FR)** câblée : toutes les chaînes UI + e-mail d'invitation externalisées (ICU) ; aucune chaîne en dur ; locale par défaut `fr-FR`, fuseau `Europe/Paris`.
- [ ] **Registre des données personnelles** mis à jour : entrée pour `Invitation.email` 🔒 (+ documentation des références personnelles `Membership`/`FamilyLink`) ; attributs déclaratifs sur les entités ; `/backend/docs/registre-donnees.md` régénéré ; **test CI de cohérence vert**.
- [ ] **`/infra/INSTALL.md`** vérifié/à jour (si une dépendance a changé).
- [ ] **Analyse statique propre** : PHPStan (niveau élevé), php-cs-fixer ; `dart analyze` / `dart format` côté mobile.
- [ ] **Chiffrement au repos vérifié** : un dump SQL brut ne révèle ni `Invitation.email`, ni `User.full_name`/`phone` ; token d'invitation absent en clair de la base et des logs.
- [ ] **AuditLog** : entrées append-only produites pour toutes les actions sensibles du lot (test fonctionnel de présence).
- [ ] **Conformité design system §8** : sélecteur de famille, écran Paramètres › Compte & Famille, gestion des membres, formulaire d'invitation et récapitulatif des droits, liste des liens — couleurs (terracotta/teal/crème, parent A/B), typographies (Bricolage Grotesque / Hanken Grotesk), formes (rayons, pastilles), layout desktop (sidebar 252px, master-détail Paramètres) et mobile (onglets bas, listes inset), conformes aux maquettes.
- [ ] **CA du lot (cdc §9)** : « droits effectivement appliqués par les Voters ; invitation acceptée crée le membership » — démontrés par tests.
