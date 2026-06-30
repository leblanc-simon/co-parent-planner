# Revue de code — Lots 1 & 2

> Revue adversariale multi-agents (8 dimensions → vérification indépendante de chaque trouvaille,
> réfutation par défaut). Périmètre : ~97 fichiers source des Lots 1 (familles/membres/droits/
> invitations/liens) et 2 (enfants/notes). **12 trouvailles → 11 confirmées, 1 réfutée.**
> **Toutes les confirmées sont corrigées** (commit `b1444b6`). Tests : backend 134 / couverture 85,5 %,
> mobile 110, E2E 6/6.

Date : 2026-07-01. Méthode : workflow `review-lots-1-2` (dimensions RBAC, scope, crypto/RGPD,
concurrence, HTTP/API, Doctrine, web/Stimulus, logique/i18n).

---

## Défauts confirmés & corrigés

### P0 — Perte de données

**1. `notes.author_membership_id` en `ON DELETE CASCADE` → destruction silencieuse des notes au retrait d'un membre.**
Retirer un membre (hard delete du `Membership`, Lot 1) supprimait **en cascade toutes les notes** qu'il
avait rédigées sur les profils enfants — contenu appartenant à la famille, sans audit ni récupération.
Contredit l'invariant de rétention Lot 2 §4.8/§5.2 (« auteur anonymisé, fil préservé, PAS effacé »).
- **Correctif** : FK passée en **`ON DELETE SET NULL`** + `author_membership_id` **nullable** ; la note
  survit avec un auteur « Membre supprimé ». Migration `Version20260630220008`, `NoteResource` et
  `ChildNotesPolicy::canMutateNote` gèrent l'auteur null. Test `testNotesSurviveAuthorRemoval`.

### P1

**2. Acceptation d'invitation : audit `invitation.accepted` jamais écrit.**
Le cas d'enum `AuditAction::InvitationAccepted` était du code mort ; seul `membership.created` était
journalisé. Viole la spec §3.3 et l'invariant AuditLog (action sensible).
- **Correctif** : journalisation de `invitation.accepted` **dans la transaction** (atomique avec
  `membership.created`). Assertion ajoutée dans `InvitationLot1Test`.

### P2

**3. `Membership::effectiveLevel()` ignorait le statut du membership.**
Un membership non actif (invité/retiré) résolvait des droits pleins (parent → edit partout). Divergence
vs la précondition §3.1 ; fuite de privilèges latente (non exploitable en l'état, mais non gardée).
- **Correctif** : garde `if (!$this->isActive()) return None;` en tête. Test
  `testInactiveMembershipHasNoRightsEvenAsParent`.

**4. Verrou optimiste `Child.info` déclaré mais jamais armé.**
`#[ORM\Version]`, colonne et docstrings affirmaient une protection contre l'écrasement concurrent
inexistante (aucun `lock(OPTIMISTIC)`, version non exposée). Fausse sûreté + écrasement réel possible.
- **Correctif** : `version` exposée dans `ChildResource`, `expectedVersion` optionnel dans
  `UpdateChildRequest`, `em->lock(OPTIMISTIC)` → `409` (`ChildConflictException`). Test
  `testOptimisticLockRejectsStaleUpdate`.

**5. Audit écrit hors de la transaction métier (retrait/maj membre, acceptation).**
La mutation était committée puis l'`AuditLogger::log()` flushait séparément : un échec du second flush
laissait l'action sensible sans entrée d'audit (trou dans le journal append-only).
- **Correctif** : `AuditLogger::persist()` (sans flush) appelé **dans** `wrapInTransaction` →
  mutation + audit committés atomiquement (`RemoveMembershipProcessor`, `UpdateMembershipProcessor`,
  `AcceptInvitationProcessor`).

**6 & 7. `AgeCalculator` / `NoteDateValidator` décalaient la date de naissance d'un jour (fuseaux ≠ UTC).**
La date de naissance (calendaire, stockée à minuit UTC) était convertie via `setTimezone($fuseauFamille)` ;
pour un fuseau à l'ouest d'UTC, l'âge et la borne « note antérieure à la naissance » étaient faux d'un jour.
- **Correctif** : comparaison en **date calendaire brute** (`->format('Y-m-d')` sans conversion). Test
  `testWestOfUtcTimezoneDoesNotShiftBirthday`.

### P3

**8. E-mail en clair dans `audit_logs.metadata` (login verrouillé).**
Seul appel d'audit stockant un e-mail brut (donnée Contact) dans une colonne non chiffrée et non déclarée
au registre. (Non-fuite stricte car `User.email` est en clair par conception, mais entorse à la minimisation.)
- **Correctif** : `LoginProcessor` consigne désormais `email_hash` (blind index HMAC).

**9. `AuditLog` append-only : garde `PreRemove` absente.**
Seul `PreUpdate` protégeait ; une suppression applicative passait sans erreur malgré la garantie documentée.
- **Correctif** : hook `#[ORM\PreRemove]` symétrique. Test `testAuditLogCannotBeDeleted`.

**10. `SendInvitationEmail` non routé async.**
Absent du `routing` Messenger → traité en synchrone ; le chiffrement-en-transit du token était un no-op et
le découplage perdu (docstrings trompeurs).
- **Correctif** : `App\Message\SendInvitationEmail: async` ajouté à `messenger.yaml`.

**11. Réglages famille : impossible de vider un champ (academy/zone/jour/heure).**
Le contrôleur Stimulus filtrait les chaînes vides → la clé n'était jamais envoyée, l'ancienne valeur
persistait (faux air de bug serveur).
- **Correctif** : `settings_controller.js` envoie `null` pour les champs optionnels vidés.

---

## Trouvaille réfutée (1)

Une trouvaille a été **réfutée** par la vérification indépendante (faux positif / comportement déjà
correct ou couvert) et n'a donné lieu à aucune modification.

---

## Suivi

Correctifs livrés dans le commit `b1444b6` (« Revue Lots 1 & 2 : correction des 11 défauts confirmés »).
Journal détaillé : `PROGRESS.md` (entrée 2026-07-01).
