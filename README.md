# Restaurant App

A Telegram Mini App used by restaurant staff for daily checklists, tasks,
calendar, feedback, and stoplist management. Staff log in via Telegram
(no separate username/password) and see a menu tailored to their role
(`staff`, `manager`, `director`, `admin`).

## Stack

- **Laravel 13** (PHP 8.3) — API + server-rendered Mini App shell
- **PostgreSQL 17** — primary database, persisted in a named Docker volume
- **Laravel Sanctum** — Bearer-token API authentication
- **Vanilla JS (ES modules) + Tailwind CSS v4** — Mini App frontend, served at `/app`
- **Docker Compose** — local stack: `db`, `app` (php-fpm), `web` (nginx), `node` (asset build)

## Project layout

```
backend/                  Laravel app
  app/                     Controllers, models, services, middleware
  database/migrations/     Schema
  resources/               Mini App frontend (Blade shell + JS/CSS)
  routes/                  api.php, web.php, telegram.php
docker/                   Dockerfiles & nginx config
docker-compose.yml        Local stack definition
restaurant app data.xlsx  Real user/restaurant data export (gitignored, PII — see below)
```

## First-time setup

1. **Copy environment files**

   ```sh
   cp .env.example .env
   cp backend/.env.example backend/.env
   ```

   The root `.env` only configures the Postgres container (database name/user/password
   for `docker-compose.yml`). `backend/.env` configures the Laravel app itself.

