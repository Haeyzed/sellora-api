# Handover

**Date:** 5 November 2026.
**Describes:** `main` at `53515da`, and the branch `encrypt-queued-messages`
started from it.

This is for continuing the work on a new PC: for the project owner, and for
a fresh Claude Code session that remembers nothing of the sessions before
it. The rules for working in this repository are in
[CLAUDE.md](../CLAUDE.md) and are not repeated here: read it first, all of
it. The product is described in the [documentation home](README.md).

A handover is a snapshot. Anything here can go out of date; when it
disagrees with `git log`, GitHub or CLAUDE.md, they win.

## 1. Where things stand

**`main`** is at `53515da` ("Document how errors are kept free of personal
data in production"). CI passed on it: run 41862033454, all three jobs (Pest
on PostgreSQL 18 and Redis; Pint, Larastan and composer audit; Scramble).

**What was finished last:** `scrub-error-reports` (the error scrubbing that
was item 2a), merged into `main` by fast-forward:

| Commit | What |
|---|---|
| `b7172ee` | Database errors are scrubbed before they are reported or shown |
| `9b8b8c7` | ...and in what the console prints |
| `3b0b080` | Reported errors go to Sentry, scrubbed, and only with a DSN |
| `f7fd529` | Failed jobs' errors are scrubbed; failed jobs are purged after 30 days |
| `53515da` | [Documentation home](README.md): errors and personal data in production |

Before that: `handover-scripts` (`c3ad6db` to `cb3e372`), which added the
helper scripts and this handover.

**Branches:**

| Branch | State |
|---|---|
| `encrypt-queued-messages` | **In progress:** item 2a below. Not merged |
| `scrub-error-reports`, `handover-scripts` | Merged; kept on GitHub |
| `step-10-customers` to `step-18-returns`, `step-10b-social-sign-in`, `step-15b-invoices`, `step-16b-paystack` | Merged; kept on GitHub |
| `docs-schema-comments`, `fix-token-links`, `fix-payment-holds` | Merged; kept on GitHub |
| `ci-proof-docs`, `ci-proof-pint`, `ci-proof-stan` | Gone, as intended. They existed only on the old laptop (each broke one CI check on purpose, to prove CI catches it) and were never meant to be kept |
| Dependabot: `laravel/telescope` 5.26.0, `spatie/laravel-activitylog` 5.1.3, `spatie/laravel-medialibrary` 11.23.10, `spatie/laravel-permission` 8.3.2, `spatie/laravel-translatable` 6.15.0 | Open, not merged. Each needs its CI run checked and the owner's approval |
| Dependabot: `shivammathur/setup-php` 2.40.0 | Merged |

## 2. What comes next, in order

Each item is its own branch, starts only when the owner says so, and ends
with a stop for review (CLAUDE.md section 17).

### a. Queued emails encrypted (`encrypt-queued-messages`)

Every queued notification implements Laravel's ShouldBeEncrypted, so the links with
tokens, codes, emails and names they carry are unreadable in the queue and
in `failed_jobs`; an architecture test requires it of every queued
notification, mailable and listener. The branch also proves the Open
Exchange Rates app ID never leaks from a failed refresh, and lets
`scripts/run-suite.ps1` pick a safe number of workers. What deploying it
means for jobs already stored is in [deployment](deployment.md).

### b. Permission labels

The next small branch, from the section 10 rule that every permission gets
a translated label, description and group. Not started.

### c. Step 18b: exchanges and the refund-destination extension point

From CLAUDE.md sections 17 and 18. An exchange is the return plus a linked
replacement order made by a staff action that reuses checkout's pricing,
stock and tax steps; the price difference is settled by a payment or a
refund. Where a refund goes becomes an extension point: the original
payment is core's default and only destination, and modules add others
through a core contract. Returns today is described in
[Returns](tenant/returns.md).

### d. Steps 19, 20 and 21

Storefront, then Messaging (email first, see [Messaging](tenant/messaging.md)),
then Data tools, as CLAUDE.md section 17 lists them.

### e. The frontend, after core

The frontends come after the core API is done. The frontend repository is
`Haeyzed/sellora-frontend`.

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
| **A credit note edited outside the app** can leave tax behind that the final credit can't take back; the daily invoice check reports it | Only here and in earlier review reports |
| **Other errors that can quote personal data:** errors are scrubbed only when they are, or were caused by, a database error. Others pass with their message, for example a mail error quoting the recipient's address, which also lands in `failed_jobs.exception` (encryption covers the job's data, not its error). Each needs its own handling in `ErrorScrubber`, one kind of error at a time | Only here (found in `scrub-error-reports`) |
| **Telescope stores exception messages** in full, including a database error's SQL and values. It runs only locally (never in production or staging with real data), so it was left out of `scrub-error-reports` | Only here |
| **Plain-text queued emails** written before `encrypt-queued-messages` is deployed stay readable in `failed_jobs` until the 30-day purge; they are not re-encrypted | [Deployment](deployment.md) |
| **The pre-commit hook** was tried by hand only on Windows (PowerShell and Git Bash); CI now runs the hook check (`composer ci:hook`) on Linux on every push | [CI workflow](../.github/workflows/ci.yml) |

## 4. New PC setup (Windows PowerShell)

The steps, with the machine facts behind them, are in the documentation
home: [local setup on Windows](README.md#local-setup-on-windows). In short,
and in this order:

1. Git, and an SSH key added to GitHub (the remote is
   `git@github.com:Haeyzed/sellora-api.git`).
2. Laravel Herd with PHP 8.4, which brings Composer. Check the PHP
   extensions.
3. PostgreSQL 18, on the port it runs on (default 5432) in `DB_PORT`.
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

The owner's working agreements are in [CLAUDE.md](../CLAUDE.md) section 15.

## 7. How to check nothing was lost

**The clone matches GitHub:**

```powershell
git fetch --all --prune
git status                      # "nothing to commit, working tree clean"
git rev-parse main origin/main  # the same hash twice
git log --oneline -1 origin/main
git branch -r                   # every branch in section 1 that is kept on GitHub
git log --branches --not --remotes --oneline  # nothing: every local commit is on GitHub
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
