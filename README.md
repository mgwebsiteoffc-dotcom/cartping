# CartPing

**WhatsApp Automation SaaS for Shopify merchants.**

A Laravel 13 + MySQL + Redis + Laravel Reverb + Horizon platform that lets Shopify
stores automate WhatsApp messaging and run an AI store agent, with full
click-to-WhatsApp attribution and analytics.

> Status: this is a production-shaped **code scaffold** for the full system. The
> core architecture (provider abstraction, event-driven pipeline, AI agent,
> templates, attribution, real-time inbox) is implemented as runnable code. You
> will need to install deps, configure credentials, and wire up real provider
> accounts (see *Getting started*). Parts marked *[stub]* below are placeholders
> for a production deployment.

---

## Highlights

- **Two auth modes**
  1. Public Shopify App via OAuth (`auth/shopify`)
  2. Direct signup with a manually-created Shopify Custom App + pasted access token (`auth/manual`)
- **WhatsApp provider abstraction** — Meta Cloud API **and** Whatify behind one
  `WhatsappProvider` interface; swap via a single config/connection.
- **Automated notifications** for Shopify events — order confirmation, shipping
  updates, abandoned-cart recovery (configurable delays), and **abandoned-browse
  recovery** via widget session data.
- **AI conversational store agent** — OpenAI **function calling** (15+ tools:
  order tracking, product search, inventory, checkout links, shipping, discount
  validation, returns, FAQ/RAG, escalation, …) driven through **OpenRouter**
  (GPT-4o + free reasoning models), using an **8-layer prompt**.
- **AI template generation** — multi-level approvals (internal → compliance →
  provider), compliance checking, A/B variant creation, **media/text header support**
  (image/document/video), an in-app **WhatsApp-style preview**, and a **live sync**
  button that pulls the latest template approval status back from Meta/Whatify.
- **Visual chat flow builder** — drag-and-drop style automation flows (like
  Aisensy/Wati): message / template / delay / condition / assign-human / end
  nodes, triggered by welcome, new message, or keyword. Runs are tracked and
  delays resume via the queue.
- **CTWA (Click-to-WhatsApp) ads** — full attribution from ad click →
  WhatsApp conversation → Shopify order → **Meta Conversions API** for ROAS.
- **Shopify Flow** hooks: custom triggers/actions are wired via webhook topics
  (`order_events`, message received, CTWA lead, opt-in) — see *Flow integration*.
- **Smart contextual widget** — 4 types (simple button, tooltip, chat widget,
  smart contextual) that change appearance/CTA by page type, plus **entry popup**
  (discount for number capture) and **exit popup** (exit-intent/inactivity, QR or
  capture form). Installed via ScriptTag or manual snippet.
- **Real-time inbox** (Laravel Reverb) — human agents take over from AI, view full
  Shopify customer data (orders, LTV, cart), assign, label, get AI-suggested replies.
- **Broadcast campaigns** — send an approved template (or free text) to an audience
  (all opted-in / by tag / by segment / manual numbers), schedule now or later, with an
  hourly send limit, per-recipient delivery stats, and a **calendar view** of scheduled
  broadcasts. Dispatched every minute by the scheduler.
- **Contacts & segments** — searchable contact list with consent state, tags,
  Shopify customer link, **CSV export/import** (upsert by WhatsApp number), and a
  **segment builder** with multi-condition groups (AND within a group, OR between
  groups) over fields like tags, consent, name, email, source, last-seen, orders
  and LTV. Segments are reusable in campaigns and show a live matching-contact count.
- **Analytics** — message performance, automation conversion, template A/B,
  CTWA ROAS, widget CTR, AI resolution rates, escalation reasons, revenue attribution.
- **Queued event-driven architecture** — Horizon with tiered queues
  (`high / default / low / webhooks`), idempotent webhook processing.
- **GDPR** — opt-in tracking + consent state on contacts, `cartping:prune` retention.
- **Onboarding wizard** — Shopify → WhatsApp → Agent → Widget → Template → Test.
- **Setup health checklist** — a `Health` page that shows what's configured vs.
  missing (Shopify, WhatsApp provider + connection, AI agent, approved template,
  products, plan/usage, queue cron, broadcast mode, onboarding), each with a
  one-click fix link. Essential on shared hosting.
