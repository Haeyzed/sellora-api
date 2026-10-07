# Sellora API: architecture guide

**Sellora** is a global multi-tenant e-commerce platform: merchants anywhere in the world sign up, get their own store, and sell online. This repository is the **Sellora API**, built on Laravel 13. Storefronts and dashboards are separate frontend apps that consume this API. It is a **modular monolith**: one codebase, with strict internal boundaries between the core, plan-gated modules, and plan-gated integrations.

Follow this guide for every change. If a request conflicts with it, say so before writing code.

---

## 1. Projects

- **`sellora-api`** (this repository) is the new, clean build of the Sellora platform. Everything here follows this guide.
- **`tenant-ecommerce-api`** is the legacy project, with poor architecture. Treat it as a **read-only reference for business logic only**. Port behaviour and rules from it, but never copy its folder structure, class names or naming habits. Never modify it.

**Never copy the legacy project blindly.** Not its architecture, not its code, not its patterns. Use it only to understand *what the business needs* (the rules, the flows, the edge cases). Then design and write the solution from scratch, based on:

1. the installed skills (section 2),
2. this guide, and
3. current best practice for multi-tenant e-commerce.

The legacy code may contain bugs, security holes, shortcuts and wrong business rules. If a legacy rule looks wrong, unsafe or inconsistent, don't port it. Flag it, explain why, and ask me what the correct behaviour should be.