2. **Fill in `backend/.env`**

   - `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBHOOK_SECRET` — from
     [@BotFather](https://t.me/BotFather) (see [Telegram bot setup](#telegram-bot-setup) below).
     `TELEGRAM_WEBHOOK_SECRET` can be any long random string you choose.
   - `APP_URL` — the public URL of the app (used to build the Mini App link sent by the bot).

3. **Create the shared Docker network** (once per machine)

   The stack attaches to an external `main` network so it can sit alongside a
   shared reverse proxy / other stacks. Create it before the first `up`:

   ```sh
   docker network create main
   ```

4. **Start the stack**

   ```sh
   docker compose up -d
   ```

5. **Install PHP dependencies, generate the app key, and run migrations**

   ```sh
   docker compose exec app composer install
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate
   ```

6. **Build the frontend assets**

   ```sh
   docker compose --profile build run --rm node
   ```

   Re-run this command whenever frontend files under `backend/resources/` change.

7. Visit **http://localhost:8080/app**. Outside of Telegram (no `initData`), the app
   will show "Not registered" — this is expected; see [Roles & invites](#roles--invites)
   and [Testing in a browser](#testing-in-a-browser) for how to log in anyway.

## Persistence

Postgres data lives in the named Docker volume `pgdata` and survives
`docker compose down` and container rebuilds. Only `docker compose down -v`
(or manually removing the `pgdata` volume) will delete the database.

## Importing real data

`restaurant app data.xlsx` (at the repo root) contains a one-time export of
real restaurants, staff, checklist questions, and photo-task questions. It
**contains PII (names and Telegram chat IDs) and must never be committed to git**
— it's already excluded via `.gitignore`, along with its working copy at
`backend/storage/app/imports/`.

To import it:

```sh
cp "restaurant app data.xlsx" backend/storage/app/imports/restaurant-app-data.xlsx
docker compose exec app php artisan app:import-spreadsheet
```

The command is idempotent — re-running it updates existing rows instead of
duplicating them, and prints a summary of what was created/updated, plus any
conflicts (e.g. duplicate Telegram IDs) that need manual review.

## Telegram bot setup

1. Create a bot with [@BotFather](https://t.me/BotFather) and note its token and username.
2. Set `TELEGRAM_BOT_TOKEN` and `TELEGRAM_BOT_USERNAME` in `backend/.env`.
3. Register the webhook (replace `<APP_URL>`, `<TOKEN>`, `<SECRET>`):

   ```sh
   curl -F "url=<APP_URL>/telegram/webhook/<SECRET>" \
        -F "secret_token=<SECRET>" \
        "https://api.telegram.org/bot<TOKEN>/setWebhook"
   ```

   `<SECRET>` must match `TELEGRAM_WEBHOOK_SECRET` in `backend/.env`. The webhook
   endpoint validates both the URL path and the `X-Telegram-Bot-Api-Secret-Token`
   header, so requests without the correct secret are rejected.

4. In BotFather, set the Mini App / Menu Button URL to `<APP_URL>/app`.

> **Note:** Telegram requires the Mini App and webhook URLs to be served over HTTPS
> in production. `http://localhost:8080` is fine for local development, but a real
> deployment needs a TLS-terminating reverse proxy in front of `web`.

## Roles & invites

Roles are `staff`, `manager`, `director`, and `admin`. There is no public
sign-up — every account is created via an admin-issued invite link:

1. An admin generates an invite (`POST /api/admin/invites` or via `/app#/admin/invites`),
   choosing a role and optionally a restaurant.
2. The admin shares the resulting `https://t.me/<bot>?start=<code>` link with the new user.
3. The user opens the link, which starts the bot. The bot creates their account with
   the role/restaurant from the invite and replies with a button to open the Mini App.
4. Invite links are single-use and can optionally expire.

Admins manage restaurants, invites, and users at `/app#/admin`.

## Development

- **Frontend rebuild:** `docker compose --profile build run --rm node`
- **Artisan commands:** `docker compose exec app php artisan <command>`
- **Tests:** `docker compose exec app php artisan test`
- **Logs:** `docker compose logs -f app`

### Testing in a browser

The Mini App normally relies on Telegram's `initData` for login, which isn't
available when you open `http://localhost:8080/app` in a regular browser. When
`APP_ENV=local`, visit **http://localhost:8080/dev-login** instead — it lists
imported users and lets you open the app "as" any of them, with a freshly
signed `initData` injected automatically. This route only exists in local
environments and is not available in production.

## Deploying to production

The dev stack above bind-mounts source and runs with `APP_DEBUG=true`. For
production use the dedicated image and compose file, which bake code + built
assets into immutable images and cache config/routes/views at startup.

1. **Create the shared network** (once): `docker network create main`. A reverse
   proxy on this network terminates TLS and routes to the `web` service.

2. **Prepare `backend/.env`** from the production template and fill in real
   values (domain, secrets, a strong `DB_PASSWORD`):

   ```sh
   cp backend/.env.production.example backend/.env
   docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate
   ```

3. **Build and start**, then run migrations:

   ```sh
   docker compose -f docker-compose.prod.yml up -d --build
   docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
   ```

   Re-run with `--build` to deploy new code; the entrypoint re-caches config on
   each start.

The deployment is otherwise self-configuring:

- **Telegram webhook** is registered automatically on every container start
  (from `APP_URL` + `TELEGRAM_WEBHOOK_SECRET`) — no manual `setWebhook` call.
- **Database** persists in the `restaurant_app_pgdata` named volume across
  rebuilds and recreations; only `docker compose -f docker-compose.prod.yml
  down -v` (or deleting that volume) destroys it.
- The **scheduler** runs photo-question reminders and daily expired-token
  pruning. API tokens expire after `SANCTUM_TOKEN_EXPIRATION` minutes; the
  Mini App re-authenticates on every open.

## Security notes

- `restaurant app data.xlsx` and `backend/storage/app/imports/` contain real PII and
  are gitignored — never commit them.
- All `/api/admin/*` endpoints require a valid Sanctum token **and** the `admin` role.
- Telegram `initData` is validated via HMAC-SHA256 against the bot token and rejected
  if older than 24 hours.
- The bot webhook requires both the URL path secret and the
  `X-Telegram-Bot-Api-Secret-Token` header to match `TELEGRAM_WEBHOOK_SECRET`.
- Secrets (`APP_KEY`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, DB credentials)
  live only in `.env` files, which are gitignored.
