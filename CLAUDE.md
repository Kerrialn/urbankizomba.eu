# urbankizomba.eu

Community events calendar for the urban kiz scene in Europe. See README.md for
the stack and how to run it.

## Hard rule

**Never push, deploy, publish or take any action that leaves this machine
without the owner's explicit go-ahead in the current conversation.** Commit
locally; the owner pushes. Pushing to `main` triggers the release workflow.

## Conventions

- Run console commands inside the container: `docker compose exec php php bin/console …`
  (`DATABASE_URL` uses the `database` hostname, which does not resolve from the host).
- Schema changes go through Doctrine Migrations, never `doctrine:schema:update`.
  Generate with `doctrine:migrations:diff`, read the SQL, write a real description.
- Server-rendered Twig with Turbo and Stimulus. No SPA framework, no Live Components.
- Theme tokens live in `assets/app.css` under `@theme`. The token *names* are
  consumed by Symfony UX Toolkit and Flowbite; change values freely, never names.
- UI copy lives in `translations/messages+intl-icu.en.php`. Never hardcode a
  user-facing string in a template. English is the only active locale, but the
  routing and hreflang machinery is written for several (`App\Enum\Locale`).
- Legal pages (`templates/legal/`) are documents, not interface copy, and are
  edited whole. Bump the version constant in `LegalController` when they change.
- Guides are Markdown in `content/guides/`; see the README there.

## Domain

- `Event` covers everything on the calendar. `EventTypeEnum::SOCIAL` is
  recurring (schedule text + `lastConfirmedAt`); the other types are dated
  (`startsAt`, optional `endsAt`). `EventSubmissionDto::validate()` decides
  which pair is required. Only `EventStatusEnum::APPROVED` reaches a public
  page; every public query in `EventRepository` filters on it.
- A `City` typed into the submission form is created immediately but is
  invisible until an event in it is approved (`CityRepository::findActiveWithCounts`,
  `CityController::show` 404s an empty city). Admins tidy names in `/admin/city`.
- Editing a listing calls `Event::resubmit()`, which sends it back to PENDING.
- Slugs come from `SlugGenerator`: title + city + year for events, and are
  unique with a numeric suffix. Changing a slug breaks links; the admin form
  says so.
- The newsletter is double opt-in (`Subscriber::confirm()` via emailed token)
  and sent by hand with `app:newsletter:send`, one Messenger message per
  subscriber. Unsubscribe is a GET with the token, on purpose.

## Sign-in

Passwordless: `LoginCodeService` issues a six-digit code, hashed with the app
secret, valid ten minutes. There is no remember-me cookie, deliberately: every
signed-in page demands `IS_AUTHENTICATED_FULLY`, so guard on
`is_granted('IS_AUTHENTICATED_FULLY')`, never on `app.user` or `getUser()`.
`ROLE_ADMIN` is granted only by `app:user:promote`.

## Tests

Functional tests extend `WebTestCaseWithTransaction`, which rolls back after
every test and offers `user()`, `city()` and `event()` builders. Emails are
routed async, so tests read them off `messenger.transport.async` rather than
from the mailer. Generate unique emails with `uniqueEmail()`: the login-code
limiter is keyed by address and lives in a cache pool no rollback touches.