**Naming the brand in code.** Use "Sellora" only where the brand genuinely appears: the app name in config, API documentation titles, email and notification templates, and user-facing messages. Never put "Sellora" in class names, namespaces, table names or folder names (`App\`, not `Sellora\`; `products`, not `sellora_products`). Read the brand name and platform domain from config (`config('app.name')`, a platform-domain config key), so a rebrand or domain change never needs a code change.

---

## 2. Before every task: check the skills first

1. List the skills in `.claude/skills/` and any package guidelines (for example, from Laravel Boost, if installed).
2. Read every skill relevant to the task: the Scramble skill for anything touching endpoints, the tenancy skill for anything tenant-related, and a package's skill before using that package.
3. **Always follow the Laravel best-practices skill** for every piece of code, alongside this guide. Package skills cover their package; the Laravel best-practices skill covers everything else.
4. Follow them. A skill's documented pattern takes priority over generic Laravel habits. If a skill conflicts with this guide, or two skills conflict with each other, stop, explain the conflict, and ask.
5. Check `composer.json` for installed packages and versions, and use the documented API for that version. Never invent methods.
6. Don't add a new package without asking. Say what it solves and what the alternative would be.
7. Briefly state which skills you used at the start of your response.

### 2.1 Installed packages and how they fit

**Before using a package, read and follow its skill if it has one.** Packages without a skill (for example `stancl/tenancy`, `brick/money`, `propaganistas/laravel-phone`, `spatie/laravel-activitylog`, `owen-it/laravel-auditing`, Sanctum) are used according to their official documentation for the installed version, checked through Boost's `search-docs`. The legacy project's usage of these packages is not a reference. The notes below only cover how each package fits this architecture; the skill decides how to use it.

| Package | Used for | Where it fits |
|---|---|---|
| `stancl/tenancy` | Multi-tenancy: tenant identification, database switching, and isolation of cache, storage, queues and Redis | Configured in `config/tenancy.php` and `App\Providers\TenancyServiceProvider`. Glue code lives in `App\Shared\Tenancy`. The tenant model lives in `App\Landlord\Tenancy\Models`. Multi-database mode: one database per tenant. |
| `dedoc/scramble` | Generating the API documentation from code | Reads Form Requests, Resources, return types and controller docblocks. See section 11. |
| `spatie/laravel-permission` | Roles and permissions for platform admins **and** store staff | Used on both sides, kept completely separate. **Landlord:** `Landlord\Identity`, tables in the central database, guard `platform` (for example Super Admin, Support, Finance). **Tenant:** `Tenant\Identity`, tables in each tenant database, guard `staff` (for example Owner, Manager, Cashier). Roles and permissions are always scoped to their guard, so a platform role can never be assigned to a store user or the reverse. The permission cache must be tenant-isolated, and the landlord cache must not collide with any tenant's. |
| `spatie/laravel-sluggable` | URL slugs for products, categories, brands | On the models that need slugs. Slugs are unique per tenant automatically, because each tenant has its own database. |
| `spatie/laravel-medialibrary` | Product images and other uploads | Tenant database and tenant-isolated storage. Image conversions run on tenant-aware queues. Define collections and conversions on the model. |
| `spatie/laravel-activitylog` | Human-readable business activity feed ("Ada cancelled order #1042") | Logged explicitly from **Actions**, not automatically on every model save. Causer is the authenticated staff user. |
| `owen-it/laravel-auditing` | Field-level change history for sensitive data (old value → new value) | Only on sensitive models: prices, stock levels, payments, refunds, roles, store settings, and on the platform side roles, plans and feature grants. Not on every model. **Auditing runs for console and queued changes too** (`audit.console` on), so no change escapes the trail because it ran in a job or command. |
| `maatwebsite/excel` | Imports and exports | In the owning domain's `Imports/` and `Exports/` folders. Large files are queued and chunked, and queued imports and exports must be tenant-aware. |
| `nnjeim/world` | Countries, states, cities, currencies, timezones, languages | **Global reference data. Install it in the central (landlord) database, not in every tenant database.** Tenants read it through the central connection. It's read-only from the tenant side. |
| `brick/money` | Exact money maths with correct decimals for every currency | Wrapped by `App\Shared\Money\Money`. Domains use our `Money` value object, never `brick/money` classes directly, so the library stays swappable. All rounding goes through it. |
| `spatie/laravel-translatable` | Translatable customer-facing content | On tenant models with customer-facing text (section 3.3). Translations live in JSON columns in the tenant database; validation and Resources handle the store's enabled locales. |
| `propaganistas/laravel-phone` | Validating and formatting international phone numbers | Validate phone input in Form Requests with the country context, and store numbers in E.164 format (section 3.3). |
| `laravel/reverb` | Real-time updates over WebSockets (**approved but deferred**: install only once a stable release is compatible with this project's dependencies; never force it) | Broadcasts such as order status changes, new orders on the staff dashboard, and live delivery tracking. **Every channel name includes the tenant**, and channel authorization checks both the tenant and the correct guard (section 10), so no one can subscribe to another store's or another user's events. Broadcast after the database transaction commits. Never broadcast secrets or more personal data than the screen needs. |
| `laravel/scout` | Product and customer search | Indexes must be **tenant-isolated**: prefix every index with the tenant, or use the engine's tenant filtering, so a search never returns another store's records. Start with Scout's database engine; ask before choosing a hosted engine such as Meilisearch or Typesense. Indexing runs on tenant-aware queues. |
| `sentry/sentry-laravel` | Error and performance tracking in production | Tag every event with the tenant ID, guard and environment so problems can be traced to a store. **Never send personal data, tokens, passwords or request bodies**; configure scrubbing and keep `send_default_pii` off. |
| `laravel/telescope` | Debugging requests, queries, jobs and events | **Local development only.** Install as a dev dependency, register it only in the local environment, and never enable it in production or staging with real data. |

**Activity log vs auditing: don't overlap them.** Both can record model changes, and running both automatically on the same models doubles the writes and gives two conflicting histories. Activity log answers "what happened in the business, and who did it?" Auditing answers "exactly which fields changed, from what to what?" Keep each to its own job as described above.

**Reference data exception:** world data is the one case where Tenant code may read from the central database. Access it only through the package's models or a thin wrapper in `App\Shared\Geography`, never by writing raw queries against the central connection.

### 2.2 First-time setup: install and publish everything first

On a fresh project, **the first task is installing and configuring all dependencies**, before any feature code. Do it in this order, and stop for my review at the end.

**1. Check compatibility before installing.**
- For each package, confirm it officially supports Laravel 13 and **PHP 8.4**, the project's PHP version.
- `composer.json` requires `"php": "^8.4"` and sets `config.platform.php` to the installed 8.4 version, so Composer never picks a package release that needs PHP 8.5. Move to PHP 8.5 only when I decide to, after every package's CI tests it.
- **The database is PostgreSQL** (central and tenant). PostgreSQL-specific features are allowed where the query builder has no equivalent (for example advisory locks and functional indexes), always with bound values.
- Never install with `--ignore-platform-reqs`, never force a dev or beta version, and never downgrade Laravel to make a package fit. If a package has no stable Laravel 13 release, stop and tell me the options.

**2. Install the packages.**

| Purpose | Package | How |
|---|---|---|
| API auth | `laravel/sanctum` | `php artisan install:api` |
| Multi-tenancy | `stancl/tenancy` | follow its skill |
| API docs | `dedoc/scramble` | follow its skill |
| Roles and permissions | `spatie/laravel-permission` | follow its skill |
| Slugs | `spatie/laravel-sluggable` | follow its skill |
| Media | `spatie/laravel-medialibrary` | follow its skill |
| Activity log | `spatie/laravel-activitylog` | follow its skill |
| Auditing | `owen-it/laravel-auditing` | follow its skill |
| Excel | `maatwebsite/excel` | follow its skill |
| World data | `nnjeim/world` | follow its skill |
| Money maths | `brick/money` | wrapped by `App\Shared\Money` |
| Phone numbers | `propaganistas/laravel-phone` | follow its skill |
| Real-time | `laravel/reverb` | **deferred** until a compatible stable release exists; then `php artisan install:broadcasting` and follow its skill |
| Search | `laravel/scout` | follow its skill; database engine first |
| Error tracking | `sentry/sentry-laravel` | follow its skill; DSN from environment only |
| Translations | `spatie/laravel-translatable` | install at the start of Step 7 (Catalog); follow its skill or official docs |
| Two-factor login | `pragmarx/google2fa` | TOTP codes for platform admins and staff (section 10); the frontend renders the QR code from the `otpauth://` URL |
| Debugging (dev) | `laravel/telescope` | `composer require --dev`, local environment only |
| Testing (dev) | `pestphp/pest`, `pestphp/pest-plugin-laravel` | replace PHPUnit tests with Pest |
| Static analysis (dev) | `larastan/larastan` | **level 8**, with a `phpstan.neon` |
| Formatting (dev) | `laravel/pint` | with a `pint.json` |

If a package's skill gives install steps, the skill's steps win over this table. Don't install anything not listed here without asking.

**3. Publish configs and migrations, into the right place.** Publishing is where multi-tenant projects most often go wrong. Every published migration must land in the correct database folder:

| Package | Migrations go to | Why |
|---|---|---|
| `stancl/tenancy` (tenants, domains) | `database/migrations/landlord/` | tenants are a platform concern |
| `nnjeim/world` | `database/migrations/landlord/` | global reference data, shared by all stores |
| `laravel/sanctum` (tokens) | **both** `landlord/` and `tenant/` | platform admins log in centrally; staff and customers log in per store |
| `spatie/laravel-permission` | **both** `landlord/` and `tenant/` | platform admins have platform roles; each store has its own staff roles |
| `spatie/laravel-medialibrary` | `database/migrations/tenant/` | each store's images |
| `spatie/laravel-activitylog` | **both** `landlord/` and `tenant/` | each store's activity, plus platform-admin actions in the central database (approved; added in Step 5) |
| `owen-it/laravel-auditing` | **both** `landlord/` and `tenant/` | each store's audit history, plus platform roles, plans and other sensitive central records (added in Step 5) |
| `laravel/telescope` | central (local only) | development tool; must never touch tenant data in production |
| `laravel/reverb`, `laravel/scout`, `sentry/sentry-laravel`, `brick/money`, `propaganistas/laravel-phone` | no migrations | configuration only; review each published config |
| Laravel defaults (users, cache, jobs, sessions) | review each one | delete or move per this guide; for example there is no generic `users` table, because identities live in their own domains (section 10) |

Publish every package's config file and review it against this guide: tenant-aware cache and queues, the correct guards, the correct models and namespaces, and storage disks per tenant.

**4. Wire the foundation** (configuration only, no business features):
- `config/tenancy.php` and `TenancyServiceProvider`: multi-database mode, central domains from config, tenancy bootstrappers for database, cache, filesystem, queue and Redis.
- `config/auth.php`: the four guards (`platform`, `staff`, `customer`, `driver`), each with its own provider and model (section 10).
- Routes split into `landlord.php`, `tenant.php` and `webhooks.php` (section 5).
- The folder skeleton from section 5, with Laravel's default `app/Models` and `app/Http` removed.
- PSR-4 entries for `modules/` and `integrations/` (section 5.7).
- Scramble configured for the landlord and tenant APIs.
- Reverb configured with tenant-scoped channels and guard-aware channel authorization in `routes/channels.php`.
- Scout configured with tenant-isolated indexes, on tenant-aware queues.
- Sentry configured with tenant tagging and personal-data scrubbing.
- Telescope restricted to the local environment.
- Pint, Larastan and Pest configured, plus the architecture tests from section 7.

**5. Verify.**
- `php artisan migrate --path=database/migrations/landlord` runs cleanly on the central database.
- A test tenant can be created, its database provisioned, and tenant migrations run on it.
- `composer audit`, Pint, Larastan and Pest all pass.
- Scramble's docs page loads.

**6. Report back:** what was installed (with versions), where every migration went, any compatibility problems, anything you weren't sure about, and anything in this guide that turned out wrong during setup. Then stop and wait for my approval before writing any feature code.

### 2.3 Laravel Boost and which rules win

This project uses **Laravel Boost**. Its guidelines are in the Boost guidelines block at the end of this file. **Never edit or delete that block by hand**; Boost regenerates it when it updates.

**Never write Boost's opening or closing tag name anywhere else in this file**, not even inside backticks. `boost:update` treats any copy of its tag as the start of its block and replaces everything after it, which deletes the rest of this guide. Always say "the Boost guidelines block" instead.

**PHP version:** Boost writes whichever PHP version runs it into its block. Always run `php artisan boost:update` with the project's PHP 8.4 CLI, never a newer global PHP. If the block ever states a different version, section 2.2 is correct.

**Use Boost's tools.** Before using any Laravel or package API, use Boost's `search-docs` tool to check the documentation for the installed version. Use `database-schema` before writing migrations or models, and `database-query` instead of raw SQL in tinker. Read `.ai/rules` if it exists, as Boost requires.

**When this guide and the Boost guidelines differ, this guide wins** on the points below. Everywhere else, follow Boost.

- **Directory structure:** Boost says not to create new base folders without approval. **This guide is that approval** for every folder in section 5: `app/Landlord`, `app/Tenant`, `app/Shared`, `modules/`, `integrations/`, the split `database/` and `routes/` folders, and the module and integration layouts. Any folder *not* in section 5 still needs my approval.
- **Dependencies:** Boost says not to change dependencies without approval. **Every package in section 2.2 is approved.** Anything else still needs my approval.
- **`php artisan make:` commands:** use them, as Boost says, but always pass the fully qualified class name so the file lands in the right domain, never in Laravel's defaults. For example `php artisan make:model 'App\Tenant\Catalog\Models\Product' --no-interaction` and `php artisan make:class 'App\Tenant\Catalog\Actions\CreateProduct' --no-interaction`. After generating, move the factory into `database/factories/Tenant/` or `Landlord/` and connect it to the model. For `modules/` and `integrations/`, which live outside `app/`, write the files by hand following the same structure.
- **"Follow existing conventions in sibling files":** this repository is new, so the conventions are the ones in this guide. Never copy conventions from Laravel's defaults or the legacy project when they conflict with this guide.
- **Tests:** during work, run the narrowest tests that cover the change, as Boost says. At the end of each step, also run the architecture tests and the tenant-isolation tests (section 7 and section 14), because those protect the whole system. Then ask me to run the complete suite.
- **Documentation files:** as Boost says, don't create documentation files unless I ask. This `CLAUDE.md` is the exception, and suggested improvements to it go in your report, not straight into the file.

---

## 3. Working with me

I'm not strong at software architecture yet, so act as a senior architect and a teacher, not just a code generator.

- **Challenge me.** If my request, my old design, or anything in this repository is inconsistent, conflicting, outdated or unlikely to scale, say so directly, even if I didn't ask.
- **For every architecture problem, state:** where it is (file, folder or concept), why it's a problem (with a concrete future consequence), and the recommended approach based on how modern multi-tenant e-commerce SaaS is built. If there are real trade-offs, give at most two options and recommend one.
- **Explain decisions briefly** in plain language, so I learn the reasoning, not just the result.
- **Don't agree just to please me.** A clear "this is a bad idea because…" is more useful than going along with a weak design.
- **Review architecture, not just code.** That means tenancy, data isolation, plan gating, auth boundaries, domain boundaries, event flow, queues, caching, payments and webhooks.

### 3.1 Deciding what is core, a module, or an integration

The legacy project's names and groupings are not reliable. They contain internal jargon and mixed responsibilities. **Don't trust its labels.** Classify every feature by what it actually does, using how modern multi-tenant e-commerce platforms (such as Shopify, BigCommerce and Saleor) are structured.

Ask these questions in order:

1. **Is it about running the platform rather than a store?** (tenants, plans, subscriptions, platform billing, platform admins) → **Landlord**
2. **Does it mainly talk to an external third-party service, and could another provider replace it?** (WhatsApp, Paystack, SMS, shipping carriers, accounting software) → **Integration**, implementing a core contract
3. **Could a typical store still sell online without it?**
   - No → **Core** (catalog, inventory, cart, checkout, orders, payments, customers, shipping, staff and roles, store settings)
   - Yes, and it's an optional capability you could charge extra for, or it's industry-specific → **Module** (HR, Restaurant, Manufacturing, AdvancedReporting, and similar)
4. **Does it have no business meaning at all?** (money handling, tenancy glue, base classes) → **Shared**

**For borderline features, split them.** Put the basic version in core and the advanced version in a module. For example, simple sales totals go in core and full analytics goes in AdvancedReporting. A single warehouse is core, while multi-warehouse can be a module.

**Rename jargon to standard industry terms.** If the old project calls something by an internal or unclear name, use the term an experienced e-commerce engineer would recognise (for example "Fulfillment", "Variant", "Storefront API", "Order line"). Always show the mapping from old name to new name, so nothing gets lost.

If a classification is genuinely unclear, say so, give your recommendation with the reason, and ask me.

### 3.2 Reviewing the legacy architecture

When asked to review `tenant-ecommerce-api`, produce a written report before any code is written. It has five parts.

**1. Classification table.** Every feature in the old project:

| Old name | Standard name | Zone (Landlord / Core / Module / Integration / Shared) | Domain | Reason |
|---|---|---|---|---|

**2. Findings.** One entry per problem, ordered from most to least severe:

- **Area:** for example tenancy, auth, payments or domain boundaries
- **What the old project does**, with file or folder references
- **Why it's a problem**, in plain language
- **What goes wrong later:** a concrete consequence ("when you add a second payment gateway you'll have to edit checkout code")
- **How it's done today:** the modern approach, and how this guide handles it
- **Severity:** Critical (data leak, money or security risk), High (blocks growth), Medium (maintenance pain) or Low (style)

**3. Thinking ahead.** Check the architecture against the future, not just today. At minimum:

- 1,000+ tenants, and one large tenant with 100,000+ products and heavy traffic
- adding a second payment gateway, a new messaging channel, or a new industry module
- a tenant upgrading, downgrading or cancelling their plan
- deleting a tenant, or exporting all of a tenant's data on request (data-protection laws)
- multiple currencies, languages and timezones per store
- background jobs from one busy tenant slowing down everyone else
- per-tenant backups and restores
- one tenant's bug, bad import or webhook flood affecting others

For each, say whether this guide's architecture handles it, and if not, what to add.

**4. Global readiness audit.** The legacy project was not built with a global market in mind, especially around payments. **Read the old project's code first** and report exactly what it currently does for each area in section 3.3: payments and gateways, currencies and exchange rates, tax, languages, timezones, addresses and phone numbers, and personal-data handling. For each one, state what exists, what's hard-coded to one country or currency, what's missing, and what the new build needs instead. Don't assume; quote file references.

**5. Recommendations.** If anything in this guide itself is wrong or weaker than the modern approach, say so and propose the change. The guide should improve, not be followed blindly.

### 3.3 Built for a global market from day one

This platform targets merchants and shoppers worldwide. **Never hard-code anything to one country, currency, language, timezone or payment provider.** Retrofitting this later means touching every domain, so design for it now.

**Payments**
- Support many gateways side by side, each as an integration implementing `PaymentGateway` (for example Stripe, PayPal, Paystack, Flutterwave). Different regions need different gateways.
- A store can enable several gateways. `PaymentGatewayResolver` picks the right one for each checkout based on the store's enabled gateways, the order currency and the customer's country.
- Each gateway declares which currencies, countries and payment methods it supports.
- **Gateway amount formats belong to the integration.** Some gateways don't follow ISO 4217 decimals for every currency (for example Stripe's handling of some zero-decimal currencies). Each gateway integration converts `Money` into its own format and back, with tests. `Money` itself always follows ISO 4217.
- **Never store or log card data.** Use the gateway's hosted checkout or tokenisation, so card details never touch our servers (this keeps PCI compliance simple).
- Every payment call and every webhook is **idempotent**. Use idempotency keys and record processed webhook event IDs, so retries never charge twice or mark an order paid twice.
- Refunds, partial refunds and failed payments are first-class flows, not afterthoughts.
- **Gateways, in build order:** **Paystack** first (Africa: cards, bank transfer, USSD, mobile money), then **Stripe** (global merchants), then Flutterwave if wider African coverage is needed. Each is its own integration (`integrations/Paystack`, `integrations/Stripe`).
- **Merchants connect their own gateway accounts.** Each store enters its own gateway credentials (stored encrypted), and customers' money goes directly to the merchant. **Sellora never holds or moves merchants' money**, so build no wallets, balances, payouts or split payments. Taking a platform fee per sale (Paystack split payments, Stripe Connect) is a later decision; ask before building anything that depends on it.
- **Merchant billing** (Sellora charging merchants for their subscription) uses Paystack for African merchants, through the separate billing contract in `Landlord\Billing` (section 14).

**Currencies**
- Amounts are always `Money`: integer minor units plus an ISO 4217 currency code. Never floats. Respect each currency's decimal places (JPY has 0, KWD has 3).
- Each store has a base currency and may sell in additional presentment currencies.
- Exchange rates are stored with a timestamp, and **an order saves the rate it used**. Later rate changes never alter past orders.
- **Precision rule:** everything a customer is charged or sees as a price (unit prices, line totals, discounts, tax, shipping, order totals, refunds) is stored and calculated in **whole minor units**. Only internal figures may carry extra decimals: purchase unit costs (for example bulk supplier costs) and exchange rates. Store those as decimals with a fixed, documented scale, and round them into `Money` (through `brick/money`) before they affect anything a customer pays.
- **Rounding mode:** customer-facing amounts round **half-up**. Always pass the rounding mode explicitly; never rely on a library default. Splitting an amount (discounts across lines, refunds across items) uses largest-remainder allocation, so the parts always add up exactly to the whole.

**Tax**
- Support both tax-inclusive pricing (common in Europe and Africa) and tax-exclusive pricing (common in the US), set per store.
- Tax rules vary by country and region, so tax calculation sits behind a `TaxCalculator` contract. A simple rate-table version lives in core, and external tax services can be added later as integrations.
- Orders store the tax amounts and rates actually applied, never recalculate them later.

