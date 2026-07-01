# Registre des données personnelles — CoParentPlanner

> **Source de vérité vivante** (cdc §7.1). Décrit, pour chaque champ personnel, sa catégorie, sa
> sensibilité, sa base légale, sa finalité, sa durée de conservation et ses destinataires.
>
> **Obligation (DoD de chaque lot)** : tout ajout d'un champ personnel ⇒ attribut `#[PersonalData]`
> sur la propriété d'entité **ET** une ligne ici. Le test `App\Tests\Rgpd\PersonalDataRegistryTest`
> échoue en CI si un champ marqué personnel n'a pas d'entrée, ou si un champ chiffré 🔒 n'est pas
> marqué personnel.
>
> 🔒 = chiffré au repos (AES-256-GCM, clé hors base — cdc §5.3).
> La clé `Entité.champ` doit apparaître telle quelle dans la colonne « Champ ».

Dernière mise à jour : 2026-07-01 (Lot 5).

## Champs (Lot 0)

| Champ | Catégorie | Sensibilité | Base légale | Finalité | Conservation | Destinataires |
|---|---|---|---|---|---|---|
| `User.email` | Contact | Standard (citext, unique) | Exécution du contrat | Identifiant de connexion + contact transactionnel | Durée de vie du compte ; anonymisé à la suppression | Interne |
| `User.passwordHash` | Authentification | Haché (Argon2id, non réversible) | Exécution du contrat + obligation de sécurité (art. 32) | Authentification | Durée de vie du compte | Interne (jamais exposé) |
| `User.fullName` | Identité | 🔒 Chiffré | Exécution du contrat | Affichage du nom dans la famille | Durée de vie du compte ; anonymisé à la suppression | Membres habilités de la famille |
| `User.phone` | Contact | 🔒 Chiffré | Consentement (champ facultatif) | Coordonnée de contact optionnelle | Durée de vie du compte ; anonymisé à la suppression | Membres habilités de la famille |
| `Invitation.email` | Contact | 🔒 Chiffré | Intérêt légitime (coordination) ; consentement à l'acceptation | Invitation nominative à rejoindre une famille | Jusqu'à acceptation/expiration ; purge des invitations périmées (Lot 8) | Invité destinataire ; membres habilités |
| `AuditLog.ip` | Technique | Standard | Obligation légale / intérêt légitime (sécurité, art. 32) | Traçabilité et preuve d'accès (détection d'abus) | Selon la politique de rétention des journaux (Lot 8) | Administration technique |

## Champs (Lot 1)

| Champ | Catégorie | Sensibilité | Base légale | Finalité | Conservation | Destinataires |
|---|---|---|---|---|---|---|
| `Invitation.emailHash` | Contact | Pseudonyme (HMAC keyé, non réversible sans clé) | Intérêt légitime (intégrité des invitations) | Unicité « un pending par (famille, e-mail) » sans déchiffrer | Identique à l'invitation (acceptation/expiration ; purge Lot 8) | Interne (jamais exposé) |

## Champs (Lot 2)

