# Sellora documentation

Start here if you are new to the project.

## What Sellora is

Sellora is an e-commerce platform for merchants anywhere in the world. A
merchant signs up, gets their own store with its own address, and sells
online. This repository is the Sellora API: the platform admin app, merchant
dashboards, storefronts and the driver app are separate frontends that use
it.

One platform hosts many independent stores. Two words describe its two
sides:

- **Landlord** is the central platform. It knows which stores exist, where
  their databases are, what plans they are on, and which platform staff run
  the service.
- **Tenant** is one merchant's store. Its products, customers, orders and
  staff belong to that store alone, in that store's own database.

```mermaid
flowchart LR
    Shopper[Shopper] -->|store domain| Storefront[One store's API]
    Merchant[Merchant / store staff] -->|same store domain| Storefront
    Storefront -->|identify store, switch tenant context| TenantDB[(That store's database)]
    TenantDB --> StoreData[Catalog, inventory, cart, checkout, orders]

    PlatformStaff[Sellora platform staff] -->|central platform domain| PlatformAPI[Platform API]
    PlatformAPI --> CentralDB[(Central landlord database)]
    CentralDB --> Directory[Store directory, domains, hosting]
    CentralDB --> Plans[Plans, subscriptions, feature access]
    CentralDB --> PlatformUsers[Platform identities and legal records]
    PlatformAPI -->|provision / administer| TenantDB
```

The central database is not a shared pool of every store's sales. It keeps
what is needed to find and manage stores. When a request arrives on a
store's domain, the application finds that store in the central directory,
then switches into the store's own database before handling the request.

## The three zones of the code

| Zone | Where | What lives there |
| --- | --- | --- |
| Core | `app/` | What every store gets, the landlord side, and shared building blocks. Core business areas are called **domains** (Catalog, Orders, Payments…). |
| Modules | `modules/` | Business features a store gets only on plans that include them, such as Restaurant or Wallet. None is built yet. |
| Integrations | `integrations/` | Connections to outside services, also by plan. Built so far: Paystack (payments) and Open Exchange Rates (market exchange rates for the platform). |

"Module" only ever means a plan-gated feature in `modules/`.

## The four account types

Each kind of person signs in separately, with their own accounts and
tokens; a token for one never works for another.

| Who | Lives in | How they sign in |
| --- | --- | --- |
| **Platform admins**: Sellora's own team | Central database | Email and password, with two-factor authentication always on. The first one is created from the command line; later ones join by invitation. |
| **Store staff**: a merchant's owner and team | That store's database | Email and password; the owner can require two-factor authentication. Staff join only by invitation, and roles and permissions decide what each may do. |
| **Customers**: shoppers | That store's database | Email and password, or Google or Apple if the store offers them. An account in one store is separate from an account in another. Guests can also buy, if the store allows it. |
| **Drivers**: a store's own delivery drivers | That store's database | Phone number and PIN, for a driver app. |

## The main flows

1. **A merchant opens a store.** They choose a plan and a subdomain, accept
   the platform's current terms and verify their email. The platform creates
   the store's database in the chosen hosting region and the owner's account,
   then the store opens. See [store tenancy and registration](landlord/tenancy.md).
2. **The merchant sets up.** Store settings (currency, languages, tax mode,
   guest checkout), the team, products and variants, stock, prices and tax
   rates, extra currencies, promotions, shipping zones and methods, and
   payment methods: bank transfer, cash on delivery or Paystack.
3. **A shopper buys.** They browse, fill a cart, and check out. Checkout
   shows a quote that holds for a short time; confirming it reserves the stock
   and promotion uses and creates the order, which keeps a copy of everything
   charged. See [business flows](tenant/business-flows.md).
4. **Payment.** Online payments are confirmed by asking the gateway, never by
   trusting a redirect or a webhook alone; staff record bank transfers and
   cash. An invoice is issued once an order counts as a sale.
5. **Fulfilment.** Staff create shipments, which move from pending to shipped
   and delivered, or to ready for pickup and then handed over. Stock leaves
   the shelf only then. A store with its own drivers gives each delivery to
   a driver, who picks it up, hands it over with proof (and collects any
   cash on delivery) or reports it failed; the buyer can follow it while it
   is on the way. See [Delivery](tenant/delivery.md).
6. **Refunds and cancellations.** Staff refund through the original payment
   method; a refund that reduces a sale gets a credit note. Cancelling an
   unshipped order releases its stock and promotion uses.
7. **Running the platform.** Platform admins manage stores (suspend,
   reactivate, close, restore before purge), plans and per-store feature
   grants, legal documents and the platform team. A closed store's data is
   purged after its retention period; a store can export its data first.
8. **Customer privacy.** Customers can export their data and erase their
   account; financial and tax records are kept for as long as the law
   requires, without the personal details that aren't needed.

## Planned, not built yet

These are in the approved build order (CLAUDE.md section 17) but don't exist
yet:

- **Step 18b, Exchanges:** a return settled with a replacement order, and
  refunds to destinations other than the original payment. Returns with a
  refund are built ([Returns](tenant/returns.md)).
- **Step 19, Storefront:** content pages, menus, banners, SEO, wishlists,
  fetching several products for comparison, related and similar products and
  best sellers.
- **Step 20, Messaging:** one place for messages, email first
  ([Messaging](tenant/messaging.md)). Today each domain sends its own emails.