- **Billing via Shopify Billing API** — plans/pricing live on the marketing site
  (`/pricing`), NOT in the app. Free plan = 100 messages/month + no flow builder;
  Builder plan (₹999/mo) unlocks the flow builder, campaigns and higher limits.
  Subscription charges are created/verified through Shopify's recurring
  application charge API; the merchant accepts the charge inside Shopify.
- **SaaS owner panel** — a platform-level dashboard at `/owner` for superadmin/admin
  staff to manage every store (assign plans, **enable/disable with a one-click
  toggle**), create subscription plans, view MRR by plan + Shopify charge ids, and
  manage platform/store users. Separately authenticated from merchant login.
  Disabled stores are blocked from signing in and from using the app (a clear
  "store disabled" page is shown).

---

## Architecture

```
Shopify webhooks ─▶ VerifyShopifyWebhook ─▶ OrderEventReceived ─▶ ProcessShopifyOrderEvent
                                                          │
Meta / Whatify ──▶ VerifyWhatsappWebhook ─▶ WhatsAppMessageReceived ─▶ ProcessInboundWhatsapp
                                                          └─────────▶ InboundPipeline ─▶ AgentOrchestrator
                                                                                       └────────▶ WhatsAppSender ─▶ Provider
Dashboard / Widget / CTWA ─▶ controllers ─▶ services ─▶ jobs (Horizon)
Inbox ◀─ Reverb broadcast ◀─ InboxMessageEvent
```

- `app/Services/Whatsapp/Contracts/WhatsappProvider` — provider interface.
- `app/Services/Whatsapp/Providers/{MetaCloudProvider,WhatifyProvider}` — adapters.
- `app/Services/Whatsapp/WhatsappManager` — resolves the right provider per store.
- `app/Services/Ai/OpenRouterClient` — reasoning + function-calling client
  (mirrors your reference `requests` script, incl. `reasoning_details` continuation).
- `app/Services/Ai/PromptBuilder` — the 8-layer prompt.
- `app/Services/Ai/AgentOrchestrator` — tool-call loop.
- `app/Services/Ai/Tools/*` — the tool registry (15+ tools).
- `app/Services/Automation/*`, `app/Services/Templates/*`, `app/Services/Attribution/*`,
  `app/Services/Analytics/*`, `app/Services/Widget/*`.
- `app/Jobs/*`, `app/Events/*`, `app/Listeners/*` — the queue pipeline.

---

## Getting started

Requirements: PHP ≥ 8.3, Composer, MySQL 8, Redis, Node 20+.

> **Note:** this repo does **not** commit a `composer.lock`, so the first command
> must be `composer update` (not `composer install`), which generates one.