**Languages**
- Customer-facing content must be translatable per locale, using **`spatie/laravel-translatable`** (translations stored as JSON in the same column, for example `{"en": "Shoe", "fr": "Chaussure"}`). Translate product names and descriptions, variant and attribute labels, category and brand names, storefront pages, menus, banners and SEO text. Never translate SKUs, prices, codes or internal notes.
- Every translated field falls back to the store's default locale when a translation is missing. Scout indexes searchable text per locale.
- API error messages and validation messages use Laravel's translation files, never hard-coded English strings.
- Each store has a default locale and may enable more. The storefront requests a locale, and the API falls back to the store default.

**Timezones and dates**
- Store every timestamp in UTC. Convert to the store's or customer's timezone only for display.
- **Every database connection runs in UTC** (the `timezone` option on the connection, central and tenant alike). Never rely on the database server's local timezone, or stored times silently shift.
- Return dates in the API as ISO 8601 with offset.
- "Today's sales", report periods and scheduled promotions use the store's timezone, not the server's.

**Addresses, phones and units**
- Address formats differ by country: states, postcodes and city rules are not universal. Validate per country using `nnjeim/world` data, and never require fields a country doesn't use.
- Phone numbers are stored in E.164 format (`+2348012345678`, `+14155550123`).
- Store weights and dimensions in metric internally, and let each store choose display units.

**Personal data and privacy**
- Design for data-protection laws such as GDPR (Europe), NDPA (Nigeria) and CCPA (California): a store can export or delete a customer's personal data on request, and a tenant can be fully exported or deleted.
- Collect only the personal data a feature needs. Never log personal data or secrets.
- **Retention:** every store of personal data that isn't a core business record has a retention period and a scheduled purge. That includes webhook payload logs, activity and audit logs, driver location history, abandoned carts and expired tokens. The periods live in config, not code.
- **Privacy registry:** each domain or module that stores personal data registers its own export and erase handlers in one central registry, so "export my data" and "delete my data" requests cover every feature automatically, including modules added later. Orders are anonymised rather than deleted, because they're financial records.

