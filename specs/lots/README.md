# CoParentPlanner — Index des lots de spécification

Ce dossier rassemble les **11 spécifications détaillées d'implémentation**, une par lot, dérivées du cahier des charges [`../cdc.md`](../cdc.md) (source de vérité) et des invariants verrouillés de [`../../CLAUDE.md`](../../CLAUDE.md). Chaque fichier de lot développe un sous-ensemble du périmètre (§9 du cdc) en user stories Gherkin, algorithmes, modèle de données, API, cas limites et critères d'acceptation.

L'implémentation est **séquentielle** : chaque lot est livrable, testé, et conditionne les suivants. Chaque écran fonctionnel est livré **à la fois en web (Symfony UX / Twig / Turbo / Stimulus)** et **en mobile (Flutter)**.

> En cas d'ambiguïté de rendu visuel, les maquettes HTML du bundle Claude Design (`CoParentPlanner Desktop.dc.html`, variante mobile, `ios-frame.jsx`) font foi. En cas de divergence entre un lot et le cdc, **le cdc prime**.

---

## Tableau des lots

| # | Titre | Fichier | v1/v2 | Dépendances clés (prérequis) |
|---|---|---|:---:|---|
| 0 | Fondations | [lot-0-fondations.md](lot-0-fondations.md) | v1 | — (premier lot, greenfield) |
| 1 | Familles, membres, droits, invitations, liens | [lot-1-familles-membres-droits-invitations-liens.md](lot-1-familles-membres-droits-invitations-liens.md) | v1 | Lot 0 |
| 2 | Enfants & notes | [lot-2-enfants-notes.md](lot-2-enfants-notes.md) | v1 | Lot 0, 1 |
| 3 | Garde (moteur + vacances) | [lot-3-garde-moteur-vacances.md](lot-3-garde-moteur-vacances.md) | v1 | Lot 0, 1 |
| 4 | Agenda | [lot-4-agenda.md](lot-4-agenda.md) | v1 | Lot 0, 1, 2, 3 |
| 5 | Familles liées & overlay | [lot-5-familles-liees-overlay.md](lot-5-familles-liees-overlay.md) | v1 | Lot 0, 1, 3, 4 |
| 6 | Échanges (temps réel) | [lot-6-echanges-temps-reel.md](lot-6-echanges-temps-reel.md) | v1 | Lot 0, 1, 2 |
| 7 | Notifications e-mail | [lot-7-notifications-email.md](lot-7-notifications-email.md) | v1 | Lot 0, 1, 3, 4, 6 |
| 8 | PWA web, offline mobile & RGPD Manager | [lot-8-pwa-offline-rgpd.md](lot-8-pwa-offline-rgpd.md) | v1 | Lot 0–7 (dernier lot v1) |
| 9 | Dépenses | [lot-9-depenses-v2.md](lot-9-depenses-v2.md) | **v2** | Lot 0, 1, 2, 7, 8 |
| 10 | Push mobile | [lot-10-push-mobile-v2.md](lot-10-push-mobile-v2.md) | **v2** | Lot 0, 7 (et 1–6 transitivement) |

Notes :
- **Lots 0 à 8 = v1** (périmètre cdc §1.3). **Lots 9 et 10 = v2.**
- Le domaine de droit `expenses` et la catégorie de notification `expenses` sont **réservés dès la v1** (présents dans l'énumération, forcés `none` pour l'enfant) mais **inertes** tant que le module Dépenses (Lot 9) n'est pas activé par famille (`Family.expenses_enabled`).
- Le canal `push` de `NotificationPref` est **réservé dès la v1** mais **inactif** jusqu'au Lot 10.

---

## Définition de fini (DoD) générale — applicable à chaque lot

Reprise du cdc §9. Un lot n'est considéré comme livré que si **tous** ces critères sont verts :

