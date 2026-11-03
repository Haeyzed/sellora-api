# Handover

**Date:** 3 November 2026.
**Describes:** `main` at `f0432da`, and the branch `handover-scripts` at
`c3ad6db` and `fffe4e7` plus the commit that adds this file.

This is for continuing the work on a new PC: for the project owner, and for
a fresh Claude Code session that remembers nothing of the sessions before
it. The rules for working in this repository are in
[CLAUDE.md](../CLAUDE.md) and are not repeated here: read it first, all of
it. The product is described in the [documentation home](README.md).

A handover is a snapshot. Anything here can go out of date; when it
disagrees with `git log`, GitHub or CLAUDE.md, they win.

## 1. Where things stand

**`main`** is at `f0432da` ("Set aside, once, a refund on an order with no
invoice to credit"). CI passed on it: run 41645166398, all three jobs (Pest
on PostgreSQL 18 and Redis; Pint, Larastan and composer audit; Scramble).

**What was finished last:** the third round of fixes on `fix-payment-holds`,
merged into `main` by fast-forward:

| Commit | What |
|---|---|
| `8e80259` | The invoice check counts its grace period from `orders.money_changed_at`, set only when money moves; existing orders backfilled |
| `cefceb2` | A fully credited invoice has no tax left on any line or rate |
| `5af260d` | The daily invoice check also reports credit note tax that doesn't fit its invoice |
| `f0432da` | A refund on an order with no invoice is set aside once (`payment_refunds.no_credit_note_at`) and never picked up again |

**Branches:**

| Branch | State |
|---|---|
| `handover-scripts` | **Not merged, waiting for the owner's review.** Three commits on `main`: `c3ad6db` caps how many refund parts one sweep run checks (`orders.credit_note_checks_per_run`); `fffe4e7` adds [scripts/](../scripts); the third adds this file and the setup steps in the [documentation home](README.md) |
| `step-10-customers` to `step-18-returns`, `step-10b-social-sign-in`, `step-15b-invoices`, `step-16b-paystack` | Merged; kept on GitHub |
| `docs-schema-comments`, `fix-token-links`, `fix-payment-holds` | Merged; kept on GitHub |
| `ci-proof-docs`, `ci-proof-pint`, `ci-proof-stan` | Local only, on the old laptop, and deleted from GitHub. Each breaks one CI check on purpose, to prove CI catches it. Throwaway: never merge them, nothing is lost without them |
| Dependabot: `laravel/telescope` 5.26.0, `spatie/laravel-activitylog` 5.1.3, `spatie/laravel-medialibrary` 11.23.10, `spatie/laravel-permission` 8.3.2, `spatie/laravel-translatable` 6.15.0 | Open, not merged. Each needs its CI run checked and the owner's approval |
| Dependabot: `shivammathur/setup-php` 2.40.0 | Merged |

## 2. What comes next, in order

Each item is its own branch, starts only when the owner says so, and ends
with a stop for review (CLAUDE.md section 17).

### a. Error reports without personal data

**Why:** a database error's message holds the SQL, its values and
PostgreSQL's "Failing row" detail, which can include a buyer's email. Today
that text reaches error reports, logs, console output, the debug response
and failed jobs.

**Where it escapes:**

- `report($exception)` in `CheckPendingPaymentsCommand`,
  `ReconcileStockLevelsCommand`, `ReconcileInvoiceBalancesCommand`,
  `IssueMissingTaxDocumentsCommand`, `ReleaseExpiredReservationsCommand`,
  `SyncPermissionsCommand`, `BuildStoreExport`, `IssueRefundCreditNote`,
  `MissingTaxDocumentSweep` and `PaymentHolds` (settling and refunding).
- Every uncaught exception: `withExceptions` in
  [bootstrap/app.php](../bootstrap/app.php) only sets how errors are shown.
- Sentry's "no personal data" setting doesn't clean exception messages.
- Console output: the exception message printed by
  `CheckPendingPaymentsCommand`, `ReconcileStockLevelsCommand`,
  `IssueMissingTaxDocumentsCommand`, `ReleaseExpiredReservationsCommand`,
  `SyncPermissionsCommand` and `AddDatabaseServerCommand`.
- `ApiErrorRenderer` returns the message when debug is on.
- `failed_jobs.exception`, which stores the whole exception text.
- To check first: Sentry's SQL breadcrumbs and bindings, and Telescope.

**The plan:**

1. One central scrubber in `App\Shared\Exceptions`, grown from
   `OrderPaymentsNotRecordedException` (its describe method): from a
   database error it keeps only the SQLSTATE code and the constraint name.
2. A `map` in the exceptions setup in
   [bootstrap/app.php](../bootstrap/app.php) turns every database query
   error into a scrubbed exception with no link back to the original, so
   `report()` and uncaught errors are both covered in one place. Code that
   catches the original error first, such as `VariantDetails`, keeps
   working.
