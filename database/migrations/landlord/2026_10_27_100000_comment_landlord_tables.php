<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Describes every central table, and the columns whose meaning isn't clear
 * from their name, in the database itself.
 *
 * Only comments change. Table comments go through Laravel; a column comment
 * can only be set through ->change(), which restates the column's type and
 * drops any modifier not repeated, so columns use PostgreSQL's COMMENT ON,
 * built from the constants below only. Columns that already had a comment
 * keep it and are not listed here.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{0: string, 1: array<string, string>}>
     */
    private const array TABLES = [
        'activity_log' => ['Platform activity log (spatie/laravel-activitylog): what platform admins and the system did. A store account causing an entry is recorded as account type and public ID in properties, not as causer.', []],
        'audits' => ['Before-and-after changes to audited central records (owen-it/laravel-auditing).', [
            'user_type' => 'Morph name of the account that made the change; null for the system.',
        ]],
        'cities' => ['Reference data: cities (nnjeim/world). Read-only for the application.', []],
        'countries' => ['Reference data: countries (nnjeim/world). Read-only for the application.', [
            'iso2' => 'ISO 3166-1 alpha-2 code, the one the application uses.',
        ]],
        'currencies' => ['Reference data: currencies (nnjeim/world). Read-only for the application.', [
            'code' => 'ISO 4217 code.',
            'precision' => 'Decimal places shown, from the reference data.',
        ]],
        'database_servers' => ['Pool of PostgreSQL servers new store databases are placed on, by hosting region.', [
            'public_id' => 'Public ID (ULID) used in the platform API.',
            'region' => 'Hosting region key from config/platform.php.',
            'password' => 'Encrypted with the application key.',
            'capacity' => 'Most store databases this server takes.',
            'tenant_count' => 'How many store databases it holds.',
            'accepting_new_tenants' => 'False stops new stores being placed here; existing stores stay.',
        ]],
        'domains' => ['Domains and subdomains that lead to a store (stancl/tenancy). Looked up on every store request.', [
            'domain' => 'Full host name, lower case; unique.',
        ]],
        'exchange_rates' => ['Market exchange rates fetched by the platform: units of each currency one US dollar bought. Append-only (a trigger refuses updates); old rows are removed by the retention policy.', [
            'currency' => 'ISO 4217 code.',
            'units_per_usd' => 'Units of this currency per 1 USD, as a decimal (not minor units).',
            'source' => 'Provider the rate came from, such as "openexchangerates".',
        ]],
        'failed_jobs' => ['Queued jobs that failed every attempt (Laravel).', []],
        'feature_grants' => ['A module or integration given to one store outside its plan by a platform admin, until it expires or is revoked.', [
            'public_id' => 'Public ID (ULID) used in the platform API.',
            'feature_key' => 'Module or integration key, from config/features.php.',
            'expires_at' => 'When the grant stops; null for no end.',
        ]],
        'idempotency_keys' => ['Idempotency-Key requests on the central domain: a retried request gets the first answer instead of running twice. Removed after expires_at.', [
            'scope' => 'Who sent the key (account or client), so keys never clash between callers.',
            'request_hash' => 'SHA-256 of the request, so a key reused with a different request is refused.',
            'status' => 'One of App\Shared\Idempotency\IdempotencyStatus.',
            'response_body' => 'The first answer, encrypted with the application key.',
        ]],
        'job_batches' => ['Batches of queued jobs (Laravel).', []],
        'jobs' => ['Queued jobs waiting to run (Laravel database queue).', []],
        'languages' => ['Reference data: languages (nnjeim/world). Read-only for the application.', []],
        'legal_acceptances' => ['Who accepted which legal document version, for which store, when and from where: the contract history. Never changed once written.', [
            'store_registration_id' => 'The sign-up it was given in, while that sign-up hasn\'t become a store.',
            'tenant_id' => 'The store it belongs to, set when the sign-up became a store.',
            'reference' => 'What the acceptance was part of, such as an ownership transfer, so it is recorded once.',
        ]],
        'legal_documents' => ['Versions of the platform\'s legal documents. A published version never changes.', [
            'public_id' => 'Public ID (ULID) used in the API.',
            'type' => 'One of App\Landlord\Legal\Enums\LegalDocumentType.',
            'published_at' => 'Null while a draft; set once, after which the version can\'t be edited.',
            'effective_at' => 'When this version takes over from the previous one.',
        ]],
        'migrations' => ['Migrations that have run on this database (Laravel).', []],
        'model_has_permissions' => ['Permissions given directly to a platform admin, besides their roles (spatie/laravel-permission, platform guard).', [
            'model_type' => 'Morph name of the account.',
        ]],
        'model_has_roles' => ['Roles held by platform admins (spatie/laravel-permission, platform guard).', [
            'model_type' => 'Morph name of the account.',
        ]],
        'permissions' => ['Platform permissions, synced from the permission enums in code by permissions:sync.', [
            'name' => 'Permission key, such as "stores.view".',
            'guard_name' => 'Always "platform" in this database.',
        ]],
        'personal_access_tokens' => ['Sign-in tokens for platform admins (Laravel Sanctum).', [
            'tokenable_type' => 'Morph name of the account the token belongs to.',
            'token' => 'SHA-256 hash of the token; the token itself is never stored.',
        ]],
        'plan_features' => ['Modules and integrations included in each plan.', [
            'feature_key' => 'Module or integration key, from config/features.php.',
        ]],
        'plan_limits' => ['Usage limits of each plan, such as how many products or staff.', [
            'limit_key' => 'Limit key, from config/features.php.',
        ]],
        'plans' => ['Plans stores subscribe to.', [
            'public_id' => 'Public ID (ULID) used in the API.',
            'code' => 'Stable key used in config and by the platform team, such as "starter".',
            'is_public' => 'Shown to merchants signing up; a private plan is assigned by platform admins only.',
        ]],
        'platform_admin_invitation_roles' => ['Roles an invited platform admin gets when they accept.', []],
        'platform_admin_invitations' => ['Invitations to join the platform team: a one-time link that expires after 3 days.', [
            'public_id' => 'Public ID (ULID) used in the platform API.',
            'token_hash' => 'SHA-256 hash of the link\'s token; the token itself is never stored.',
            'revoked_at' => 'When it was cancelled or replaced by a resend.',
        ]],
        'platform_admin_password_reset_tokens' => ['Pending password resets for platform admins (Laravel password broker).', [
            'token' => 'Hash of the reset token; the token itself is never stored.',
        ]],
        'platform_admins' => ['Platform admins: the people who run the platform. Two-factor authentication is required.', [
            'public_id' => 'Public ID (ULID) used in the platform API.',
            'email' => 'Lower case; the sign-in name.',
            'password' => 'Password hash.',
            'is_active' => 'False blocks sign-in and rejects existing tokens.',
            'two_factor_secret' => 'Authenticator secret, encrypted with the application key.',
            'two_factor_recovery_codes' => 'Hashes of the unused recovery codes; a used code is removed.',
            'two_factor_last_used_timestep' => 'Time step of the last code accepted, so no code works twice.',
        ]],
        'released_subdomains' => ['Subdomains of purged stores, held back from new stores for a while (from config), so nobody takes over the old store\'s links and traffic.', [
            'held_until' => 'When the subdomain can be registered again.',
        ]],
        'role_has_permissions' => ['Permissions in each platform role (spatie/laravel-permission).', []],
        'roles' => ['Platform roles (spatie/laravel-permission, platform guard). Names are unique per guard ignoring case.', [
            'public_id' => 'Public ID (ULID) used in the platform API.',
            'guard_name' => 'Always "platform" in this database.',
        ]],
        'social_sign_in_states' => ['What the central social sign-in relay needs to send a provider\'s answer back to the right store. Single use and short-lived; nothing about the customer is kept here.', []],
        'states' => ['Reference data: states and regions (nnjeim/world). Read-only for the application.', []],
        'store_exports' => ['Exports of a whole store\'s data, built in the background for its owner or a platform admin, and removed after expires_at.', [
            'public_id' => 'Public ID (ULID) used in the API.',
            'requested_by_type' => 'Account type of who asked: a platform admin or the store\'s owner.',
            'requested_by_id' => 'Public ID of who asked.',
            'status' => 'One of App\Landlord\Tenancy\Enums\StoreExportStatus.',
            'path' => 'Private file path on the disk; never a public URL.',
            'size_bytes' => 'Size of the finished file in bytes.',
        ]],
        'store_registrations' => ['Merchant sign-ups waiting for the emailed code. No store or database exists until the code is entered.', [
            'public_id' => 'Public ID (ULID) used in the registration API.',
            'subdomain' => 'Requested subdomain, lower case; held while the sign-up is pending.',
            'password_hash' => 'Hash of the owner\'s chosen password, for creating their staff account; cleared once the store is set up.',
            'hosting_region' => 'Region key from config/platform.php where the store\'s database is placed.',
            'verification_code_hash' => 'Hash of the emailed verification code; the code itself is never stored.',
            'verification_attempts' => 'Wrong codes entered so far; the sign-up locks after the configured number.',
            'tenant_id' => 'The store created from it, once verified.',
        ]],
        'subscriptions' => ['Each store\'s plan and subscription status. One per store.', [
            'public_id' => 'Public ID (ULID) used in the API.',
            'status' => 'One of App\Landlord\Subscriptions\Enums\SubscriptionStatus.',
            'past_due_at' => 'When a payment failed; the grace period counts from here.',
            'ends_at' => 'When a cancelled subscription\'s paid period ends.',
        ]],
        'telescope_entries' => ['Laravel Telescope debugging entries; Telescope runs only on a developer\'s machine.', []],
        'telescope_entries_tags' => ['Laravel Telescope entry tags; Telescope runs only on a developer\'s machine.', []],
        'telescope_monitoring' => ['Tags Laravel Telescope is watching; Telescope runs only on a developer\'s machine.', []],
        'tenant_features' => ['Each store\'s state for a module or integration it has had: whether its plan removed it or the merchant turned it off. Its data stays readable either way.', [
            'feature_key' => 'Module or integration key, from config/features.php.',
            'removed_from_plan_at' => 'When the store\'s plan stopped including it; the feature is then locked, not deleted.',
            'disabled_by_merchant_at' => 'When the merchant turned it off.',
        ]],
        'tenant_limit_overrides' => ['A usage limit set for one store instead of its plan\'s, by a platform admin.', [
            'limit_key' => 'Limit key, from config/features.php.',
            'is_unlimited' => 'True means no limit at all, set on purpose; limit_value is then null. A missing value never means unlimited.',
            'expires_at' => 'When the plan\'s limit applies again; null for no end.',
        ]],
        'tenants' => ['One record per store: what the platform needs to find and run it. Its products, customers and orders live in the store\'s own database.', [
            'id' => 'Internal store ID; its database is named from it. Never shown publicly.',
            'data' => 'Extra attributes kept by stancl/tenancy.',
            'public_id' => 'Public ID (ULID) used in the platform API.',
            'status' => 'One of App\Landlord\Tenancy\Enums\TenantStatus.',
            'hosting_region' => 'Region key from config/platform.php where the store\'s database lives.',
            'country_code' => 'ISO 3166-1 alpha-2 country the store is in.',
            'currency_code' => 'ISO 4217 base currency the store started with.',
            'closed_by_type' => 'Account type of who closed it: platform_admin or staff_member.',
            'closed_by_id' => 'Public ID of who closed it.',
            'status_before_closing' => 'Status a restore returns it to, so closing never lifts a suspension.',
            'purge_after' => 'When a closed store\'s data is purged; it can be restored until then.',
            'purged_at' => 'When the purge finished: the database, files and owner details are gone and only this record is left.',
        ]],
        'timezones' => ['Reference data: time zones (nnjeim/world). Read-only for the application.', []],
        'webhook_endpoints' => ['Routes a payment webhook URL to the store and integration it is for. Holds no gateway keys.', []],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => [$tableComment, $columns]) {
            Schema::table($table, static fn (Blueprint $blueprint) => $blueprint->comment($tableComment));

            foreach ($columns as $column => $comment) {
                $this->commentOnColumn($table, $column, $comment);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => [, $columns]) {
            // PostgreSQL removes a comment set to an empty string.
            Schema::table($table, static fn (Blueprint $blueprint) => $blueprint->comment(''));

            foreach (array_keys($columns) as $column) {
                $this->commentOnColumn($table, $column, null);
            }
        }
    }

    private function commentOnColumn(string $table, string $column, ?string $comment): void
    {
        $connection = DB::connection($this->getConnection());
        $grammar = $connection->getSchemaGrammar();

        $connection->statement(sprintf(
            'comment on column %s.%s is %s',
            $grammar->wrapTable($table),
            $grammar->wrap($column),
            $comment === null ? 'null' : $connection->getPdo()->quote($comment),
        ));
    }
};
