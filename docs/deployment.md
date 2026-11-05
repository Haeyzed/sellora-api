# Deploying Sellora

Run this on every deployment, after the new code is in place and
`composer install --no-dev --optimize-autoloader` has run:

```sh
composer deploy
```

which runs:

1. `php artisan migrate --force`: the central (platform) database.
2. `php artisan tenants:migrate --force`: every store database. Stores with
   no database yet (being set up, or whose setup failed) are skipped; their
   setup migrates them.
3. `php artisan permissions:sync`: writes the permissions defined in code
   into the central database (platform guard) and every store database
   (staff guard), so a permission added in this release reaches every
   existing store (CLAUDE.md section 10). Safe to run any number of times.
   It ends with a failure if any store couldn't be synced; fix that store
   and run it again. A permission removed from code is deleted only once no
   role or person holds it; the command lists the ones it kept.
4. `php artisan queue:restart`: workers pick up the new code after their
   current job.

## Queue workers

Workers must listen on every queue the app uses, or that work never runs:
`payments` (checks with payment gateways and webhooks, set by
`PAYMENTS_QUEUE`), `default`, and `bulk` (store exports). Payments come first,
so money never waits behind bulk work:

```sh
php artisan queue:work --queue=payments,default,bulk
```

A job that fails every try is kept in the central `failed_jobs` table, from
every store, with its error scrubbed of a database error's SQL and values.
The daily retention purge deletes it after 30 days
(`RETENTION_FAILED_JOBS_DAYS`), because the job's own data can name a buyer.

Queued emails are encrypted with `APP_KEY` while they wait in the queue and
in `failed_jobs`, because they carry links with tokens, codes, email
addresses and names. What that means when deploying:

- **Jobs queued or failed before encryption** still run: a worker reads a
  job stored in plain text as before, and decrypts the rest. Those stored in
  plain text stay so; retrying one queues it again unchanged. They leave
  `failed_jobs` with the 30-day purge, and none are re-encrypted.
- **Workers still running the previous release** can run encrypted jobs too,
  as long as they share the same `APP_KEY`; `queue:restart` above moves them
  to the new code.
- **Changing `APP_KEY`:** put the old key in `APP_PREVIOUS_KEYS`
  (comma-separated) until every job queued before the change has run or left
  `failed_jobs`. Without it, those jobs can't be decrypted and fail; so does
  everything else encrypted with the old key.

## Scheduler

Run `php artisan schedule:run` every minute (retention purges, purge
reminders, closed-store purges).
