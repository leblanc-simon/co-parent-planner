# web-e2e — Tests E2E web (Playwright)

Parcours de bout en bout du front web Symfony (Lot 0, DoD §8.2) :
**inscription → vérification e-mail (Mailpit) → connexion**, plus un smoke responsive
(desktop + mobile) et un cas d'identifiants invalides non énumérant.

## Prérequis

- Node 18+ et `npm`.
- Une stack web démarrée : app Symfony + PostgreSQL + **worker Messenger** (les e-mails
  d'auth sont asynchrones) + **Mailpit** (capture des e-mails, API de lecture).

## Installation

```bash
cd web-e2e
npm install
npx playwright install --with-deps chromium
```

## Variables d'environnement

| Variable | Défaut | Rôle |
|---|---|---|
| `E2E_BASE_URL` | `http://127.0.0.1:8000` | URL de l'app Symfony |
| `MAILPIT_API`  | `http://127.0.0.1:8025` | API Mailpit (lecture des e-mails) |

## Lancer

```bash
npm test                 # tous les projets (desktop + mobile)
npm run test:headed      # navigateur visible (debug)
npx playwright test tests/auth.spec.ts --project=desktop
```

## Démarrer la stack en local

La voie la plus simple est `infra/docker-compose.yml` (app + PG + Mercure + Mailpit + worker).
Sinon, en bare-metal (cf. `infra/INSTALL.md`), avec une base dédiée et le mailer pointant sur
Mailpit, démarrer `symfony serve`, un worker `messenger:consume async`, puis lancer Playwright.
En CI, ces services sont fournis par le job `web-e2e` de `.github/workflows/ci.yml`.

> Le worker Messenger **doit** tourner : sans lui, l'e-mail de vérification n'est jamais envoyé
> et le test échoue à l'étape Mailpit.