- **Tests** : unitaires + fonctionnels (API) + E2E web (Playwright) verts, **+ tests Flutter** pour les écrans mobiles concernés.
- **Migrations** Doctrine fournies et réversibles.
- **OpenAPI** à jour (le client Dart est généré depuis ce schéma ; la CI échoue si le client dérive).
- **i18n** (fr-FR) câblée : toutes les chaînes UI + e-mail externalisées en ICU, fuseau `Europe/Paris` par défaut, aucune date codée en dur.
- **Registre des données personnelles** mis à jour si de nouveaux champs personnels sont introduits (attribut déclaratif sur l'entité + entrée dans `/backend/docs/registre-donnees.md`) ; un test CI de cohérence échoue sinon.
- **`/infra/INSTALL.md`** (Docker + bare-metal) à jour si une dépendance change.
- **Analyse statique propre** : PHPStan (niveau élevé) + `php-cs-fixer` ; `dart analyze` + `dart format`.
- **Écrans conformes au design system** §8 (tokens couleurs/typographies/formes).

Invariants transversaux à respecter dans **tout** le code (cf. `CLAUDE.md`) : scope famille obligatoire via `X-Family-Id`, autorisation par Voters sur chaque ressource, protection du dernier admin, priorité de garde `exception > vacances > base`, pas de données de santé (bandeau d'avertissement sur l'édition enfant), chiffrement applicatif AES-256-GCM des champs 🔒, conventions API (RFC 7807, pagination, `Idempotency-Key`).

---

## Graphe d'ordonnancement des lots

Ordre séquentiel imposé (cdc §9). Les flèches indiquent les dépendances bloquantes ; un lot ne démarre qu'une fois ses prérequis livrés.

```
                         ┌───────────────────────────────────────────┐
                         │                  Lot 0                     │
                         │                Fondations                  │
                         └───────────────────────┬───────────────────┘
                                                 │
                                                 ▼
                         ┌───────────────────────────────────────────┐
                         │                  Lot 1                     │
                         │  Familles · membres · droits · invitations │
                         │              · liens (FamilyLink)          │
                         └───┬───────────────┬───────────────┬───────┘
                             │               │               │
                ┌────────────▼───┐   ┌────────▼────────┐      │
                │     Lot 2      │   │     Lot 3       │      │
                │ Enfants &      │   │ Garde (moteur   │      │
                │ notes          │   │ + vacances)     │      │
                └───┬────────┬───┘   └───┬─────────┬───┘      │
                    │        │           │         │          │
                    │        └────────┐  │         │          │
                    │                 ▼  ▼          │          │
                    │            ┌──────────────┐   │          │
                    │            │    Lot 4     │   │          │
                    │            │   Agenda     │   │          │
                    │            └──────┬───────┘   │          │
                    │                   │           │          │
                    │                   ▼           ▼          │
                    │            ┌──────────────────────┐      │
                    │            │        Lot 5         │      │
                    │            │ Familles liées /     │      │
                    │            │ overlay (1,3,4)      │      │
                    │            └──────────────────────┘      │
                    │                                          │
                    ▼                                          │
             ┌──────────────┐                                  │
             │    Lot 6     │◄─────────────────────────────────┘
             │  Échanges    │  (dépend de 0,1,2 ; pas de 3/4/5)
             │  temps réel  │
             └──────┬───────┘
                    │
                    ▼
             ┌──────────────────────────────┐
             │            Lot 7             │
             │   Notifications e-mail        │  (consomme événements de 1,3,4,6)
             └──────────────┬───────────────┘
                            │
                            ▼
             ┌──────────────────────────────┐
             │            Lot 8             │
             │ PWA · offline mobile · RGPD   │  (dernier lot v1 ; consolide 0→7)
             └──────────────┬───────────────┘
                            │
            ┌───────────────┴───────────────┐
            ▼                               ▼
   ┌──────────────────┐           ┌──────────────────────┐
   │      Lot 9       │           │       Lot 10         │
   │  Dépenses (v2)   │           │  Push mobile (v2)    │
   │  (0,1,2,7,8)     │           │  (étend Lot 7)       │
   └──────────────────┘           └──────────────────────┘
```

Lecture rapide de la chaîne v1 : **0 → 1 → {2, 3} → 4 → 5**, avec **6** branché sur {0,1,2}, puis **7** (après 6) → **8**. La v2 (**9**, **10**) vient après le socle complet v1.
