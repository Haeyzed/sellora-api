# Deploying Sellora

Run these on every deploy, in this order, after the new code is in place and
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

Workers must listen on every queue the app uses, or that work never runs
(store exports, for example, go on `bulk`):

```sh
php artisan queue:work --queue=default,bulk
```

## Scheduler

Run `php artisan schedule:run` every minute (retention purges, purge
reminders, closed-store purges).
