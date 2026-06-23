# CoParentPlanner

Application de coordination co-parentale (planning de garde, agenda, échanges, profils
enfants, et v2 dépenses), web responsive **et** mobile, avec synchronisation temps réel.

> Spécification : [`specs/cdc.md`](specs/cdc.md) (source de vérité) et
> [`specs/lots/`](specs/lots/) (lots d'implémentation détaillés).
> Avancement courant : [`PROGRESS.md`](PROGRESS.md).

## Monorepo

| Dossier | Contenu |
|---|---|
| [`backend/`](backend/) | Symfony 8.1 — API REST (mobile) + front web Twig/UX + Mercure + Messenger + workers |
| [`mobile/`](mobile/) | Flutter (iOS, Android) — client généré depuis l'OpenAPI |
| [`contracts/`](contracts/) | Schéma OpenAPI exporté par le backend → génération du client Dart |
| [`infra/`](infra/) | Docker Compose, `INSTALL.md` (bare-metal), CI/CD |

## Stack

- **Backend** : Symfony 8.1 + API Platform 4 + Doctrine ORM + Mercure + Messenger, PostgreSQL 16, Redis.
- **Front web** : Symfony UX server-rendered (Twig + Turbo + Stimulus + AssetMapper), aucun framework JS.
- **Mobile** : Flutter (Dart), client HTTP généré depuis l'OpenAPI, offline riche (Drift + outbox).

## Démarrage

Voir [`infra/INSTALL.md`](infra/INSTALL.md) pour l'installation **Docker** et **bare-metal**.

```bash
# Docker (dev)
cd infra && docker compose up
```

## Licence

Propriétaire.