```bash
# 1. Install dependencies  (run update first — there is no lock file yet)
composer update
npm install

### Windows / Laragon

Laravel **Horizon requires the `pcntl` and `posix` PHP extensions, which do not
exist on Windows** — so `composer install` would fail with
`requires ext-pcntl * -> it is missing from your system`.

This project's `composer.json` already handles that by declaring a
`config.platform` entry for `ext-pcntl` and `ext-posix`, so Composer resolves
and installs cleanly on Windows. Just run **`composer update`** the first time
(no special flags needed):

```bash
composer update
```

If you ever need to regenerate from scratch:
```bash
# remove the stale lock if a previous attempt left a broken one
del composer.lock   # (Windows)  /  rm composer.lock  (macOS/Linux)
composer update
```

> `config.platform` makes the packages install; but because Windows has no real
> `pcntl`, **Horizon's supervisor cannot spawn worker processes there.** Use plain
> queue workers instead (this is exactly what the `dev:win` script does):

```bash
composer run dev:win
# = php artisan serve
# + php artisan queue:work --tries=3 --queue=high,default,webhooks,low
# + php artisan reverb:start --debug
# + npm run dev
```

Run `php artisan horizon` only on a Linux/macOS machine (or a production server).

**Laragon prerequisites:** enable `pdo_mysql` + `mysqli` in
`laragon\bin\php\php-8.3.*\php.ini` (usually already on), and start the Redis
service from the Laragon menu (the app uses `predis`, a pure-PHP client, so no
`redis` PHP extension is needed).

### Required credentials
Set in `.env`:

| Variable | Purpose |
| --- | --- |
| `OPENROUTER_API_KEY` | AI agent + template generation (GPT-4o + reasoning model) |
| `SHOPIFY_API_KEY` / `SHOPIFY_API_SECRET` | Public app OAuth mode |
| `META_WHATSAPP_TOKEN` / `META_WHATSAPP_PHONE_ID` | Meta Cloud API defaults |
| `WHATIFY_API_TOKEN` | Whatify provider defaults |
| `META_ACCESS_TOKEN` + `META_PIXEL_ID` (`services.meta.pixel_id`) | Conversions API |
| `REDIS_*` | Queue/cache/Horizon/Reverb |

Per-store credentials are stored encrypted on `whatsapp_connections` (a merchant
can paste their own provider token instead of using the env defaults).

### Reference OpenRouter reasoning script
The demo command mirrors your Python script using `reasoning_details`:

```bash
php artisan cartping:ai:demo --task="create task to create mobile app, delivery date is 29 aug 2026"
```

It performs the two-turn reasoning continuation exactly like the reference script
(the model continues reasoning from where it left off and emits the requested JSON).

---

## Key flows

### 1. Inbound WhatsApp message → AI reply
1. Provider webhook POSTs to `/webhooks/whatsapp/{provider}`.
2. `VerifyWhatsappWebhook` validates + resolves store/connection.
3. `WhatsappWebhookController` normalizes events and dispatches `WhatsAppMessageReceived`.
4. `ProcessInboundWhatsapp` (high queue) runs `InboundPipeline`:
   resolve contact/conversation → persist message → broadcast → run AI agent.
5. `AgentOrchestrator` builds the 8-layer prompt, calls OpenRouter with tool schemas,
   executes tool calls, and the final reply is sent via `WhatsappSender` + broadcast.

### 2. Shopify event → notification
`/webhooks/shopify/{topic}` → verify HMAC → `OrderEventReceived` → `ProcessShopifyOrderEvent`
→ `AutomationService::onOrderEvent` → schedule `SendAutomationMessage` (immediate or
delayed) → `WhatsappSender`.

### 3. CTWA attribution
`/c/wa/{ad}` records a `ClickEvent` (fingerprint + cookie) and redirects to `wa.me`.
When a conversation/order appears, `AttributionService` matches by fingerprint and
fires a Meta CAPI `Purchase` for ROAS.

### 4. Widget
`public/js/widget.js` bootstraps config from `/widget/config?shop=…`, starts a
`WidgetSession`, tracks views/cart, captures opt-ins, and renders the selected type
+ popups. Abandoned-browse sessions feed `automations` (`abandoned_browse`).

---

## WhatsApp providers (Meta / Whatify)

- **Meta Cloud API** — uses a bearer **access token** + `phone_number_id` + `waba_id`.
- **Whatify** — uses an **API key** (+ optional **API secret**) from the Whatify
  dashboard. This app integrates Whatify's **External / Server API**
  (`https://whatify.in/api/v1/external`, `X-API-Key` header):

  | Action | Whatify endpoint |
  | --- | --- |
  | Send text | `POST /send-message` `{ phone, message }` |
  | Send template | `POST /send-template` `{ phone, template_name, body_params }` |
  | Template status | `GET /templates/{id}` |
  | Test connection | `GET /ping` |

  The connection UI (onboarding step 2 and **Settings → WhatsApp**) shows the
  correct fields per provider and lets you **switch between Meta and Whatify**
  instantly — just pick the provider and paste its credentials.

## Seeded users

`php artisan migrate --seed` creates platform + demo users:

| Email | Password | Role |
| --- | --- | --- |
| `superadmin@cartping.test` | `superadmin123` | superadmin |
| `admin@cartping.test` | `admin123456` | admin |
| `agent@cartping.test` | `agent123456` | agent |
| `owner@demo.myshopify.com` | `password123` | owner |
| `staff-admin@demo.myshopify.com` | `password123` | admin |
| `staff-agent@demo.myshopify.com` | `password123` | agent |
| `staff-viewer@demo.myshopify.com` | `password123` | viewer |

