# kaltra.cz

Patient recall software for Czech medical practices. A practice uploads an export
from its practice-management system; Kaltra works out who is overdue a preventive
examination or a dispensary review (09532), sends the invitations, and reports how
many patients actually attended.

Operated by BASEPOINT GROUP S.R.O (IČO 29574153).

## Documentation

- [Database Migrations](docs/database-migrations.md) — Doctrine Migrations, the additive-only rule, and applying on production
- [Testing](docs/testing.md) — running the suite and provisioning the test database

## Tech Stack

- **Framework**: Symfony 7.4 with PHP 8.4
- **Database**: PostgreSQL 16
- **ORM**: Doctrine 3.3
- **Queue**: Symfony Messenger (Doctrine transport — the database is the queue, no Redis)
- **Scheduling**: Symfony Scheduler
- **Frontend**: Twig, Symfony UX (Turbo, Live Component, Stimulus), Tailwind CSS 4 + Flowbite, built with Vite
- **Back-office**: EasyAdmin
- **Payments**: Stripe
- **SMS**: 46elks via Symfony Notifier (EU-based — keeps the GDPR sub-processor list inside the EU)

No SPA framework. The due-list is a Live Component; nothing here needs React or Vue.

## Running locally

```bash
docker compose up -d
composer install
npm install && npm run build

docker compose exec php php bin/console doctrine:migrations:migrate
```

The app is served at https://localhost (Caddy, self-signed certificate).
Adminer is on http://localhost:8000, Mailpit on http://localhost:8025.

Run console commands **inside the container** — `DATABASE_URL` points at the
`database` service hostname, which does not resolve from the host:

```bash
docker compose exec php php bin/console <command>
```

## Data protection

This application processes patient data. Practices are the **controller**;
Kaltra is the **processor**. Before onboarding a practice there must be a signed
processing agreement in place, and the import must carry the minimum viable
fields — an identifier, a phone number and a due date. Do not import birth numbers
or medical history.

Diagnosis columns are the one exception, and a narrow one: they are read during
processing to work out which patients are under dispensary care and how often they
are due, and the code itself is never written to the database. What is stored is
the recall stream's label — "Diabetes", never "E11.8".

Nothing is ever sent without the practice explicitly approving the recipient list.
That approval is both the product's safety net against bad data and the thing that
makes each send the practice's decision rather than ours.