- **Step 21, Data tools:** import and export, bulk actions and dashboard
  numbers.
- **Phase C, after the shopping flow works:** custom domains, merchant
  billing ([Billing](landlord/billing.md)), Stripe and other gateways, live
  updates, custom fields, and modules.

## Local setup on Windows

The steps for a new Windows PC, in Windows PowerShell. Where work stands
and what comes next are in the [handover](HANDOFF.md).

1. **Git**, and an SSH key added to GitHub: the remote is
   `git@github.com:Haeyzed/sellora-api.git`. Check with `git --version` and
   `ssh -T git@github.com`.
2. **PHP 8.4 and Composer** from Laravel Herd (Herd's PHP 8.4, not 8.5).
   Run PHP, `php artisan` and Composer from PowerShell: Git Bash has no
   `php` on its path. Check `php -v`, then that `php -m` lists every
   extension CI uses: bcmath, gmp, intl, pdo_pgsql, pgsql, gd, exif, zip and
   redis. Node.js and npm are needed only for building assets.
3. **PostgreSQL 18**, installed on its own (Herd's free version runs no
   database). It must include the `pg_trgm` extension, which the standard
   Windows installer does. Use the port your PostgreSQL runs on (the
   default is 5432); that port goes in `DB_PORT`.
4. **Redis** on port 6379, for the cache, queues and sessions.
5. **Clone and install:**

   ```powershell
   git clone git@github.com:Haeyzed/sellora-api.git
   cd sellora-api
   composer install
   ```

   Windows file locks (antivirus or the search indexer) can make Composer
   fail while unpacking; run `composer install` again, up to three times.
6. **The `.env`:** copy the old machine's `.env` into the clone and keep its
   `APP_KEY`: encrypted data can be read only with the key that wrote it.
   Set `DB_PORT` and `DB_PASSWORD` for this PC's PostgreSQL. Only on a
   brand-new setup with no data to keep, copy `.env.example` and run
   `php artisan key:generate`. Don't use `composer setup` with a copied
   `.env`, since it runs `key:generate`.
7. **Databases:** create `sellora_central` and `sellora_central_testing`
   (for example in pgAdmin). The tests make everything else themselves:
   each parallel test process's own central database, and the store
   template database that test stores are copied from. Store databases are
   named with `TENANT_DATABASE_PREFIX` (`sellora_tenant_`, and
   `sellora_testing_tenant_` in tests). The database user must be allowed
   to create databases.
8. **Central database:**

   ```powershell
   php artisan migrate
   php artisan permissions:sync
   php artisan db:seed --force
   ```

9. **The pre-commit hook:** `composer hooks:install` (see below).
10. **VS Code:** turn off auto-save and format-on-save for this folder, so
    an editor never rewrites a file while a probe or a test run has it
    open. In `.vscode/settings.json` (git-ignored):

    ```json
    {
        "files.autoSave": "off",
        "editor.formatOnSave": false
    }
    ```

11. **Final check:** `composer ci` runs every check CI runs (Pint, the hook
    check, Larastan, composer audit, Scramble and the full Pest suite). When
    it passes, this PC matches CI.

Windows quirks worth knowing:

- PowerShell breaks the quotes in `php artisan tinker --execute`; put the
  code in a file and pass the file to `tinker` instead.
- `psql` run from Git Bash can fail to load its DLLs; run it from
  PowerShell, or use `.\scripts\db-status.ps1`.
- A test run that fails on database timeouts after the PC slept proves
  nothing; run it again on its own.

### Helper scripts

`scripts/` holds PowerShell helpers for local work. Each one explains itself
at the top, works in Windows PowerShell 5.1 and PowerShell 7, and writes its
output to the git-ignored `scripts/output/`. If Windows blocks them, run one
as `powershell -ExecutionPolicy Bypass -File .\scripts\probe.ps1 ...`.

| Script | What it does |
|---|---|
| `probe.ps1` | Breaks one rule on purpose, runs the tests named and says whether they caught it; the file is always restored byte for byte. Runs only on a clean working tree |
| `run-suite.ps1` | Runs a suite, the Landlord suite by default, one or more times, and keeps each run's full output |
| `wait-for-ci.ps1` | Waits for the GitHub Actions run of a commit and prints each job's result |
| `db-status.ps1` | A read-only look at the database server: leftover test databases and live sessions |

## Before you commit

Run this once in each clone (`composer setup` does it for you):

```sh
composer hooks:install
```

It points git at the hooks in `.githooks`. Before each commit, Pint then
checks the staged version of every staged PHP file and refuses the commit if
any fail. It works from PowerShell, Git Bash and any other shell, because git
runs the hook itself. To run the same check by hand, use
`composer hooks:check`. CI still runs Pint on everything.

## Choose a guide

- [Landlord: central platform](landlord/README.md): store registration,
  provisioning, plans, platform staff and legal terms.
- [Tenant: one merchant store](tenant/README.md): a store's settings,
  products, inventory, shoppers and orders.

Developer guides span both sides: [landlord and registration API
testing](landlord/api-testing.md) covers central endpoints, [tenant API
testing](tenant/api-testing.md) covers store-domain endpoints, and
[deployment](deployment.md) explains migrations and the work to run on every
release.

Field-level detail about tables and columns is not written by hand in these
guides; it lives in the database's own column comments.
