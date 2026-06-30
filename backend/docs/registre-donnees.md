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

Dernière mise à jour : 2026-06-30 (Lot 1).

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

### Références à des personnes (sans champ personnel direct)

Ces entités **référencent** des personnes via des clés étrangères mais ne stockent pas de champ
personnel en propre (donc pas d'attribut `#[PersonalData]`) ; l'anonymisation Lot 8 neutralisera
les références (`SET NULL`) sans casser les liens/audits :

- **`Membership`** : lie `User` ↔ `Family` (rôle, droits, couleur). Identité affichée déchiffrée à
  la lecture depuis `User.fullName`/`phone` 🔒 (déjà au registre).
- **`Right`** : niveau par domaine, rattaché à un `Membership` (pas de donnée personnelle directe).
- **`FamilyLink`** : `createdBy` → `User` (nullable, `SET NULL`) ; n'expose aucune donnée de l'autre famille.
- **`Invitation.invitedBy`** : `User` (nullable, `SET NULL`).

## Champs à venir (rappel, autres lots)

- `Note.text` 🔒 (Lot 2 — contenu), `Message.text` 🔒 (Lot 6 — contenu).
- `Child.*` (Lot 2 — infos pratiques libres ; **aucune donnée de santé typée**, cf. invariant).

> À chaque introduction, ajouter l'attribut `#[PersonalData]` + une ligne ci-dessus (DoD du lot).