If the legacy project hard-codes anything covered here (one currency, one gateway, one country's address format, server-timezone dates), flag it in the review and port the behaviour in the global-ready form.

---

## 4. The three zones

| Zone | Location | Namespace | What lives here |
|---|---|---|---|
| Core | `app/` | `App\` | Everything every tenant gets, plus the landlord (platform) side and shared infrastructure |
| Modules | `modules/<Name>/` | `Modules\<Name>\` | Plan-gated business features: Hr, Restaurant, Manufacturing, AdvancedReporting |
| Integrations | `integrations/<Name>/` | `Integrations\<Name>\` | Plan-gated connections to third parties: WhatsApp, payment gateways, SMS, shipping carriers |

The word **"module" only ever means a plan-gated feature in `modules/`.** Core business areas are called **domains**. Never use "module" for a core domain.

Modules and integrations are wired by hand (no `nwidart/laravel-modules`).

---

## 5. Full project structure

`<Domain>`, `<Name>` and similar placeholders in angle brackets mean "fill in the real name."

```
sellora-api/
├── app/                              # Core (see 5.1)
├── modules/                          # Plan-gated modules (see 5.4)
├── integrations/                     # Plan-gated integrations (see 5.5)
├── bootstrap/
│   ├── app.php                       # routing, middleware, exception mapping
│   └── providers.php                 # registers ALL module and integration providers
├── config/
│   ├── auth.php                      # four guards: platform, staff, customer, driver
│   ├── tenancy.php
│   └── scramble.php
├── database/
│   ├── migrations/
│   │   ├── landlord/                 # central database
│   │   └── tenant/                   # every tenant database
│   ├── factories/
│   │   ├── Landlord/
│   │   └── Tenant/
│   └── seeders/
│       ├── Landlord/
│       └── Tenant/
├── routes/
│   ├── landlord.php                  # central domain; loads routes/landlord/*
│   ├── landlord/
│   │   ├── auth.php
│   │   ├── team.php                  # platform team: admin invitations, admins, platform roles, 2FA resets (super admins only)
│   │   ├── tenants.php
│   │   ├── plans.php
│   │   ├── subscriptions.php
│   │   └── legal.php
│   ├── tenant.php                    # tenant domains; loads routes/tenant/*
│   ├── tenant/
│   │   ├── auth.php
│   │   ├── settings.php
│   │   ├── staff.php
│   │   ├── customers.php
│   │   ├── catalog.php
│   │   ├── inventory.php
│   │   ├── promotions.php
│   │   ├── cart.php
│   │   ├── checkout.php
│   │   ├── orders.php
│   │   ├── payments.php
│   │   ├── shipping.php
│   │   ├── returns.php
│   │   ├── storefront.php
│   │   └── delivery.php              # driver app endpoints + staff delivery management
│   ├── registration.php              # central domain, no sign-in: hosting regions, legal documents, store sign-up (docs at /docs/registration)
│   ├── webhooks.php                  # central domain; shared webhook entry points
│   ├── channels.php                  # Reverb channel authorization, tenant- and guard-aware
│   └── console.php
├── tests/
│   ├── Architecture/                 # Pest arch tests enforcing section 7
│   ├── Feature/
│   │   ├── Landlord/
│   │   └── Tenant/
│   ├── Unit/
│   ├── Pest.php
│   └── TestCase.php
├── composer.json
├── phpunit.xml                       # includes module and integration test folders
└── CLAUDE.md
```

Delete Laravel's default `app/Models/` and `app/Http/` after setup. Everything they would hold has a home below.

### 5.1 Core: `app/`

```
app/
├── Landlord/                         # CENTRAL database only. The platform business.
│   ├── Tenancy/                      # Tenant, Domain; creating and provisioning stores
│   ├── Plans/                        # Plan, PlanFeature; which features each plan includes
│   ├── Subscriptions/                # which plan each tenant is on; upgrades, downgrades
│   ├── Billing/                      # invoicing tenants for their subscription
│   ├── Identity/                     # platform admins, platform roles + permissions (guard: platform)
│   ├── Legal/                        # platform terms and policies, versions, merchant acceptance records
│   └── Integrations/                 # maps external account IDs to tenants (for webhooks)
│
├── Tenant/                           # TENANT database. Every store gets all of these.
│   ├── Settings/                     # store name, currency, logo, tax and locale settings
│   ├── Identity/                     # staff users, roles, permissions (guard: staff)
│   ├── Customers/                    # customers, addresses, customer auth (guard: customer)
│   ├── Catalog/                      # products, variants, categories, brands, attributes
│   ├── Inventory/                    # stock levels, stock movements, reservations
│   ├── Pricing/                      # price and tax calculation
│   ├── Promotions/                   # coupons, discounts
│   ├── Cart/                         # carts and cart items
│   ├── Checkout/                     # turns a cart into an order and starts payment
│   ├── Orders/                       # orders, items, status history, invoices
│   ├── Payments/                     # payments, refunds; PaymentGateway contract
│   ├── Shipping/                     # shipping methods, shipments; ShippingCarrier contract
│   ├── Delivery/                     # store's own drivers, delivery assignments, proof of delivery (guard: driver)
│   ├── Returns/                      # return requests (RMA), inspection, restocking; refunds go through Payments
│   ├── Storefront/                   # content pages, menus, banners, SEO settings for the storefront
│   └── Messaging/                    # customer notifications; MessageChannel contract
│
├── Shared/                           # Infrastructure used by all zones. No business rules.
│   ├── Features/                     # plan gating (see 5.6)
│   ├── Tenancy/                      # glue around the tenancy package; TenantRoutes.php is the single definition of how store routes are served (core and modules)
│   ├── Geography/                    # thin read-only access to nnjeim/world reference data
│   ├── Auth/                         # shared sign-in pieces for all four guards: token issuing, credential check with lockout, password change and reset
│   │   ├── TwoFactor/                # 2FA challenges, codes and recovery codes; the ONLY place allowed to use pragmarx/google2fa
│   │   └── Models/Role.php           # the one role model for both sides (the permissions package allows only one); always scoped by guard
│   ├── Idempotency/                  # Idempotency-Key middleware and stored replay responses
│   ├── Privacy/                      # privacy registry (export and erase handlers per kind of person) and the store export registry
│   ├── Retention/                    # retention periods and the scheduled per-tenant purge
│   ├── Money/
│   │   ├── Money.php                 # value object wrapping brick/money: minor units + currency
│   │   ├── MoneyCast.php             # stores amount + currency columns (currency column may be shared)
│   │   ├── Decimal.php               # high-precision internal numbers (exchange rates, unit costs)
│   │   ├── DecimalCast.php           # fixed-scale column; refuses values with too many decimals
│   │   └── MoneyResource.php         # {amount, currency, formatted} in every API response
│   ├── Http/
│   │   ├── Controller.php            # abstract base controller
│   │   └── Middleware/               # app-wide middleware only, e.g. ForceJsonResponse
│   ├── Exceptions/
│   │   ├── DomainException.php       # abstract parent of all business exceptions
│   │   └── ApiErrorResponseDocumentation.php  # documents the {message, code, errors} shape in Scramble
│   └── Concerns/                     # traits used across zones, e.g. HasPublicId, HasNormalisedEmail
│
└── Providers/
    ├── AppServiceProvider.php
    └── TenancyServiceProvider.php
```

Why the Landlord/Tenant split is at the top: with multi-database tenancy, the database connection is the hardest boundary in the system. Landlord models use the central connection and Tenant models use the tenant connection, so the folders match that boundary. Never mix them inside one domain.

### 5.2 Anatomy of a domain

Every domain, in core, modules and integrations alike, uses the same layout. **Only create the folders a domain actually needs.** This is a menu, not a checklist.

```
<Domain>/
├── Actions/          # one class per business operation; one public method: handle()
├── Contracts/        # interfaces this domain exposes or expects others to implement
├── Data/             # readonly DTOs passed into Actions and across domains
├── Enums/
├── Events/           # facts this domain announces: OrderPlaced
├── Exceptions/       # business errors this domain throws
├── Exports/          # Excel/CSV exports (maatwebsite/excel)
├── Http/
│   ├── Controllers/  # thin: Form Request → Action → Resource
│   ├── Requests/
│   └── Resources/
├── Imports/          # Excel/CSV imports (maatwebsite/excel)
├── Jobs/
├── Listeners/        # this domain reacting to other domains' events
├── Models/
├── Policies/
└── Services/         # ONLY shared calculation logic used by several Actions
```

### 5.3 Two domains, fully expanded

These show real naming. Follow the same patterns everywhere.

```
app/Tenant/Catalog/
├── Actions/
│   ├── CreateProduct.php
│   ├── UpdateProduct.php
│   ├── PublishProduct.php
│   ├── ArchiveProduct.php
│   ├── CreateCategory.php
│   └── CreateBrand.php
├── Data/
│   ├── ProductData.php
│   ├── ProductVariantData.php
│   └── CategoryData.php
├── Enums/
│   └── ProductStatus.php
├── Events/
│   ├── ProductPublished.php
│   └── ProductArchived.php
├── Exceptions/
│   └── ProductCannotBePublishedException.php
├── Http/
│   ├── Controllers/
│   │   ├── ProductController.php
│   │   ├── PublishProductController.php
│   │   ├── CategoryController.php
│   │   └── BrandController.php
│   ├── Requests/
│   │   ├── StoreProductRequest.php
│   │   ├── UpdateProductRequest.php
│   │   ├── StoreCategoryRequest.php
│   │   └── StoreBrandRequest.php
│   └── Resources/
│       ├── ProductResource.php
│       ├── ProductVariantResource.php
│       ├── CategoryResource.php
│       └── BrandResource.php
├── Models/
│   ├── Product.php
│   ├── ProductVariant.php
│   ├── Category.php
│   ├── Brand.php
│   ├── Attribute.php
│   └── AttributeValue.php
└── Policies/
    ├── ProductPolicy.php
    └── CategoryPolicy.php
```

```
app/Tenant/Orders/
├── Actions/
│   ├── CreateOrderFromCart.php
│   ├── MarkOrderAsPaid.php
│   ├── CancelOrder.php
│   └── GenerateOrderInvoice.php
├── Data/
│   └── OrderData.php
├── Enums/
│   └── OrderStatus.php
├── Events/
│   ├── OrderPlaced.php
│   ├── OrderPaid.php
│   └── OrderCancelled.php
├── Exceptions/
│   └── OrderCannotBeCancelledException.php
├── Http/
│   ├── Controllers/
│   │   ├── OrderController.php
│   │   └── CancelOrderController.php
│   ├── Requests/
│   │   └── CancelOrderRequest.php
│   └── Resources/
│       ├── OrderResource.php
│       └── OrderItemResource.php
├── Listeners/
│   └── MarkOrderAsPaidWhenPaymentSucceeds.php   # listens to Payments\Events\PaymentSucceeded
├── Models/
│   ├── Order.php
│   ├── OrderItem.php
│   └── OrderStatusChange.php
└── Policies/
    └── OrderPolicy.php
```

And a domain that defines a contract for integrations to implement:

```
app/Tenant/Payments/
├── Actions/
│   ├── InitiatePayment.php
│   ├── ConfirmPayment.php
│   └── RefundPayment.php
├── Contracts/
│   └── PaymentGateway.php            # implemented by integrations (Paystack, etc.)
├── Data/
│   ├── PaymentRequestData.php
│   └── GatewayResultData.php
├── Enums/
│   ├── PaymentStatus.php
│   └── PaymentMethod.php
├── Events/
│   ├── PaymentSucceeded.php
│   ├── PaymentFailed.php
│   └── PaymentRefunded.php
├── Exceptions/
│   └── PaymentGatewayUnavailableException.php
├── Http/ ...
├── Models/
│   ├── Payment.php
│   └── Refund.php
└── Services/
    └── PaymentGatewayResolver.php     # picks the tenant's enabled gateway
```

### 5.4 Modules: `modules/`

```
modules/
├── Hr/
├── Restaurant/
├── Manufacturing/
└── AdvancedReporting/
```

Every module has the same shape. Hr, fully expanded:

```
modules/Hr/
├── src/
│   ├── Employees/                    # a domain inside the module (5.2 layout)
│   │   ├── Actions/
│   │   │   ├── HireEmployee.php
│   │   │   └── TerminateEmployee.php
│   │   ├── Data/
│   │   │   └── EmployeeData.php
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── EmployeeController.php
│   │   │   ├── Requests/
│   │   │   │   └── StoreEmployeeRequest.php
│   │   │   └── Resources/
│   │   │       └── EmployeeResource.php
│   │   ├── Models/
│   │   │   └── Employee.php
│   │   └── Policies/
│   │       └── EmployeePolicy.php
│   ├── Payroll/
│   ├── Leave/
│   └── HrServiceProvider.php         # extends App\Shared\Features\ModuleServiceProvider
├── database/
│   ├── migrations/                   # tenant migrations
│   ├── factories/
│   └── seeders/
├── routes/
│   └── api.php                       # wrapped in ->middleware('module:hr')
├── config/
│   └── hr.php
└── tests/
    ├── Feature/
    └── Unit/
```

The module's service provider is its single source of truth. It declares the module key (`hr`) and any modules it depends on, and loads its routes, tenant migrations, config and event listeners. There is no separate manifest file.

### 5.5 Integrations: `integrations/`

```
integrations/
├── WhatsApp/
├── Paystack/                         # example payment gateway
└── <Name>/
```

WhatsApp, fully expanded:

```
integrations/WhatsApp/
├── src/
│   ├── WhatsAppChannel.php           # implements App\Tenant\Messaging\Contracts\MessageChannel
│   ├── Client/
│   │   └── WhatsAppClient.php        # the ONLY class that talks to the WhatsApp API
│   ├── Webhooks/
│   │   ├── VerifyWhatsAppSignature.php   # middleware
│   │   └── HandleIncomingMessage.php
│   ├── Listeners/
│   │   └── SendOrderConfirmationOnWhatsApp.php   # listens to Orders\Events\OrderPlaced
│   ├── Http/                         # connect/disconnect endpoints for store admins
│   ├── Models/
│   │   └── WhatsAppAccount.php       # tenant's credentials and settings
│   └── WhatsAppServiceProvider.php   # extends App\Shared\Features\IntegrationServiceProvider
├── database/migrations/
│   ├── landlord/                     # phone-number ID → tenant lookup
│   └── tenant/                       # credentials, message templates
├── routes/
│   ├── api.php                       # wrapped in ->middleware('integration:whatsapp')
│   └── webhooks.php                  # central domain, signature-verified
├── config/
│   └── whatsapp.php
└── tests/
```

Payment gateway integrations follow the same shape. The main class is `<Provider>Gateway` implementing `App\Tenant\Payments\Contracts\PaymentGateway`, for example `PaystackGateway`.

### 5.6 Plan gating: `app/Shared/Features/`

```
app/Shared/Features/
├── Features.php                      # the one place that answers "may this store use X?" (states + limits)
├── FeatureState.php                  # Enabled, Locked, Suspended, Disabled, Unavailable
├── FeatureRegistry.php               # every registered module/integration key, dependencies, wind-down routes
├── FeatureSnapshot.php               # cached per-tenant plan data (plain array in the cache)
├── Contracts/
│   └── FeatureSource.php             # implemented by Landlord\Subscriptions; Shared never imports Landlord
├── FeatureServiceProvider.php        # abstract parent of the two providers below
├── ModuleServiceProvider.php         # abstract base for module providers
├── IntegrationServiceProvider.php    # abstract base for integration providers
├── Jobs/
│   └── RunOnlyWhenFeatureIsEnabled.php # job middleware: re-checks the plan when a queued job runs
├── Http/Middleware/
│   ├── EnsureFeatureIsUsable.php     # abstract parent: Locked = read-only, wind-down routes stay open
│   ├── EnsureModuleIsEnabled.php     # alias: module
│   └── EnsureIntegrationIsEnabled.php  # alias: integration
└── Exceptions/
    └── FeatureNotEnabledException.php
```

Settings such as the unpaid-subscription grace period (`past_due_grace_period_in_days`) live in `config/features.php`.

### 5.7 Autoloading

Each module and integration gets its own PSR-4 entry in `composer.json`:

```json
"autoload": {
  "psr-4": {
    "App\\": "app/",
    "Modules\\Hr\\": "modules/Hr/src/",
    "Modules\\Restaurant\\": "modules/Restaurant/src/",
    "Integrations\\WhatsApp\\": "integrations/WhatsApp/src/",
    "Database\\Factories\\": "database/factories/",
    "Database\\Seeders\\": "database/seeders/"
  }
}
```

Tests are never autoloaded in production. Module and integration test namespaces go in `autoload-dev`, and their folders are added to `phpunit.xml` and `tests/Pest.php`.

Adding a new module or integration means: create the folder, add the PSR-4 entry, register the provider in `bootstrap/providers.php`, and run `composer dump-autoload`.

---

## 6. Domain boundaries: public vs private

Each domain has a **public surface** that other domains may use, and **private internals** that only it may use.

| Public (others may use) | Private (only this domain) |
|---|---|
| `Actions/` (to ask it to do something) | `Http/` |
| `Contracts/` | `Listeners/` |
| `Data/` | `Jobs/` |
| `Enums/` | `Policies/` |
| `Events/` | `Services/` |
| `Exceptions/` | |
| `Models/` (**read only**) | |
| `Concerns/` (traits other domains may use, e.g. `Tenant\Identity\Concerns\ActsAsStaffMember`) | |

- **Only a domain changes its own data.** Orders may read a `Product`, but it never updates one. To change stock, it calls an Inventory Action or Inventory reacts to an Orders event.
- **Prefer events for reactions.** Payments announces `PaymentSucceeded`, and Orders listens and marks the order paid. Payments never calls into Orders.
- **Use Actions for direct requests**, when one domain needs another to do something right now and needs the result, such as Checkout calling `Inventory\Actions\ReserveStock`.
- **Never copy another domain's private code to get around this table.** If several domains need the same helper, the owning domain makes it public (usually in `Concerns/` or `Contracts/`); one copy, not one per domain.
- **Writes across the central and store databases can't share one transaction.** The store database is the source of truth for what happens inside a store, so commit the store change first, then update the central side through a Shared contract with an idempotent queued job that retries until it succeeds. Check anything that could make the central side refuse (for example the current terms version) before the store commits.
- **Core offers extension points; modules plug in.** Core never imports a module to support it. Instead, core defines contracts for the places modules need to hook in, and modules register implementations from their service providers. Examples: payment methods offered at checkout (gift cards, instalments, store credit), discount sources (loyalty points, promotions), order-line enrichers, and checkout validators. Core collects whatever is registered and only uses the ones whose module is enabled for the tenant.

---

## 7. Dependency rules (non-negotiable)

1. **Core never imports `Modules\` or `Integrations\`.**
2. **Landlord and Tenant stay apart.** Tenant domains never import Landlord models; they ask through `App\Shared\Features`. Landlord domains never query tenant databases directly. The platform may initialise a store's tenancy to run Shared infrastructure through contracts (tenant migrations, the store export, revoking sign-ins), but Landlord code never reads or writes store tables itself.
3. Modules and integrations may use core's public surface (section 6) only.
4. Modules never import other modules, except for a dependency declared in the provider. Even then, they use only its public surface.
5. Integrations never import modules.
6. Core calls integrations only through contracts (`PaymentGateway`, `MessageChannel`, `ShippingCarrier`), never by class name.
7. `App\Shared` contains no business rules and imports nothing from Landlord, Tenant, Modules or Integrations. When Shared needs information that lives elsewhere, it defines a contract and another zone implements it. For example, `Features` needs to know a tenant's plan, so `App\Shared\Features\Contracts\FeatureSource` is implemented in `Landlord\Subscriptions` and bound in a service provider.

These rules are enforced by Pest architecture tests in `tests/Architecture/`, for example:

```php
arch('core does not depend on modules')
    ->expect('App')
    ->not->toUse('Modules');

arch('core does not depend on integrations')
    ->expect('App')
    ->not->toUse('Integrations');

arch('shared does not depend on the landlord side')
    ->expect('App\Shared')
    ->not->toUse('App\Landlord');

arch('shared does not depend on stores')
    ->expect('App\Shared')
    ->not->toUse('App\Tenant');

arch('domains are final')
    ->expect(['App\Landlord', 'App\Tenant'])
    ->classes()
    ->toBeFinal()
    ->ignoring(['App\Shared']);
```

**Pest caveat:** a `not->toUse([...])` rule that lists several namespaces can pass even when it's broken. Write one namespace per rule, and when adding a rule, prove it fails against a deliberate violation before relying on it.

If a change needs to break a rule, stop and explain why instead of working around the test.

---

## 8. Plan gating

- **Always register** every module's and integration's service provider. Never boot or skip a provider based on the current tenant. Providers boot before the tenant is resolved, and conditional booting breaks under queues and Octane.
- **Gate access at runtime** through `App\Shared\Features\Features` (for example, `Features::enabled('hr')`). Use it in:
  - route middleware: `->middleware('module:hr')` and `->middleware('integration:whatsapp')`
  - policies
  - queued jobs and listeners. Check again at execution time, because the plan may have changed since dispatch.
- **Gate access, not schema.** Run every module's migrations for every tenant, whatever their plan. Upgrades are instant, and downgrades keep the tenant's data.
- Feature results may be cached per tenant. Clear the cache whenever a subscription changes.
- **Locked is recorded at the moment a plan change removes a feature**, not worked out later from the current plan. That way, adding a feature to a plan in future never turns a downgraded store's data from read-only into hidden.
- **A dependent feature takes its requirement's state.** If HR is Locked, Payroll is Locked too (read-only), not Disabled.
- Features on a store's plan start Enabled; the merchant can switch them off (Disabled).
- A limit missing from a plan means **zero**, never unlimited. Limits may overshoot by one under exact concurrency; that's acceptable for plan limits, because it never loses data.
- **Feature grants:** platform admins can grant or revoke a feature for one store, optionally until a date (for example "Loyalty free for 30 days"), through `Features`, never by editing plans. When a grant ends (by date or by being revoked), the feature becomes **Locked** (read-only), never hidden.
- **Limit overrides** per store, optionally until a date. **Unlimited must be stated explicitly** (an explicit unlimited flag or value sent on purpose), never inferred from a missing or empty value, so a bug can never grant unlimited by accident.
- **Platform suspension** is separate from billing: every feature becomes Suspended, but the store is still served so staff can sign in and see why. Paying doesn't lift a platform suspension, and lifting it doesn't override an unpaid subscription.

**Feature states.** A feature is not just on or off. `Features` returns one of these states, and every gate respects it:

| State | Meaning | Behaviour |
|---|---|---|
| `Enabled` | On the tenant's plan | Full access |
| `Locked` | Was on the plan, removed by a downgrade | **Read-only**: existing data stays visible and exportable, nothing new can be created or changed |
| `Suspended` | Tenant suspended (for example unpaid subscription) | Blocked, data kept; customers see a friendly unavailable response |
| `Disabled` | Available on the plan but turned off by the merchant | Blocked, data kept |
| `Unavailable` | Not on the plan and never used | Blocked |

- **Wind-down:** when a feature becomes `Locked` or `Suspended`, customers can still finish what they already started (for example view and pay for an existing booking or instalment plan), but nothing new begins. Each module declares its wind-down routes.
- **Usage limits** sit beside feature states: maximum products, staff accounts, locations, storage and similar, defined per plan, with optional per-tenant overrides. Limits are checked in the Action that creates the thing, with a clear error when a limit is reached. Exceeding a limit after a downgrade never deletes data; it only blocks creating more.

---

## 9. Tenancy

- Landlord migrations go in `database/migrations/landlord`. Tenant migrations go in `database/migrations/tenant`, and module migrations are tenant migrations too, registered by each module's provider.
- Landlord routes are served on the central domain only. Tenant routes are served on tenant domains only, behind the tenancy identification middleware.
- **Webhooks** from third parties arrive on the central domain. The flow is: verify the signature, look up the tenant in `Landlord\Integrations`, initialize tenancy, then hand off to the integration's handler.
- Queued jobs, cache, storage and Redis must be tenant-isolated. Follow the `stancl/tenancy` skill for how.
- Follow the `stancl/tenancy` skill for tenant creation, database provisioning, tenant migrations and tenant identification. Don't hand-roll anything the package already provides.

### 9.1 Store registration, domains and hosting region

**Registration (store onboarding)** is handled in `Landlord\Tenancy` by one Action, for example `RegisterStore`, which runs these steps in order: validate the details, reserve the subdomain, record the hosting region, create the tenant, provision its database in that region, run tenant migrations and seeders, create the owner's staff account, and apply defaults. Provisioning is queued and retry-safe, and the store is only marked active when every step has succeeded. A failed registration never leaves a half-created store behind.

At registration the merchant chooses:
- **Store name and subdomain:** for example `mystore.sellora.<tld>` (the final platform domain is not decided yet; keep it in config, never hard-code it).
- **Country:** sets sensible defaults for currency, timezone, language, tax mode and address format (section 3.3). The merchant can change these later.
- **Hosting region** (see below).

Until merchant billing is decided (section 16), new stores register on the plan with code `free`. A landlord seeder creates it with starter limits I approve; platform admins can change plans later. Registration stays closed (503) if no `free` plan exists.

**How sign-up works (built in Step 5a):**
- The merchant submits their details and accepts the current **terms of service and privacy policy**; a 6-digit code is emailed. No store or database exists until the code is confirmed, so spam sign-ups cost one row.
- Codes last 60 minutes; 5 wrong codes require a new code. Abandoned sign-ups are deleted 7 days after expiry, together with their legal acceptances (no contract was formed). A real store's acceptances are kept as evidence of the contract.
- Confirming the code creates the store (status `provisioning`), its subdomain and its `free` subscription, then queues setup. Setup is retry-safe (5 tries with growing waits) before the store becomes `provisioning_failed`. The owner's password hash is held only until their account exists, then deleted.
- The owner is created through a Shared contract (Landlord never writes to a store database itself), and **the owner does not count toward the staff limit**; the limit covers additional staff.
- A store that doesn't serve requests (`provisioning`, `provisioning_failed`, `closed`, `purging`, `purged`) answers every request with 503 `store_unavailable`, checked when the store is identified from its domain. Suspended stores are still served (section 8).
- Published legal document versions never change. A version published for a future date doesn't replace the current one until it takes effect.
- **Running tenant migrations across all stores skips only stores that have no database yet** (`provisioning` before its database exists, and `provisioning_failed`). Suspended and closed-but-not-purged stores still have databases and must stay migrated, or reactivating them would break.

**Store domains**
- Every store gets a subdomain on the platform domain at registration.
- Subdomains are lowercase letters, numbers and hyphens only. They are unique, and a reserved list is blocked (`www`, `api`, `admin`, `app`, `mail`, `dashboard`, `billing`, `support`, `status`, brand names and similar).
- Stores can later connect a **custom domain** (`www.mystore.com`). The flow is: the merchant adds the domain, we show the DNS record to create, we verify ownership by checking DNS, SSL is issued automatically, and only then does the domain go live. Unverified domains never serve the store.
- A store can have several domains, with exactly one marked as primary. The other domains redirect to it.
- Domains belong to `Landlord\Tenancy` (`Domain` model) because tenant identification needs them before any tenant database is known.

**Hosting region (data residency)**
- Because the platform is global, some merchants are legally required, or simply prefer, to keep their data in a specific region (for example the EU for GDPR).
- Every tenant records a **hosting region** at registration (for example `eu`, `us`, `africa`). Its database, file storage and backups live in that region.
- Design for this from day one, even if the first launch has only one region. That means the region is stored on the tenant, database and storage connections are chosen from the tenant's region (following the `stancl/tenancy` skill for per-tenant connection settings), and nothing assumes a single database server.
- Moving a store between regions is a deliberate, planned migration, never automatic. Treat the region as fixed after registration unless I decide otherwise.
- The central (landlord) database is the only cross-region store. Keep personal data out of it beyond what's needed to run the platform (tenant owner contact, billing).
- **Database server pool:** servers are added with `php artisan platform:add-database-server` (password entered hidden and stored encrypted; only added after a successful test connection). Tenant databases are placed on a pool of database servers recorded in `Landlord\Tenancy`. Each server has a region, a capacity and an "accepting new tenants" flag. Registration picks a server in the tenant's region with spare capacity. This is how Sellora grows past one server without code changes.

**Backups and restore**
- Every tenant database is backed up on a schedule, in its own region, and backups are encrypted.
- It must be possible to restore **one store** without touching any other store. One database per tenant makes this possible, so keep it that way.
- The restore procedure is written down and tested regularly. An untested backup doesn't count as a backup.
- **Automatic purging stays switched off in production until per-region encrypted backups exist and a restore has been tested** (`tenancy.purge_enabled`, off by default). Until then a purge is the one thing in the system that can't be undone. Purge never makes its own ad-hoc backup.

**Closing, restoring, exporting and purging stores (Step 5c)**
- **Statuses:** Closed, Purging and Purged join the store statuses. Closed stores still have a database and keep being migrated; Purging and Purged stores are skipped. None of the three is served.
- **Closing** works on Active, Suspended and ProvisioningFailed stores, by a platform admin (`stores.manage`) or by the owner (password plus a 2FA code when they have 2FA). It records the status before closing, revokes every sign-in in the store (staff, customers and drivers) through a Shared contract, and sets a purge date from config (90 days). The owner is emailed the purge date, told how to export first, and reminded 7 days before.
- **Restoring** is done by a platform admin, only from Closed, and returns the store to the status it had before closing, so closing and restoring can never lift a suspension. Restore and purge take the same row lock; once a store is Purging, restore answers 409.
- **Purge** is resumable and safe to run twice: lock, re-check, mark Purging and commit; drop the database; delete the store's files and exports; delete its domains and hold its subdomain (365 days, from config) so nobody can take over its old links; clear the owner's personal data on the tenant row (a database check constraint allows null owner details only on Purged stores); mark Purged. Subscriptions and legal acceptances are kept as business and contract records. Personal data about the store in central activity and audit logs follows its retention period.
- **Export** is a ZIP of JSON Lines files plus a manifest, built in chunks on the `bulk` queue, one export at a time per store, stored privately on the store's regional disk and deleted after 7 days. It's downloadable only by the person who requested it, while signed in, and every download is logged. Platform admins need `stores.export` (separate from `stores.view`, because an export holds every customer's personal data); the owner can export their own store.
- **Export contents are decided column by column, failing closed.** Every column of every store table is either explicitly included or explicitly excluded (with a reason), and a test fails when any column is unclassified, so a new column is never exported by accident. Secrets are never exported: password and PIN hashes, tokens, 2FA secrets, recovery codes, gateway credentials, and secret values inside audit and activity log records. Uploaded files (images and documents) are part of the export once domains have them.

---

## 10. Auth

There are four guards, all on Sanctum. Each guard has its own model, provider, login endpoints and tokens.

| Guard | Who | Domain | Database | Routes |
|---|---|---|---|---|
| `platform` | Sellora's own admins and support staff | `Landlord\Identity` | central | `routes/landlord/*` |
| `staff` | A store's employees | `Tenant\Identity` | tenant | staff endpoints in `routes/tenant/*` |
| `customer` | Shoppers | `Tenant\Customers` | tenant | storefront endpoints in `routes/tenant/*` |
| `driver` | A store's delivery drivers | `Tenant\Delivery` | tenant | `routes/tenant/delivery.php` |

- **Never let one guard's tokens authorize another guard's routes.** Test this for every guard pair.
- **Passwords:** at least 12 characters, no forced symbol or number rules (length protects better), and checked against known data breaches with Laravel's `uncompromised()` rule. It sends only a 5-character hash prefix, never the password. Applies to platform admins, staff and customers.
- **Sign-in lockout:** after 5 wrong attempts, sign-in for that email or phone **in that store** pauses for 15 minutes, from any IP address, for every guard (settings in `config/api.php`). Unknown accounts lock the same way, so a lockout reveals nothing.
- **Sign-in responses:** checks take the same time whether or not the account exists; deactivated accounts are refused only after a correct password; sign-in returns only the token, and every guard uses `/me` for the profile.
- **Sessions:** signing out ends only that device; changing a password ends every other device; a password reset ends all devices. **Deactivated accounts' tokens are rejected on the very next request.**
- **Polymorphic names** (tokens, roles, media, activity) come from the morph map in `AppServiceProvider`, never PHP class names. Every polymorphic model must be listed there.
- The first platform admin is created with `php artisan platform:create-admin --super-admin`. Built-in roles: Super Admin (platform) and Owner (store, seeded into every new store).
- Customer sign-up may say an email is already taken (standard in e-commerce, and rate-limited).
- Always name the guard in route middleware (`auth:platform`, `auth:staff`, `auth:customer`, `auth:driver`). Never use a bare `auth:sanctum` or `auth`; an architecture test enforces this.
- **Two-factor authentication** (`pragmarx/google2fa`) is available for platform admins and staff: TOTP codes, hashed one-time recovery codes, and a 2FA challenge before a token is issued. Platform admins must use it; for staff, it's a store setting the owner can require (built with store settings in Step 6).
  - With 2FA on, a correct password returns **202 with a challenge** that lasts 5 minutes and works only for the store and account type that started it. Codes work once, even under racing requests. 5 wrong codes lock sign-in for 15 minutes, and a correct password doesn't reset that count.
  - Setting up 2FA requires the current password, signs out every other device, and shows 8 recovery codes once. Authenticator secrets are stored encrypted; recovery codes only as hashes, removed when used.
  - **Turning 2FA off requires the current password and a current code or recovery code.** For platform admins, turning it off forces them to set it up again.
  - A platform admin without 2FA can only view their profile, set up 2FA and sign out. A route test enforces this for every platform route.
  - `platform:create-admin` sets up 2FA at creation (it prints the `otpauth://` link and the recovery codes), so a new admin is never left with a password-only account. Later platform admins are invited by a super admin with an expiring link (Step 5), never given a password chosen by someone else.
  - A platform admin who loses both their phone and recovery codes is reset by another super admin, who must confirm with their own password; the reset signs the admin out everywhere and forces 2FA setup again.
  - **Platform team management** (super admins only): admins join only by invitation (one-time link, expires after 3 days, because platform accounts reach every store); the account exists only once they accept and choose a password. Nobody changes their own account through team management, the last active super admin can never be deactivated or demoted (also under concurrent requests), and admins can't change each other's email (that would let one admin redirect another's password resets).
- **Current-password checks** (password change, 2FA settings) lock after 5 wrong tries and return 429 `too_many_incorrect_attempts`, so a stolen token can't be used to guess the password behind it.
- **Staff join only by invitation:** an emailed one-time link that expires after 7 days (only its hash is stored); the person chooses their own password when accepting. Invitations can be resent (the old link stops working) or cancelled. Nobody ever sets another person's password.
- **No self-promotion:** nobody can grant a permission they don't hold (through a role or an invitation), edit a role or manage a person that has permissions they lack, change their own roles, or deactivate themselves. The owner and the Owner role can't be changed or given through staff management; ownership transfer is its own flow (Step 5).
- **Ownership transfer** has two steps. The current owner starts it (current password, plus a 2FA code when they have 2FA) and chooses which roles they keep afterwards (required; may be none). The chosen person must be active staff, and **accepts while signed in** within 72 hours, accepting Sellora's current terms of service as they do, because the contract with Sellora moves to them. Nothing changes until they accept; the owner can cancel before then. Only one transfer can be pending per store. On acceptance the new owner gets the Owner role, the platform's owner contact is updated through a Shared contract, both people are emailed, and the change goes in the store's activity log.
- **Role names** are unique per guard ignoring case, enforced by a functional unique index on `lower(name)` as well as validation. A role still in use can't be deleted.
- **The staff limit** counts active staff plus pending invitations; deactivated staff don't count.
- **Platform admins** are authorized with `spatie/laravel-permission` roles and permissions on the `platform` guard (central database). Their actions (granting roles, suspending stores, feature grants) are written to the central activity log and audited. **When a store account triggers a platform action** (for example an owner closing their store), the central log entry has no causer and records the account as account type plus public ID, because a store account's database ID means nothing centrally. Never let the activity log fill in the signed-in user by default on such entries.
- **Staff** are authorized with `spatie/laravel-permission` roles and permissions on the `staff` guard (tenant database), through Policies.
- **Customers** can only access their own data (orders, addresses, profile). This is enforced with Policies and scoped queries, not roles.
- **Drivers** can only see and update deliveries assigned to them. Authorization is by assignment, through Policies, not staff roles. A driver never sees other drivers' deliveries, unassigned orders, prices beyond what delivery needs, or store settings.

**Delivery and drivers**
- `Tenant\Delivery` covers a store's own delivery fleet: drivers, delivery assignments, delivery status updates (picked up, on the way, delivered, failed), proof of delivery (photo or signature through `spatie/laravel-medialibrary`, recipient name) and cash-on-delivery collection.
- It is separate from `Tenant\Shipping`, which handles third-party carriers. Both are fulfilment options for an order.
- Driver location is personal data. Collect it only during an active delivery, keep it only as long as needed, and never expose it beyond the store and the customer of that delivery.
- Staff assign and manage deliveries, so staff permissions cover managing drivers. Drivers use a separate, minimal API meant for a mobile driver app.
- **Driver login** is phone number plus PIN. PINs are hashed like passwords, login is rate-limited, and the account locks temporarily after repeated wrong attempts. Staff can reset a driver's PIN and deactivate a driver, which immediately revokes their tokens.
- **Open question for me:** drivers are designed as belonging to one store. If Sellora later offers a shared driver network across many stores, drivers would move to the landlord side. Ask before building anything that depends on this.

---

## 11. API layer and documentation

### Form Requests and API Resources (mandatory)

- **Every endpoint that accepts input uses a Form Request.** That includes body, query filters and file uploads. Never call `$request->validate()` in a controller, and never read unvalidated input with `$request->input()` or `$request->all()`. Use `$request->validated()` or a DTO built from it.
- Form Requests handle request-dependent authorization in `authorize()`, usually delegating to a Policy.
- **Every endpoint that returns data uses an API Resource** (or `Resource::collection()` for lists). Never return a model, array or query result directly.
- Resources control exactly which fields are exposed. Never expose internal columns such as tenant IDs, hashed values or internal flags.
- Use `whenLoaded()` for relationships in Resources, so they never trigger lazy loading.
- Version the API in the route prefix (`/api/v1/...`). Only split controllers into a `V1/` folder when introducing a breaking change.

### Response conventions

Every endpoint behaves the same way, so frontends and API consumers can rely on it.

- **Errors** always use one JSON shape with a stable machine-readable code, a translated human message, and field errors for validation, for example `{"message": "...", "code": "order_cannot_be_cancelled", "errors": {...}}`. Map domain exceptions to this shape in `bootstrap/app.php`. Use correct status codes: 401 unauthenticated, 403 forbidden, 404 not found (also when a record exists but belongs to someone else), 409 conflict, 422 validation, 429 rate limited.
- **Pagination:** every list endpoint of records is paginated, never unbounded. The only exception is a short, fixed reference list defined in code (for example the permission list). Use cursor pagination for large or fast-growing lists (orders, products, activity) and a maximum page size.
- **Public identifiers:** never expose sequential database IDs in URLs or responses. Use ULIDs for public identifiers, so nobody can guess or count records. Orders also get a human-friendly order number per store (`#1042`), which is display-only and never used for lookups that bypass authorization.
- **Idempotency:** endpoints that create money-related records (checkout, payments, refunds) accept an `Idempotency-Key` header, so a retried request never creates a duplicate.
- **Dates** are ISO 8601 with offset.
- **Money in and out uses integer minor units.** Responses use `MoneyResource` (`{"amount": 1999, "currency": "USD", "formatted": "$19.99"}`). Requests send amounts as integers in minor units (`1999`, never `"19.99"` or `19.99`), validated as integers in Form Requests. Floats are rejected everywhere.
- **Success responses have no custom wrapper.** Return API Resources in Laravel's standard shape (`data`, plus `links` and `meta` for paginated lists). Never wrap them in an envelope such as `{success, message, data}`: it hides each Resource's real shape from Scramble and duplicates what HTTP status codes already say. Only errors use the shared error format above.

### Strong typing

- `declare(strict_types=1);` at the top of every PHP file.
- Every parameter, property, return type and constant has a native PHP type. Avoid `mixed`; use it only when truly unavoidable, with a comment explaining why.
- Controller methods declare their real return type (`: ProductResource`, `: AnonymousResourceCollection`, `: JsonResponse`). Scramble reads these to generate the docs, so a missing or vague type means wrong docs.
- Use PHPDoc generics where PHP can't express the type: `@return Collection<int, Product>`, `@param array<string, int> $quantities`.
- Model properties are documented with `@property` annotations. Casts use enums and value objects.

### Docblocks

Every class and every public method has a docblock. The **first line is a plain-English summary a non-technical person can understand**: what it does and why it matters to the business, not how it works.

```php
/**
 * Cancels a customer's order and returns any reserved stock to the shelf.
 *
 * Only unpaid orders can be cancelled. Paid orders must be refunded instead.
 *
 * @throws OrderCannotBeCancelledException When the order has already been paid or shipped.
 */
public function handle(Order $order): Order
```

- Avoid jargon in the summary. Write "Saves a new product to the store's catalog", not "Persists the Product entity."
- Add `@param`, `@return` and `@throws` tags where they add information beyond the native types.
- Don't write docblocks that only repeat the method name ("Gets the product").

### API documentation with Scramble

The API documentation is generated by Scramble (`dedoc/scramble`). **Read the Scramble skill before creating or changing any endpoint**, and follow it for how endpoints, parameters, responses and errors are documented.

- Controller method docblocks become each endpoint's title and description, so write them for an API consumer: what it does, who can call it, and anything surprising about it.
- Scramble infers request bodies from Form Requests, and responses from Resources and return types. That's why both are mandatory and must be strongly typed.
- The landlord API and the tenant API are separate audiences. Document them separately if the Scramble skill supports it.
- After adding or changing an endpoint, check that the generated docs are correct: parameters, response shape and error responses.

---

## 12. Naming conventions

Names must say exactly what a thing is or does, so the code reads without explanation. When unsure, choose the longer, clearer name. If a good name is hard to find, the class is probably doing too much, so split it.

### Banned everywhere

- Vague names: `Helper`, `Helpers`, `Utils`, `Util`, `Common`, `Misc`, `Manager`, `Handler` (except webhook handlers), `Processor`, `Core`, `Stuff`, `Base` (except abstract parents in `App\Shared`)
- Entity services: `ProductService`, `OrderService`, `UserService`
- Abbreviations: `Prod`, `Cat`, `Cust`, `Mgr`, `Qty`, `Amt`, `Addr`, `Usr`, `Cfg`. Common acronyms like `Api`, `Id`, `Url`, `Sms` and `Otp` are fine.
- Version or state words: `New`, `Old`, `V2`, `Final`, `Temp`, `Copy`, `Fixed`
- Hungarian notation or type prefixes: `IPaymentGateway`, `strName`, `arrItems`
- Suffixes that repeat the folder: `PaymentGatewayInterface`, `OrderStatusEnum`, `HasMoneyTrait`, `CreateProductAction`

### Folders

- PascalCase.
- Type folders are plural: `Actions`, `Models`, `Events`, `Enums`.
- Domain folders are business nouns: `Catalog`, `Orders`, `Customers`, `Inventory`.

### Classes

| Kind | Pattern | Examples |
|---|---|---|
| Model | Singular noun | `Product`, `OrderItem` |
| Action | Verb + noun | `CreateProduct`, `CancelOrder`, `RefundPayment` |
| Service | Noun naming its role, usually -er/-or | `PriceCalculator`, `TaxCalculator` |
| DTO | Noun + `Data` | `ProductData`, `CheckoutData` |
| Controller (resource) | Singular noun + `Controller` | `ProductController` |
| Controller (single action) | Verb + noun + `Controller`, invokable | `PublishProductController` |
| Form request | Verb + noun + `Request` | `StoreProductRequest`, `UpdateProductRequest` |
| API resource | Noun + `Resource` | `ProductResource` |
| Event | Noun + past-tense verb | `OrderPlaced`, `PaymentRefunded` |
| Listener | What it does + when | `MarkOrderAsPaidWhenPaymentSucceeds`, `SendOrderConfirmationOnWhatsApp` |
| Job | Verb + noun | `SyncInventoryLevels`, `GenerateSalesReport` |
| Policy | Model + `Policy` | `ProductPolicy` |
| Enum | Singular noun | `OrderStatus`, `PaymentMethod` |
| Contract (interface) | Noun for the capability | `PaymentGateway`, `MessageChannel` |
| Trait (in `Concerns/`) | `Has…` or adjective | `HasMoney`, `Searchable` |
| Exception | Problem + `Exception` | `InsufficientStockException` |
| Export | Plural noun + `Export` | `ProductsExport`, `OrdersExport` |
| Import | Plural noun + `Import` | `ProductsImport`, `CustomersImport` |
| Middleware | What it ensures | `EnsureModuleIsEnabled`, `ForceJsonResponse` |
| Service provider | Name + `ServiceProvider` | `HrServiceProvider` |
| Integration implementation | Provider name + contract | `WhatsAppChannel`, `PaystackGateway` |

### Methods and variables

- Methods are camelCase and start with a verb: `calculateTotal()`, `markAsPaid()`, `sendInvoice()`.
- Every Action has one public method, `handle()`.
- Methods returning booleans start with `is`, `has`, `can` or `should`: `isPaid()`, `hasStock()`, `canBeRefunded()`.
- Eloquent relationships are named after what they return: `items()` (has many), `customer()` (belongs to).
- Query scopes describe the filter: `scopeActive()`, `scopePublished()`.
- Variables are camelCase, and collections and arrays are plural: `$order`, `$orderItems`.
- Boolean variables and properties read as yes/no questions: `$isActive`, `$hasDiscount`, `$shouldNotify`.
- Include units when a number has one: `$timeoutInSeconds`, `$weightInGrams`.
- Don't use generic names (`$data`, `$result`, `$temp`, `$item`, `$value`, `$info`, `$obj`) outside a very short closure. Name what it actually holds: `$validatedProduct`, `$paidOrders`.
- Constants are SCREAMING_SNAKE_CASE and named for meaning: `MAX_CART_ITEMS`, not `FIFTY`.
- Enum cases are PascalCase: `OrderStatus::Pending`.
- No single-letter variables except loop indexes.

### Database

- Tables: plural snake_case (`products`, `order_items`). Pivot tables: singular names in alphabetical order (`category_product`).
- Module tables are prefixed with the module key to avoid collisions: `hr_employees`, `restaurant_tables`.
- Columns: snake_case. Foreign keys: `{singular}_id`. Booleans: `is_` or `has_` prefix (`is_active`). Timestamps: `_at` suffix (`published_at`).
- Money columns store integer minor units with a matching currency column: `price_amount`, `price_currency`.
- Migration files use Laravel's default names (`create_products_table`).

### Routes, config and other files

- URIs: plural kebab-case nouns: `/api/v1/products`, `/api/v1/product-categories`.
- Route names: dot notation matching the resource: `products.index`, `orders.cancel`. Module routes are prefixed with the module key: `hr.employees.index`.
- Config files and keys: snake_case: `config/restaurant.php`, `table_reservation_limit`.
- Tests describe behaviour: `it('cancels an unpaid order')`.

---

## 13. Clean code patterns

- Classes are `final` by default. Only remove `final` when a class is genuinely designed to be extended.
- DTOs and value objects are `readonly` classes.
- Use enums instead of magic strings or numbers for statuses, types and methods.
- Use constructor injection for dependencies in Actions, Services and Listeners. Don't resolve from the container mid-method.
- Return early. Use guard clauses instead of nested `if`s, and never `else` after a `return`.
- Keep methods short and focused on one job. If a section needs a comment explaining it, extract it into a named method.
- **Controllers:** Form Request, Policy, one Action, Resource. No queries or business logic.
- **Models:** relationships, casts, scopes and small accessors only. No business workflows.
- **Actions:** own the business logic. Wrap multi-step writes in a database transaction.
- **Services:** only shared calculations used by several Actions (`PriceCalculator`). Never entity services.
- Events that trigger side effects (emails, WhatsApp messages, webhooks) dispatch after the transaction commits.
- Never swallow exceptions silently. Throw a named domain exception extending `App\Shared\Exceptions\DomainException`, and map it to an API response in `bootstrap/app.php`.
- Avoid N+1 queries: eager-load relationships that Resources use.
- Format with Laravel Pint (`vendor/bin/pint --dirty --format agent` after changing PHP files) and analyse with Larastan at a high level. Fix warnings rather than suppressing them.

### Data integrity

- **Financial records are never deleted or rewritten.** Orders, order lines, payments, refunds, invoices and tax records keep the values they had at the time (prices, tax, exchange rates, product names). Corrections are made with new records (refunds, adjustments, credit notes), never by editing history.
- Use soft deletes for things merchants may want back (products, categories, customers), and make sure soft-deleted records still show correctly on old orders.
- Use database constraints (foreign keys, unique indexes, not-null) as the last line of defence, not just validation.
- Stock changes are always recorded as stock movements, and concurrent updates (two customers buying the last item) are protected with row locks or atomic updates, so stock can never go negative by accident.

### Scheduled and background work

- Scheduled tasks that act on stores (abandoned carts, promotion start and end, subscription renewals, report generation) run **per tenant**, using the tenancy package's way of iterating tenants. They respect each store's timezone.
- Jobs are small, retry-safe and idempotent, with sensible timeouts and retry limits. A failing job for one tenant must never block other tenants' jobs.
- Long or heavy work (imports, exports, image conversions, bulk updates, reindexing) always runs on a queue, never inside a request.
- **Queue workers** must listen on every queue the app uses (at least `default` and `bulk`); otherwise scheduled work such as retention purges never runs. Keep the list of queues in deployment config.
- **Fairness between tenants:** one busy store must never delay other stores. Keep urgent work (order confirmations, payment processing) on separate queues from bulk work (imports, exports, reindexing). Split bulk work into small chunks, and rate-limit bulk jobs, imports and incoming webhooks **per tenant**, so no single store can fill a queue.

### Complex business logic, simple code

The business rules in an e-commerce platform can be heavy. The code that implements them must still be simple, clear and easy to follow.

- **Break heavy logic into small, named steps.** A complex Action reads like a list of business steps, each a well-named private method or a smaller Action: `ensureOrderCanBeCancelled()`, `releaseReservedStock()`, `refundPaymentIfCaptured()`.
- **Prefer obvious code over clever code.** No dense one-liners, nested ternaries or tricks that need a second read to understand.
- **One level of abstraction per method.** Don't mix high-level business steps with low-level details in the same method.
- **Make illegal states impossible** where you can, with enums, value objects and guard clauses, instead of checking for them everywhere.

### No shortcuts

Always do it the standard, correct way, even when a shortcut would be faster.

- Never skip validation, authorization, transactions or tests to "get it working."
- Never suppress errors or warnings (`@`, empty `catch`, `@phpstan-ignore`, `// @codeCoverageIgnore`) to make a problem disappear. Fix the cause.
- Never hard-code IDs, emails, URLs, secrets, tenant names, currencies or magic values.
- Never leave `TODO`, commented-out code, `dd()`, `dump()` or debug logging in finished work.
- Never use raw SQL or `DB::` queries where Eloquent or the query builder does the job safely.
- If doing it properly needs more time or a decision from me, say so instead of taking a shortcut.

---

## 14. Security (non-negotiable)

This platform handles many businesses' money, customers and personal data. **Security outranks speed and convenience.** When in doubt, choose the safer option and tell me.

**Tenant isolation**
- One tenant must never be able to see or change another tenant's data, files, cache or jobs.
- Every tenant-facing feature has a test proving that tenant A cannot access tenant B's data.
- Never trust a tenant ID, store ID or similar value sent in a request. The tenant always comes from tenancy identification.

**Authentication and authorization**
- Every endpoint is either explicitly public or protected by the correct guard. There is no endpoint whose access is left to chance.
- Every action a staff user takes is authorized through a Policy. Being logged in is never enough.
- Scope route model binding so a user can only load records they're allowed to see. This prevents users changing an ID in the URL to see someone else's order.
- Sanctum tokens have abilities and an expiry. Platform admin and staff accounts should support two-factor authentication.
- Platform admin impersonation of a store, if built, is logged and clearly visible in the activity log.

**Input and output**
- All input is validated by Form Requests (section 11). Never pass `$request->all()` into `create()` or `update()`. Use `$request->validated()` or DTOs, and keep `$fillable` explicit on every model.
- Never build queries from raw user input. No string-concatenated SQL.
- API Resources expose only intended fields. Never return secrets, tokens, hashes, internal IDs or other tenants' data.
- Error responses in production never reveal stack traces, SQL or internal paths.

**Abuse protection**
- Rate-limit login, registration, password reset, OTP, checkout, coupon redemption and all public endpoints.
- **Every rate-limit key includes the store** (for tenant routes), so an attacker in one store can never lock out users of another store. Emails and phone numbers in rate-limit keys are hashed.
- Login and password-reset responses never reveal whether an email exists.
- Validate uploads strictly: allowed MIME types, file size limits, no executable files, and no SVG uploads unless sanitised. Store uploads privately, with access through signed URLs where appropriate.

**Secrets and sensitive data**
- Integration credentials (API keys, tokens, webhook secrets) are stored encrypted with Laravel's `encrypted` cast, never in plain text, and never returned by the API after saving.
- Secrets live in environment variables or encrypted storage, never in code or the repository.
- Never log passwords, tokens, card data, full personal data or request bodies containing them.
- Passwords are hashed with Laravel's default hasher. Never store or compare them in plain text.

**Payments and webhooks**
- Verify every webhook's signature before doing anything with it, and reject unsigned or invalid requests.
- Never trust amounts, prices or totals sent by the client. Always recalculate them on the server from the database.
- Confirm a payment with the gateway on the server side before marking an order paid. A redirect back from the gateway is not proof of payment.
- **Fail closed.** A payment is only verified when the gateway's confirmation includes a successful status, the exact expected amount **and** the expected currency. If any of these is missing, unclear or different, the payment is not verified. Test this with missing, tampered and mismatched gateway replies, and with a 3-decimal currency.
- **Two separate payment contracts:** stores charging shoppers use `Tenant\Payments\Contracts\PaymentGateway`; Sellora charging merchants uses a separate contract in `Landlord\Billing`. Never combine them into one interface.
- Payment and webhook processing is idempotent (section 3.3).

**Platform and dependencies**
- Configure CORS to allow only known storefront and dashboard origins, and add standard security headers.
- Run `composer audit` regularly and before releases. Don't add packages without asking (section 2).
- Write tests for security rules, not just happy paths: wrong tenant, wrong guard, missing permission, invalid signature, tampered price.

If you notice a security problem anywhere, in the legacy project, in this repository, or in a request I make, raise it immediately, even if it's outside the current task.

---

## 15. How to work in this codebase

- Before a large change, outline the plan and the files affected, and wait for approval.
- Make changes in small, reviewable steps. After each step, run `composer dump-autoload`, `vendor/bin/pint --dirty --format agent`, Larastan, and the tests described in section 2.3.
- When moving or renaming a file, update its namespace and every reference, then search for the old namespace to confirm nothing still points to it.
- Write tests next to the code they cover: core tests in `tests/`, module and integration tests in their own `tests/` folders.
- When porting from `tenant-ecommerce-api`, rename everything to match these conventions. Port the behaviour, not the code shape.
- Before creating any class, check that its name and location follow sections 5 and 12.
- If something is ambiguous, ask rather than guess. Wrong guesses on structure are expensive to undo.
- Commit in small, focused commits with clear messages that say what changed and why. Never commit secrets, `.env` files or generated files.
- **Unpushed commits may be cleaned up** (for example folding a fix into the commit that introduced the bug); pushed commits are never rewritten.
- **Every commit must pass on its own.** Before each commit, clear Larastan's result cache and run Larastan plus that commit's tests with only that commit's changes in place. A test may never depend on code from a later commit.
- Claude Code never edits this `CLAUDE.md` itself; proposed changes go in the report (section 2.3).
- A CI pipeline (for example GitHub Actions) runs Pint, Larastan, Pest (including architecture and tenant-isolation tests) and `composer audit` on every push. Nothing is merged while CI fails.

## 16. Decisions still open

Don't build anything that depends on these until I decide. If a task needs one, stop and ask.

- **Drivers:** per store (current design) or a shared Sellora driver network (section 10).
- **Themes:** whether and when to add `Landlord\Themes` and theme settings. (Storefront content itself is decided: `Tenant\Storefront`.)
- **Queue monitoring:** whether to add `laravel/horizon`.
- **Search engine** beyond Scout's database engine (Meilisearch, Typesense or other).
- **Platform domain** (`sellora.com` or other).
- **Merchant billing for global merchants:** Paystack bills African merchants (section 3.3). Whether Stripe Billing is used for merchants elsewhere depends on where Sellora's company is registered, which I'll decide later.

## 17. Build order (approved)

Build in this order, one step at a time. Each step ends with Pint, Larastan, the architecture and tenant-isolation tests, Scramble checks for new endpoints, and a **stop for my review**. At the start of each step, read the matching legacy code and list any rule you think is wrong before porting it.

**Phase A: foundation**
1. **API conventions** (`App\Shared`): error format, translation files, ULID public IDs, pagination limits, `Idempotency-Key` middleware, named rate limiters, CORS and security headers, privacy registry contract, retention config with a scheduled purge, per-tenant rate-limit helpers.
2. **Money** (`App\Shared\Money`): fixes F2 (ISO 4217 decimals, half-up rounding, allocation, `MoneyCast`, `Decimal` type for internal precision).
3. **Plan gating** (`App\Shared\Features` + minimal `Landlord\Plans` and `Landlord\Subscriptions`): feature states, usage limits, module and integration middleware. No merchant billing.
4. **Identity and auth** for all four guards, then **4b: two-factor authentication**.
5. **Store registration** (`Landlord\Tenancy` + `Landlord\Legal`): server pool, region, `RegisterStore`, terms acceptance, platform-admin store management, tenant export and purge. Custom domains come later.

**Phase B: core commerce**

6. Settings + Geography → 7. Catalog → 8. Inventory → 9. Pricing and tax → 10. Customers → 11. Promotions → 12. Cart → 13. Shipping → 14. Orders → 15. Payments (fixes F1) → 16. Checkout → 17. Delivery → 18. Returns → 19. Storefront → 20. Messaging (email channel first).

**Phase C: after the core shopping flow works**

Custom domains, merchant billing, the first real payment gateways, Reverb broadcasting (once compatible), `Tenant\Metafields`, then modules from section 18 in the order I choose.

## 18. Roadmap and decided scope

These decisions came out of the legacy review. Follow them, and don't build anything marked "later" without my go-ahead.

**Core, now:** everything in section 5.1, including `Tenant\Returns`, `Tenant\Storefront` and `Landlord\Legal` (merchants accept Sellora's terms at registration).

**Core, later phase:** `Tenant\Metafields` (custom fields merchants add to products, customers and orders), after the main shopping flow works.

**Landlord, later:** `Landlord\Partners` (referral/affiliate program, with an `affiliate` guard added only then) and `Landlord\Support` (start with a third-party helpdesk tool instead of building one).

**Module catalogue.** Use these names when modules are built; legacy names in brackets.

| Module | Status | Notes |
|---|---|---|
| `Hr` | roadmap | classes named without an `Hr` prefix (`Employee`, not `HrEmployee`); only tables get `hr_` |
| `Restaurant` | roadmap | tables, reservations, modifiers, kitchen display |
| `Manufacturing` | roadmap | bills of materials, work orders |
| `AdvancedReporting` | roadmap | includes "Store insights" (legacy AiAssistant, which is not AI) |
| `Accounting` | roadmap | ledger, expenses and income (legacy Expenses; "Biller" becomes "Payee") |
| `Pos` | roadmap | point of sale; terminal providers are integrations |
| `GiftCards`, `Loyalty` (RewardPoints), `Installments` | roadmap | plug into checkout through extension points (section 6) |
| `RecurringOrders` (ProductSubscriptions) | roadmap | renamed to avoid confusion with platform subscriptions |
| `MultiLocation` | roadmap | multiple warehouses, transfers, per-location stock; a single location is core |
| `Purchasing`, `B2B` (SalesQuotations), `SalesCommissions` (SalesAgents) | roadmap | back-office add-ons |
| `Booking`, `ApprovalWorkflows`, `Engagement` (BackInStock), `ContentMarketing` (blog, FAQ), `Helpdesk` (customer Support) | roadmap | optional add-ons |
| `Marketplace` | later | multi-vendor; adds a `seller` guard only when built |
| `Repair` | later | postponed until there's demand |
| `Projects` | **dropped** | not commerce; don't port |

**Integrations:** payment gateways (Stripe, Paystack, Flutterwave), SMS providers (Termii, Africa's Talking), WhatsApp, push notifications, POS terminals (Moniepoint, OPay), WooCommerce sync and social commerce. Each is its own folder in `integrations/`, never inside `app/Shared`. Country-specific tax rules (such as India's GST split) are plug-ins behind `TaxCalculator`, never inside core.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== dedoc/scramble/core rules ===

## Scramble

This project uses `dedoc/scramble` to generate OpenAPI documentation from application code. Prefer inference over redundant annotations.

Follow the `scramble-development` skill when changing API endpoints or the resources, FormRequests, shared types, and authentication they use, or when configuring or troubleshooting Scramble documentation.

=== maatwebsite/excel/core rules ===

# Laravel Excel

- Use `maatwebsite/excel` for spreadsheet exports, imports, queued spreadsheet work, CSV handling, and PhpSpreadsheet integration in Laravel applications.
- Prefer explicit export/import classes with package concerns over ad-hoc spreadsheet generation in controllers, jobs, or commands.
- Activate the `laravel-excel` skill when working with `Excel::download()`, `Excel::store()`, `Excel::queue()`, `Excel::raw()`, `Excel::import()`, `Excel::toArray()`, `Excel::toCollection()`, export/import concerns, queued imports/exports, validation, CSV settings, styling, events, formulas, charts, drawings, multiple sheets, mapped cells, macros, config, cache, transactions, temporary files, or `Excel::fake()`.
- For broad docs, all-feature tasks, or missing-feature audits, use the skill's `references/package.md` feature matrix before answering.
- For large datasets, prefer `FromQuery` with queued exports or `WithChunkReading` and `WithBatchInserts` for imports.
- Test spreadsheet behavior with `Excel::fake()` when asserting dispatch/download/store/import intent, and inspect generated files only when cell contents, formatting, sheets, or writer behavior must be proven.

=== spatie/laravel-activitylog/core rules ===

# spatie/laravel-activitylog

Activity logging package for Laravel. Logs model events and manual activities to a database table.

## Key Concepts

- **Activity**: An Eloquent model (`Spatie\Activitylog\Models\Activity`) storing log entries with subject, causer, event, attribute_changes, and properties.
- **Subject**: The model being acted upon (polymorphic `subject_type`/`subject_id`).
- **Causer**: The model that caused the action, typically the authenticated user (polymorphic `causer_type`/`causer_id`).
- **LogOptions**: Fluent configuration object returned by `getActivitylogOptions()` on models using the `LogsActivity` trait.
- **ActivityEvent**: Enum with cases `Created`, `Updated`, `Deleted`, `Restored`.
- **`attribute_changes`** column: stores `{"attributes": {...}, "old": {...}}` for tracked model changes.
- **`properties`** column: stores custom user data set via `withProperties()`.

## Traits

### `LogsActivity`

Add to models to automatically log create/update/delete events. Optionally implement `getActivitylogOptions()` to configure which attributes to track (defaults to logging events without attribute changes).

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Article extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
```

### `CausesActivity`

Add to user/causer models. Provides `activitiesAsCauser()` relationship.

### `HasActivity`

Combines `LogsActivity` and `CausesActivity`. Provides `activities()`, `activitiesAsSubject()`, and `activitiesAsCauser()`.

## Manual Logging

```php
activity()
    ->performedOn($article)
    ->causedBy($user)
    ->event(ActivityEvent::Updated)
    ->withProperties(['key' => 'value'])
    ->log('Article was updated');
```

## LogOptions Methods

| Method | Description |
|--------|-------------|
| `logFillable()` | Log all fillable attributes |
| `logAll()` | Log all attributes |
| `logOnly(array)` | Log specific attributes |
| `logExcept(array)` | Exclude attributes |
| `logOnlyDirty()` | Only log changed attributes |
| `dontLogEmptyChanges()` | Skip logging when no tracked attributes changed |
| `dontLogIfAttributesChangedOnly(array)` | Ignore updates that only change these attributes |
| `useLogName(string)` | Set custom log name |
| `setDescriptionForEvent(Closure)` | Custom description per event |
| `useAttributeRawValues(array)` | Store raw (uncast) values |

## Querying Activities

```php
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Enums\ActivityEvent;

Activity::forEvent(ActivityEvent::Created)->get();
Activity::causedBy($user)->get();
Activity::forSubject($article)->get();
Activity::inLog('orders')->get();
```

## Setting the causer

Override the causer for a block of code:

```php
use Spatie\Activitylog\Facades\Activity;

Activity::defaultCauser($admin, function () {
    // all activities here are caused by $admin
});

// or set globally for the rest of the request
Activity::defaultCauser($admin);
```

## Disabling Logging

```php
activity()->withoutLogging(function () {
    // no activities logged here
});
```

## Accessing Changes and Properties

```php
$activity = Activity::latest()->first();

// Tracked model changes (set automatically by LogsActivity)
$activity->attribute_changes; // Collection: {"attributes": {...}, "old": {...}}

// Custom user data (set via withProperties)
$activity->properties; // Collection
$activity->getProperty('key'); // single value
```

## Custom Activity Model

Set `activity_model` in `config/activitylog.php` to a class that extends `Model` and implements `Spatie\Activitylog\Contracts\Activity`. Use a custom model for custom table names or database connections.

## Customizing Actions

The package uses action classes (`LogActivityAction`, `CleanActivityLogAction`) that can be extended and swapped via config:

```php
// config/activitylog.php
'actions' => [
    'log_activity' => \App\Actions\CustomLogActivityAction::class,
    'clean_log' => \App\Actions\CustomCleanAction::class,
],
```

Custom action classes must extend the originals. Override protected methods (`save()`, `beforeActivityLogged()`, `resolveDescription()`, etc.) to customize behavior.

## Configuration

Key config options in `config/activitylog.php`:
- `enabled`: Master on/off switch (env: `ACTIVITYLOG_ENABLED`)
- `clean_after_days`: Days to keep records for `activitylog:clean` command
- `default_log_name`: Default log name (string)
- `default_auth_driver`: Auth driver for causer resolution
- `include_soft_deleted_subjects`: Include soft-deleted subjects
- `activity_model`: Custom Activity model class
- `default_except_attributes`: Globally excluded attributes
- `actions.log_activity`: Action class for logging activities
- `actions.clean_log`: Action class for cleaning old activities

=== spatie/laravel-medialibrary/core rules ===

## Media Library

- `spatie/laravel-medialibrary` associates files with Eloquent models, with support for collections, conversions, and responsive images.
- Always activate the `medialibrary-development` skill when working with media uploads, conversions, collections, responsive images, or any code that uses the `HasMedia` interface or `InteractsWithMedia` trait.

</laravel-boost-guidelines>