| Champ | Catégorie | Sensibilité | Base légale | Finalité | Conservation | Destinataires |
|---|---|---|---|---|---|---|
| `Child.firstName` | Identité (mineur) | Standard (non chiffré — décision §5.4 #4) | Exécution du contrat | Identifier/afficher l'enfant | Durée de vie du profil ; purge RGPD (Lot 8) | Membres habilités de la famille |
| `Child.info` | Contenu | Standard (paires libres, **aucune donnée de santé**) | Exécution du contrat | Informations pratiques libres | Durée de vie du profil ; purge RGPD (Lot 8) | Membres habilités de la famille |
| `Note.text` | Contenu | 🔒 Chiffré | Exécution du contrat | Partage d'informations entre co-parents | Durée de vie de la note ; anonymisation auteur / purge (Lot 8) | Membres habilités (children_notes ≥ view) |

> `Child.birthDate` (date de naissance) : donnée d'un mineur ; portée par l'entité `Child` au même
> titre que `firstName`/`info` ci-dessus (non marquée `#[PersonalData]` séparément car simple date,
> couverte par la finalité du profil). Aucun champ médical typé n'est créé (invariant permanent).

## Champs (Lot 4 — Agenda)

> **Décision verrouillée (§5, §7.7-7.8)** : l'agenda **n'introduit aucun champ marqué `#[PersonalData]`
> ni chiffré 🔒**. `Event.title`, `Event.location` et `Event.description` sont du **texte libre de
> coordination familiale**, non typé et non sensible ; la catégorie d'agenda `sante` est un simple
> libellé (jamais une donnée de santé typée — invariant permanent). Documenté ici par exhaustivité.

| Champ | Catégorie | Sensibilité | Base légale | Finalité | Conservation | Destinataires |
|---|---|---|---|---|---|---|
| `Event.title` / `Event.location` / `Event.description` | Contenu (coordination) | Standard (texte libre, **aucune donnée de santé typée**) | Exécution du contrat (coordination parentale) | Planifier et documenter les activités des enfants | Durée de vie de l'événement ; purge RGPD (Lot 8) | Membres habilités de la famille (agenda ≥ view) |

### Références à des personnes (sans champ personnel direct)

Ces entités **référencent** des personnes via des clés étrangères mais ne stockent pas de champ
personnel en propre (donc pas d'attribut `#[PersonalData]`) ; l'anonymisation Lot 8 neutralisera
les références (`SET NULL`) sans casser les liens/audits :

- **`Membership`** : lie `User` ↔ `Family` (rôle, droits, couleur). Identité affichée déchiffrée à
  la lecture depuis `User.fullName`/`phone` 🔒 (déjà au registre).
- **`Right`** : niveau par domaine, rattaché à un `Membership` (pas de donnée personnelle directe).
- **`FamilyLink`** : `createdBy` → `User` (nullable, `SET NULL`) ; n'expose aucune donnée de l'autre famille.
- **`Invitation.invitedBy`** : `User` (nullable, `SET NULL`).
- **`Event`** (Lot 4) : `responsibleMembership` et `createdByMembership` → `Membership` (nullable, `SET NULL` :
  l'événement survit à l'anonymisation d'un membre) ; `family` (`CASCADE`).
- **`event_child`** (Lot 4) : table de liaison N-N `Event` ↔ `Child` (FK en `CASCADE` des deux côtés) ;
  aucune donnée personnelle propre (que des références).
- **`OverlayPreference`** (Lot 5) : opt-in d'affichage overlay par (`Membership`, `FamilyLink`, `view`).
  **Donnée technique de préférence**, non personnelle, non 🔒 ; FK en `CASCADE`. Aucun champ identité/contenu.

## Champs (Lot 5 — Familles liées / overlay)

> **Nouveau flux de destinataire** (cdc §7.1.1) : l'overlay expose, **en lecture seule et en projection
> minimale**, une partie des événements d'agenda d'une famille à une **famille liée active**. Aucun
> nouveau champ n'est créé ; c'est la **liste des destinataires** des champs d'événement surfacés qui
> s'élargit. Projection stricte (Lot 5 §3.6) : **seuls** `starts_at`/`ends_at`/`all_day`, `category` et
> `title` sont transmis ; `responsible`, `event_child`/enfants, `description`, `location`, `series_id`
> ne le sont **jamais** (anti-fuite testée).

| Champ | Catégorie | Sensibilité | Base légale | Finalité | Conservation | Destinataires |
|---|---|---|---|---|---|---|
| `Event.title` / `Event.category` / horaires (`starts_at`,`ends_at`,`all_day`) — **via overlay** | Contenu (coordination) | Standard (projection minimale RO) | Exécution du contrat (coordination inter-foyers) | Coordonner les activités des enfants entre les deux foyers | Durée de vie de l'événement ; purge RGPD (Lot 8) | Membres habilités (agenda ≥ view) **de la famille liée active** (RO) |

## Champs à venir (rappel, autres lots)

- `Note.text` 🔒 (Lot 2 — contenu), `Message.text` 🔒 (Lot 6 — contenu).
- `Child.*` (Lot 2 — infos pratiques libres ; **aucune donnée de santé typée**, cf. invariant).

> À chaque introduction, ajouter l'attribut `#[PersonalData]` + une ligne ci-dessus (DoD du lot).