Roles: `superadmin` (platform), `admin`, `agent`, `viewer` (per-store), `owner`.

## Shopify OAuth (2026 policy)

As of **April 1, 2026**, Shopify requires **all new public apps** to use
**expiring offline access tokens** with a **refresh-token flow** (non-expiring
offline tokens show up as "deprecated offline tokens" in the API Health
monitor). This app implements that:

- `ShopifyOAuth::exchangeCode()` sends **`expiring => 1`** and stores the returned
  `access_token` (≈60 min), `refresh_token` (90 days) and both expiries on
  `shopify_connections`.
- `ShopifyClient` **auto-refreshes** the token a few minutes before expiry, guards
  concurrent refreshes with a per-shop lock (`shopify_refresh_{shop}`), persists
  atomically, and retries once on a 401.
- `config/shopify.php` uses API version **`2026-07`** (current). Bump `SHOPIFY_API_VERSION`
  in `.env` each quarter.
- **OAuth redirect URI:** `ShopifyOAuth::authorizeUrl()` derives `redirect_uri`
  from `config('app.url')` (or `SHOPIFY_REDIRECT_URI`). This URI **must be
  whitelisted** under the app's "Allowed redirection URL(s)" in the Shopify
  Partner dashboard — otherwise you get `Oauth error invalid_request: The
  redirect_uri is not whitelisted`. It must match exactly (scheme + domain + path
  `/auth/shopify/callback`).
