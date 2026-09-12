# urbankizomba.eu

A community calendar for the urban kiz scene in Europe: festivals, weekenders,
workshops, parties and the weekly socials in each city. Anyone can submit a
listing after signing in with their email; a listing goes live once an admin
has approved it. A monthly newsletter digests what is coming up.

## Tech stack

- **Framework**: Symfony 7.4 on PHP 8.4
- **Database**: PostgreSQL 16 (Doctrine ORM 3, migrations)
- **Queue**: Symfony Messenger on the Doctrine transport (the database is the queue)
- **Scheduling**: Symfony Scheduler, consumed by the worker container
- **Frontend**: Twig, Turbo and Stimulus, Tailwind CSS 4 + Flowbite, built with Vite
- **Back-office**: EasyAdmin at `/admin`
- **Auth**: passwordless, a six-digit code sent by email
- **Bots**: Cloudflare Turnstile on the public newsletter form

No SPA framework. Everything is server rendered.

## Running locally

```bash
docker compose up -d
composer install
npm install && npm run build

docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console app:cities:seed
docker compose exec php php bin/console app:user:promote you@example.com
```

The app is served at https://localhost:8443 (Caddy, self-signed certificate).
Adminer is on http://localhost:8001, Mailpit on http://localhost:8026, and
sign-in codes land there. Ports are offset from the defaults so the stack can
run beside other projects.

Run console commands **inside the container**: `DATABASE_URL` points at the
`database` service hostname, which does not resolve from the host.

Every email goes through Messenger and is sent by the `worker` container. If
Mailpit stays empty, check `docker compose logs worker`: a worker that came up
before the first migration created the queue table needs
`docker compose restart worker`.

For sample data on every page, load the fixtures instead of seeding:

```bash
docker compose exec php php bin/console doctrine:fixtures:load
```

That creates `admin@example.com` (admin) and `organiser@example.com`, six
cities, a handful of events and four socials.

`npm run dev` starts Vite with hot reload; `npm run build` writes
`public/build`, which is not committed.

## Day to day

| Task | How |
| --- | --- |
| Review submissions | `/admin/event`, filter by status. Approve and Reject are row actions. |
| Make someone an admin | `bin/console app:user:promote them@example.com` |
| Send the newsletter | `bin/console app:newsletter:send` (add `--dry-run` to count first). Queues one message per confirmed subscriber; the worker sends them. Refuses to resend to anyone who got one in the last 20 days. |
| Add a guide | Drop a Markdown file in `content/guides/`. See the README there for the frontmatter. |
| Add seed cities | Edit `SeedCitiesCommand::CITIES` and rerun `app:cities:seed`. It is idempotent. |

## Tests and checks

```bash
docker compose exec php composer test        # PHPUnit
docker compose exec php composer phpstan
docker compose exec php composer check-cs    # ECS; fix-cs to apply
docker compose exec php composer rector-dry
```

The test database is built from the entity mapping, not from migrations:

```bash
docker compose exec php php bin/console --env=test doctrine:database:create --if-not-exists
docker compose exec php php bin/console --env=test doctrine:schema:create
```

## Deployment

Pushing to `main` runs the tests, then builds three images (`php`, `worker`,
`caddy`) to GHCR and deploys them over SSH with `docker compose`
(`.github/workflows/release.yml`). Secrets are substituted into
`.deployment/docker-compose.production-template.yml`; every `__UK_*__`
placeholder there must exist as a repository secret, plus `DEPLOY_HOST`,
`DEPLOY_USERNAME` and `DEPLOY_PRIVATE_KEY`.

Migrations are **not** run by the deploy. After a release that carries one:

```bash
ssh $DEPLOY_HOST
cd /urbankizomba.eu
docker compose -p urbankizomba exec php bin/console doctrine:migrations:migrate --dry-run
docker compose -p urbankizomba exec php bin/console doctrine:migrations:migrate --no-interaction
```

Posters live in the `uploads` volume, shared between `php` (writes) and
`caddy` (serves).
