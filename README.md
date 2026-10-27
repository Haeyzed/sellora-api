# Sellora API

Sellora is a multi-tenant e-commerce platform. Merchants run independent
stores, each with its own database, while Sellora operates a central platform
for store registration, plans, platform staff and tenant management.

This repository contains the Laravel API used by the platform admin app,
merchant dashboards and storefronts. It is a modular monolith organized into
`app/Landlord`, `app/Tenant`, `app/Shared`, `modules` and `integrations`.

Start with the [project and domain documentation](docs/README.md) for a
plain-language overview of the landlord/tenant split and the store purchase
flows.

## Requirements

- PHP 8.4 and Composer
- PostgreSQL (the central database and tenant databases)
- Redis (cache, sessions and queues in the default local configuration)
- Node.js and npm (Vite assets and frontend development)
- Laravel Herd is the recommended local environment on Windows

The PHP requirement and framework/package versions are declared in
[`composer.json`](composer.json). Local service and application settings are
listed in [`.env.example`](.env.example).

## Local setup with Herd on Windows

### 1. Start required services

Start PostgreSQL and Redis using Herd or your local service manager. Create
the central PostgreSQL database; the example environment calls it
`sellora_central`.

### 2. Configure the environment

From the repository root, copy the example environment and edit the database,
Redis, mail and domain values to match your machine:

```powershell
Copy-Item .env.example .env
```

At minimum, check these values in `.env`:

```dotenv
APP_URL=http://sellora-api.test
PLATFORM_DOMAIN=sellora-api.test
PLATFORM_CENTRAL_DOMAINS=sellora-api.test,localhost,127.0.0.1

DB_CONNECTION=central
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sellora_central
DB_USERNAME=postgres
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Use credentials appropriate for your local PostgreSQL installation. Keep
secrets in `.env`; do not commit them.

### 3. Install and initialize

Run the repository's setup script:

```powershell
composer setup
```

The script installs PHP and JavaScript dependencies, creates an application
key, runs central migrations, synchronizes permissions, and builds frontend
assets. The database must exist and be reachable first.

Seed the central reference data, starter plan and platform permission data:

```powershell
php artisan db:seed --force
```

### 4. Open the API

Herd serves this project at `http://sellora-api.test`. Confirm that the
application responds at:

```text
http://sellora-api.test/up
```

For Windows Herd setup, API addresses, tenant subdomains and sign-in examples,
see the [landlord API testing guide](docs/landlord/api-testing.md) and the
[tenant API testing guide](docs/tenant/api-testing.md).

## Running locally

Herd serves the HTTP site. In separate terminals from the project root, start
the queue worker and (when actively changing frontend assets) Vite:

```powershell
php artisan queue:listen --queue=payments,default,bulk --tries=1 --timeout=0
```

```powershell
npm run dev
```

The queues are ordered so gateway checks and webhooks (`payments`) are not
stuck behind general or bulk work. `npm run dev` is optional if you only
need the API and have already built assets.

The Composer `dev` script starts `php artisan serve`, a queue listener and
Vite together. It is intended for setups without Herd; do not run it when
Herd is already serving the same site.

### Creating a platform administrator

After setup and migrations, create the first platform administrator with:

```powershell
php artisan platform:create-admin --name="Platform Admin" --email="admin@example.com" --super-admin
```

The command prompts for the password and guides you through authenticator
setup. Store the displayed recovery codes securely. Platform administrators
must use two-factor authentication.

### Registering and provisioning a local store

Store registration uses a database-server pool. To test the full registration
flow locally, add a reachable PostgreSQL server for a configured hosting
region:

```powershell
php artisan platform:add-database-server
```

The command prompts for the database-server password and tests the connection
before saving it. The database account must be able to create tenant
databases. Each store subdomain also needs a Windows hosts-file entry; the
[tenant API testing guide](docs/tenant/api-testing.md) shows the example.

## Tests and useful commands

Run the full test suite:

```powershell
composer test
```

Run a focused test file:

```powershell
php artisan test --compact tests/Feature/Shared/QueueWorkersTest.php
```

Export the platform, registration and store OpenAPI documents:

```powershell
composer docs:export
```

The generated JSON files are written to the untracked `api-docs/` directory.

## Documentation

- [Documentation home](docs/README.md)
- [Landlord: central platform](docs/landlord/README.md)
- [Tenant: one merchant store](docs/tenant/README.md)
- [End-to-end store purchase flow](docs/tenant/business-flows.md)
- [Landlord and registration API testing](docs/landlord/api-testing.md)
- [Tenant API testing](docs/tenant/api-testing.md)
- [Deployment](docs/deployment.md)

## Important boundaries

- The central database stores the platform's store directory and management
  data; each tenant database stores that merchant's business data.
- Tenant Payments handles shopper payments for merchant orders. Landlord
  subscription billing is not implemented yet.
- Tenant Delivery currently supports driver sign-in, not shipment assignment.
- The Messaging tenant domain is a placeholder and does not provide a central
  messaging workflow.

See [CLAUDE.md](CLAUDE.md) for the repository's architecture and engineering
constraints.