3. A Sentry `before_send` hook as a backstop.
4. The console commands above and the debug response use the scrubber.
5. `failed_jobs.exception` is scrubbed before it is stored.
6. Check whether the store export includes `failed_jobs`, and say so in the
   report either way.

**Tests:** force a constraint failure on a row holding a buyer's email, and
assert the email is absent from the log, the Sentry event, the console
output, the debug response and the failed job. Each path gets its own
probe.

### b. Step 18b: exchanges and the refund-destination extension point

From CLAUDE.md sections 17 and 18. An exchange is the return plus a linked
replacement order made by a staff action that reuses checkout's pricing,
stock and tax steps; the price difference is settled by a payment or a
refund. Where a refund goes becomes an extension point: the original
payment is core's default and only destination, and modules add others
through a core contract. Returns today is described in
[Returns](tenant/returns.md).

### c. Steps 19, 20 and 21

Storefront, then Messaging (email first, see [Messaging](tenant/messaging.md)),
then Data tools, as CLAUDE.md section 17 lists them.

### d. The frontend, after core

The frontends come after the core API is done. The frontend repository is
`Haeyzed/sellora-frontend`; see section 5 for its uncommitted files.

## 3. Open follow-ups

| Follow-up | Where it is recorded |
|---|---|
| **Audit ignore** GHSA-9vw2-f4vp-x7gf / PKSA-qhjm-rpqc-qkn6 is still in place. It is the same entry as the PHPUnit one below | [composer.json](../composer.json), `config.audit.ignore` |
| **PHPUnit ignore:** remove it once Pest allows PHPUnit 13.4.4 or later, then run `composer update` for PHPUnit and `composer ci` | The same entry's note in [composer.json](../composer.json) |
| **Step 20 alerts with no listener:** `OrderRefundedBeyondPaid`, `OrderPaidAfterCancellation`, `OrderPaymentsKeepFailing`, `PaymentHoldKeepsFailing`, `CommittedPaymentRefundFailed`, `PaymentDisputed`, `PaymentNeedsReview`, `PaymentOverpaid`, `PaymentUnresolved`, `PaymentGatewayNeedsReconnecting`, `RefundStillPending`, `RefundSucceededAfterFailing`, `CashCollectionOverdue`, `DeliveryFailed`, `StockRunningLow`, `VariantSoldOut` (16), plus `RefundNeedsReview` and `RefundFailed`, which have a listener but don't reach staff: 18 in all. Until Step 20, staff see none of them | Only here and in earlier review reports |
| **Return messages for Step 20:** `ReturnRequested`, `ReturnApproved`, `ReturnRejected`, `ReturnReceived`, `ReturnInspected`, `ReturnRefundStarted`, `ReturnCompleted`, `ReturnCancelled` | [Returns](tenant/returns.md) |
| **Real mixed-refund tests** (part of a refund went back, part failed, across payments) once `Wallet` or `GiftCards` exists; today only a fake gateway produces the mixed case | [Returns](tenant/returns.md), the "Never twice" table |
| **Split-paid refunds:** which part of a split-paid order goes back first is decided when the first payment module is built | CLAUDE.md section 18, the refund-destination rule |
| **Driver app, for the frontend:** a delivery is handed over in three calls: pick up, on the way, then delivered (multipart, with proof and the recipient's name, and an `Idempotency-Key`). The API also allows delivered straight after pick-up, but the buyer can follow a delivery only while it is on the way, so the app should not skip that call | [Delivery](tenant/delivery.md); the note itself only here |
| **Legal checks** before `Wallet`, `AccountCredit` or `Installments` launch (stored value, credit and instalment rules differ by country) | [Roadmap](roadmap.md), each module's section |
| **Reverb broadcasting** is not installed: it needs a stable release that allows `guzzlehttp/psr7` 3 | CLAUDE.md section 17, Phase C |
| **Open decisions** (themes, Horizon, search engine, platform domain, billing for merchants outside Africa) | CLAUDE.md section 16 |
| **Invoice check false alarms:** it can flag a refund whose credit note is still on its way; the grace period narrows this but doesn't remove it | Only here and in earlier review reports |
| **Release pending is shown only on the order:** the `refund_due` list filter doesn't include those orders, and customer anonymisation doesn't treat them as blocked | Only here and in earlier review reports |
| **A credit note edited outside the app** can leave tax behind that the final credit can't take back; the daily invoice check reports it | Only here and in earlier review reports |
| **The pre-commit hook was tried only on Windows** (PowerShell and Git Bash), never on real Linux | Only here |

## 4. New PC setup (Windows PowerShell)

The steps, with the machine facts behind them, are in the documentation
home: [local setup on Windows](README.md#local-setup-on-windows). In short,
and in this order:

1. Git, and an SSH key added to GitHub (the remote is
   `git@github.com:Haeyzed/sellora-api.git`).
2. Laravel Herd with PHP 8.4, which brings Composer. Check the PHP
   extensions.
3. PostgreSQL 18, and its port.
4. Redis.
5. Clone, copy in the old `.env` (section 5), `composer install`.
6. Create `sellora_central` and `sellora_central_testing`, run the central
   migrations, sync permissions and seed.
7. The test template database is built by the tests themselves; nothing to
   do.
8. Install the pre-commit hook.
9. Turn off VS Code's auto-save and format-on-save for this folder.
10. Run `composer ci` as the final check that the PC matches CI.

**Never run `composer setup` with a copied `.env`:** it runs
`php artisan key:generate`, which replaces `APP_KEY`.

## 5. Not in the repository: carry these by hand

Names only. Values are never written in the repository or in a chat.

| What | Why it matters |
|---|---|
| `.env` | Every local setting. **Keep its `APP_KEY`:** encrypted data (store gateway credentials, two-factor secrets, database server passwords) can only be read with the key that wrote it. Never run `php artisan key:generate` on a copied `.env` |
| In `.env`: `DB_PASSWORD`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `REDIS_PASSWORD`, `SENTRY_LARAVEL_DSN`, `OPEN_EXCHANGE_RATES_APP_ID`, the `AWS_` keys and the store export bucket settings | Secrets and service settings |
| In `.env`, not in `.env.example`: `PAYSTACK_MANUAL_TEST_SECRET_KEY` | The Paystack **test** secret key for the manual Paystack check. It goes in the local `.env` only |
| In `.env`, not in `.env.example`: `PLATFORM_REGIONS`, `PLATFORM_ADMIN_PASSWORD_RESET_URL`, the three `STORE_EXPORT_*_BUCKET_REGION` settings, `TRANSLATION_DEFAULT_DRIVER`, `TRANSLATION_SOURCE_LANGUAGE`, `OLLAMA_MODEL`, `OLLAMA_API_URL`, `OPENAI_API_KEY`, `DEEPSEEK_API_KEY`, `GOOGLE_API_KEY`, `DEEPL_API_KEY` | Local-only settings; copy them with the file |
| The PostgreSQL `postgres` password | Needed to create the databases; it goes in `DB_PASSWORD` |
| The SSH key for GitHub | Make a new one on the new PC and add it to GitHub, rather than copying the old one |
| Local databases (`sellora_central` and any `sellora_tenant_` store databases) | Only if the local test data matters. Otherwise start empty: migrate and seed, then register stores again |
| `.vscode/` settings | Git-ignored; set them again (section 4) |
| **The frontend's uncommitted files:** 65 untracked files in `sellora-frontend` (under `apps/dashboard/hooks`, `apps/dashboard/lib` and `packages/ui/src`) are on the old laptop only | Commit and push them, or copy the folder, before the laptop goes |
| Claude Code's own notes about this machine | Moved into the documentation home's setup steps; nothing else is needed |

## 6. How to resume

Paste this as the first prompt in a fresh Claude Code session opened in the
new clone:

```text
This is the sellora-api repository, continued on a new PC. You have no memory of earlier sessions.
1. Read CLAUDE.md in full, then docs/HANDOFF.md and docs/README.md.
2. Run: git fetch --all --prune, git status, git log --oneline -5, and git branch -a. Compare with HANDOFF.md section 1 and tell me anything that differs.
3. Check the environment with the steps in HANDOFF.md section 7. Don't change any file.
4. Report what you found in a few lines and stop. Don't start the next item until I say which one.
Work exactly to CLAUDE.md. Use scripts/probe.ps1 for probes, scripts/run-suite.ps1 for the Landlord suite before a push, and scripts/wait-for-ci.ps1 after a push.
```

Working agreements from the owner that CLAUDE.md doesn't state yet:

- Every rule a change adds gets a probe (a deliberate break of that rule)
  that its tests catch; the report lists each probe and whether it was
  caught.
- A commit is never chained onto a test or probe command in the same shell
  line.
- Probes and tests never run against the database at the same time, and no
  file is edited while a suite runs.
- No new packages without asking.
- Larastan errors are fixed, never silenced: no ignore comments, no inline
  `@var`, no casts only to quiet it.
- A suite that passed is not run again unless something it covers changed.
- Reports are short: commits with CI, probes, and anything new or open,
  always including the audit ignore while it is open.

## 7. How to check nothing was lost

**The clone matches GitHub:**

```powershell
git fetch --all --prune
git status                      # "nothing to commit, working tree clean"
git rev-parse main origin/main  # the same hash twice
git log --oneline -1 origin/main
git branch -r                   # every branch in section 1, except the ci-proof ones
git log --oneline main..origin/handover-scripts
```

On the old laptop, before it goes, look for anything not on GitHub:

```powershell
git status --short                            # nothing
git log --branches --not --remotes --oneline  # only the three ci-proof commits
git stash list                                # nothing
```

**The PC matches CI:**

```powershell
php -v                 # PHP 8.4
php -m                 # includes the extensions in the setup steps
composer ci            # Pint, the hook check, Larastan, audit, Scramble, Pest: all pass
.\scripts\db-status.ps1
```

`composer ci` passing is the proof the PC is ready. A run that fails only on
database timeouts proves nothing (for example after the PC slept): run it
again on its own.
