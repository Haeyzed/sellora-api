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
| `owen-it/laravel-auditing` | Field-level change history for sensitive data (old value → new value) | Only on sensitive models: prices, stock levels, payments, refunds, roles, store settings. Not on every model. |
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
| `spatie/laravel-activitylog` | `database/migrations/tenant/` | each store's activity. Add a landlord copy only if platform admin activity needs logging, and ask first. |
| `owen-it/laravel-auditing` | `database/migrations/tenant/` | each store's audit history |
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

This project uses **Laravel Boost**. Its guidelines are in the `<laravel-boost-guidelines>
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

=== spatie/laravel-medialibrary/core rules ===

## Media Library

- `spatie/laravel-medialibrary` associates files with Eloquent models, with support for collections, conversions, and responsive images.
- Always activate the `medialibrary-development` skill when working with media uploads, conversions, collections, responsive images, or any code that uses the `HasMedia` interface or `InteractsWithMedia` trait.

</laravel-boost-guidelines>