- Embedded installs: the OAuth callback redirects back into the Shopify admin at
  the app URL, the app layout loads Shopify **App Bridge**, and
  `VerifyShopifySession` validates the **session token** (JWT, RS256 via the
  shop's JWKS) to auto-login installed merchants — so **no login page is shown
  inside the Shopify admin** once the app is installed.

> Custom App (manual-token) mode and merchant-created apps are **not** affected by
> the expiring-token rule — they keep using a pasted admin token as-is.

## Flow integration (Shopify Flow)

CartPing ships **Flow app extensions** so its custom triggers/actions appear in
the Shopify Flow builder. To register them you must deploy the app with the
**Shopify CLI** (this cannot be done from shared-hosting cPanel — run it locally
or on a dev machine):

```bash
# 1. Put your real app API key in shopify.app.toml (client_id)
# 2. Deploy the app + extensions:
shopify app config link
shopify app deploy
```

After deploy, install/update the app on the store (the Flow scopes are granted),
then in the Shopify admin → **Flow → Create workflow**, search for "CartPing"
and you'll find:

**Triggers (fire CartPing into the flow):**
- `WhatsApp message received` (`POST /webhooks/flow/trigger/message-received`)
- (CTWA lead / opt-in also available via the `topic` field)

**Actions (invoked by the flow):**
- `Send WhatsApp template` (`POST /webhooks/flow/action/send-template`) — sends an
  approved template to a customer phone (`{{ customer.phone }}`).

The runtime endpoints are in `FlowWebhookController` under `routes/webhooks.php`.

> The app's **own** visual flow builder (Flows → + New flow → builder) is
> independent of Shopify Flow and works immediately with no CLI deploy.

---

## Deploying to shared hosting (cPanel)

Shared cPanel hosts usually have **no Redis and no way to run a long-lived
WebSocket server**. `.env.example` now defaults to shared-hosting-safe values so
the app boots out of the box: `CACHE_STORE=database`, `QUEUE_CONNECTION=database`,
`BROADCAST_CONNECTION=log`. (Use the Redis/Reverb values on a VPS instead.)

The `package:discover` crash (`Pusher::__construct() auth_key null`) happens when
`composer update` runs before a `.env` exists — the config now ships safe
fallbacks for the Reverb app key/secret/id, so it no longer happens.

There are also two more shared-host fixes baked into the repo:

1. **`config/app.php` is the modern Laravel 13 layout** (empty `providers` /
   `aliases` arrays). The old-style full framework-providers list caused
   duplicate provider registration and broke console-command loading — which
   produced `There are no commands defined in the "package" namespace` during
   `composer update`. App providers live in `bootstrap/providers.php`.
2. **No app-booting Composer hook.** The `post-autoload-dump` hook is removed,
   so `composer install/update` never boots the framework (a shared host where
   the app isn't fully bootable mid-install would otherwise error — e.g. the
   `Target class [files] does not exist` you can hit if a hook boots the app).
   Laravel discovers packages lazily on the first real `php artisan` run.

Server steps:

```bash
cd ~/public_html/cartping

# 0. Pull the fixes
git pull origin arena/01a01e0f-cartping

# 1. Environment FIRST (so artisan commands have an APP_KEY/DB settings)
cp .env.example .env
php artisan key:generate

# 2. Dependencies — no boot hook now, so this succeeds even if the app can't
#    boot yet. If a composer.lock exists on the server use install, else update.
composer install --no-dev --optimize-autoloader
#   or (first time / no lock):  composer update --no-dev

# 3. Database — create a MySQL DB in cPanel, then edit .env:
#      DB_HOST=localhost   DB_DATABASE=your_db   DB_USERNAME=your_user   DB_PASSWORD=your_pass
#    and run migrations:
php artisan migrate --seed

# 4. Storage + deploy (clears stale route/config/view caches, migrates, re-caches)
php artisan storage:link
composer deploy
```

> **Always run `composer deploy` (or `php artisan optimize:clear`) after pulling
> new code.** If you skip it, Laravel keeps serving the **stale compiled route
> cache** from `bootstrap/cache/routes-v7.php`, which shows errors like
> `Route [settings.shopify] not defined` on pages that use new routes. The
> `deploy` script deliberately does **not** run `route:cache` (route caching is
> the usual cause of these "route not defined" surprises on shared hosting).

**Committing a `composer.lock` (recommended):** run `composer update` once on
your local machine (Laragon) so it generates `composer.lock`, then commit it:

```bash
composer update          # local, generates composer.lock
git add composer.lock
git commit -m "Add composer.lock for reproducible installs"
git push origin arena/01a01e0f-cartping
```

After that, the server can always use `composer install --no-dev`. (`composer.lock`
is not in `.gitignore`, so it will be committed.)

Frontend assets are served from `public/`, so point your domain document root at
`/cartping/public` (or keep the Laravel root and rely on the public `.htaccess`).
Make sure `storage/` and `bootstrap/cache/` are writable.

> **Queues on shared hosting:** with `QUEUE_CONNECTION=database`, queued jobs
> (webhooks, AI agent, automation sends) are stored in the `jobs` table. Add a
> cron job that runs every minute:
> `* * * * * /usr/bin/php /home/USER/public_html/cartping/artisan queue:work --once`
>
> **Real-time inbox (Reverb) is NOT available on shared hosting** — no WebSocket
> daemon. Set `BROADCAST_CONNECTION=log` (default) and use page refresh to view
> the inbox. Move to a VPS to enable live updates.

---

## Troubleshooting

- **`laravel/framework` `spatie/once` / "cannot coexist" during `composer update`** —
  caused by `minimum-stability: dev` and/or an old `laravel/tinker: ^2.0` pin
  dragging `laravel/framework dev-master`. This repo now uses
  `minimum-stability: stable` and `laravel/tinker: ^3.0` (matching the official
  Laravel 13 skeleton), so delete any stale `composer.lock` and run `composer update`.
- **`Target class [Laravel\Reverb\Console\Commands\StartServer] does not exist`** —
  only appears when Reverb is not installed yet (Composer failed). Reverb
  auto-registers its `reverb:start` command, so no manual provider registration
  is needed; the custom provider was removed. Re-run `composer update` first.
- **`There are no commands defined in the "package" namespace`** during
  `composer update`, and **`Target class [files] does not exist`** on `php
  artisan` — both mean the framework's default providers aren't being registered,
  because `config/app.php` had a `providers` key (either the old full list, or an
  empty `[]`). The fix is to omit the `providers`/`aliases` keys entirely; pull
  the latest code and run `rm -f bootstrap/cache/*.php`.
- **`Target class [files] does not exist`** on any `php artisan` command (and the
  earlier `There are no commands defined in the "package" namespace`). **Root
  cause:** a `'providers'` key present in `config/app.php`. Laravel 11+ falls back
  to its framework `defaultProviders()` (filesystem, auth, cache, validation,
  console commands, …) **only when the `app.providers` key is absent**. An even
  *empty* `providers => []` suppresses all framework providers and the app can't
  boot. `config/app.php` here deliberately has **no** `providers`/`aliases` keys
  (matching the official skeleton); app providers live in `bootstrap/providers.php`.
  After pulling this, run:
  ```bash
  rm -f bootstrap/cache/*.php
  composer dump-autoload
  php artisan key:generate
  ```
- **`Target class [App\...] does not exist`** — run
  `composer dump-autoload` (or `php artisan optimize:clear`).
- **`Route [xxx] not defined` (e.g. `Route [settings.shopify] not defined`)** —
  the route IS in `routes/web.php`, but the server is serving a **stale compiled
  route cache**. Run `composer deploy` (or `php artisan optimize:clear`). If you
  previously ran `php artisan route:cache`, stop doing that on shared hosting —
  it's what creates this error on every code change.
- **`Oauth error invalid_request: The redirect_uri is not whitelisted`** — the
  OAuth `redirect_uri` (default `{APP_URL}/auth/shopify/callback`) isn't listed in
  your Shopify app's **Allowed redirection URL(s)**. Fix:
  1. In `.env`, set `APP_URL` to your real domain (e.g. `https://cartping.rankboosterinfotech.in`).
     It **must not** be a placeholder like `https://cartping.yourdomain.com`.
  2. In the Shopify Partner dashboard → App setup → **Allowed redirection URL(s)**,
     add exactly `https://your-real-domain/auth/shopify/callback`
     (this app also accepts `https://your-real-domain/shopify/callback` as an alias).
  3. Run `composer deploy` (clears the config cache — a cached `APP_URL` from an
     old deploy is a very common cause of this).
  The redirect URI Shopify receives must match the whitelisted one exactly on
  scheme, host, port and path.
- **`Data truncated for column 'user_id'` / `SQLSTATE[01000]` on `sessions`** —
  `sessions.user_id` was created as a bigint but Store/User use UUID primary
  keys, so the UUID was truncated when writing the session. It's now a `string`
  in the framework migration, plus `2026_01_01_000024_alter_sessions_user_id_to_uuid_string.php`
  converts already-migrated databases. Run `php artisan migrate` to apply.
- **Products empty after "Sync from Shopify"** — the old sync dispatched a queued
  job that never ran without a queue worker on shared hosting. Sync now runs
  **synchronously** via `ShopifySyncService`, so click the button and it imports
  immediately. If it's still empty, the Shopify token is missing/revoked or the
  `read_products` scope isn't granted (check Settings → Shopify).

---

## Commands / scheduling

| Command | Purpose |
| --- | --- |
| `cartping:automations --run` | Dispatch due automation runs (scheduled every minute) |
| `cartping:metrics:rollup` | Roll up raw analytics (hourly) |
| `cartping:prune` | GDPR retention cleanup (daily) |
| `cartping:ai:demo` | OpenRouter reasoning demo |
| `horizon:list` / `reverb:start` | Queue workers / WebSocket server |

Schedules are declared in `bootstrap/app.php`; run
`php artisan schedule:work` in production.

---

## Tests

```bash
php artisan test
```

Unit tests cover the compliance checker, OpenRouter JSON extraction /
reasoning continuation, Meta webhook normalization, and the 8-layer prompt builder.

---

## Production notes

- Use `config('app.debug', false)`, HTTPS, a real DB.
- Register Shopify App Webhooks + ScriptTag via the merchant's connection
  (see `ShopifyClient::createWebhook` / `createScriptTag`).
- The `[stub]` areas (JWT session-token validation, embedding, some visual-builder
  UI polish, real embeddings for RAG, Cloud storage, billing) are intentionally
  left as hooks for a production build.
- Encryption requires `APP_KEY` (tokens are stored encrypted).

## License

Proprietary.
