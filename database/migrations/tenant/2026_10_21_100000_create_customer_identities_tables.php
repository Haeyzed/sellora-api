<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Customer social sign-in (Step 10b), in the store's own database.
 *
 * - customer_identities: a customer's sign-in with a provider, found only by
 *   the provider's own user ID, unique per provider in this store; at most
 *   one per provider per customer. Provider tokens are never kept.
 * - social_sign_in_attempts: one per sign-in started, keyed by the hash of
 *   its one-time state (the central relay keeps only the routing): whether
 *   it signs in or links, the account being linked, the PKCE verifier
 *   (encrypted), the nonce and where the storefront returns. Used once.
 * - social_sign_in_tickets: what a finished sign-in still needs from the
 *   customer before it can go on: proof they own an existing account with
 *   the provider's email, or an email when the provider gave none. Only the
 *   ticket's hash is stored; it holds what the provider sent, and expires.
 *
 * Accounts that only ever used social sign-in have no password, so the
 * customers check now allows that until erasure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_identities', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('provider_user_id', 255)->comment('The provider\'s own ID for the person; never their email');
            $table->string('email', 254)->nullable()->comment('As the provider sent it, which may differ from the account\'s');
            $table->boolean('email_verified')->comment('Whether the provider said the email is verified');
            $table->boolean('is_private_relay')->default(false)->comment('An Apple private relay address');
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('created_at');

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['customer_id', 'provider']);
        });

        DB::statement("alter table customer_identities add constraint customer_identities_provider_check check (provider in ('google', 'apple'))");

        Schema::create('social_sign_in_attempts', static function (Blueprint $table): void {
            $table->id();
            $table->char('state_hash', 64)->unique();
            $table->string('provider', 16);
            $table->string('intent', 16)->comment('sign_in, or link to a signed-in customer');
            $table->foreignId('customer_id')->nullable()->comment('The customer linking a provider; null for a sign-in')->constrained('customers')->cascadeOnDelete();
            $table->text('code_verifier')->comment('PKCE verifier, encrypted');
            $table->string('nonce', 64);
            $table->string('return_path', 512)->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('created_at');
        });

        DB::statement("alter table social_sign_in_attempts add constraint social_sign_in_attempts_provider_check check (provider in ('google', 'apple'))");
        DB::statement("alter table social_sign_in_attempts add constraint social_sign_in_attempts_intent_check check ((intent = 'sign_in' and customer_id is null) or (intent = 'link' and customer_id is not null))");

        Schema::create('social_sign_in_tickets', static function (Blueprint $table): void {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->string('kind', 24)->comment('link_account: prove ownership of the existing account; email_required: the provider gave no email');
            $table->string('provider', 16);
            $table->string('provider_user_id', 255);
            $table->string('email', 254)->nullable();
            $table->boolean('email_verified');
            $table->boolean('is_private_relay')->default(false);
            $table->string('name', 120)->nullable();
            $table->foreignId('customer_id')->nullable()->comment('The existing account to link to')->constrained('customers')->cascadeOnDelete();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('created_at');
        });

        DB::statement("alter table social_sign_in_tickets add constraint social_sign_in_tickets_provider_check check (provider in ('google', 'apple'))");
        DB::statement("alter table social_sign_in_tickets add constraint social_sign_in_tickets_kind_check check ((kind = 'link_account' and customer_id is not null and email is not null) or (kind = 'email_required' and customer_id is null))");

        // A social-only account has no password until the customer sets one.
        DB::statement('alter table customers drop constraint customers_erasure_check');
        DB::statement(<<<'SQL'
            alter table customers add constraint customers_erasure_check check (
                (erased_at is null and name is not null and email is not null)
                or (
                    erased_at is not null
                    and name is null and email is null and password is null and phone is null and locale is null
                    and tax_id is null and tax_id_country is null and is_tax_exempt = false and is_active = false
                    and search_text = '' and default_shipping_address_id is null and default_billing_address_id is null
                )
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('alter table customers drop constraint customers_erasure_check');
        DB::statement(<<<'SQL'
            alter table customers add constraint customers_erasure_check check (
                (erased_at is null and name is not null and email is not null and password is not null)
                or (
                    erased_at is not null
                    and name is null and email is null and password is null and phone is null and locale is null
                    and tax_id is null and tax_id_country is null and is_tax_exempt = false and is_active = false
                    and search_text = '' and default_shipping_address_id is null and default_billing_address_id is null
                )
            )
            SQL);

        Schema::dropIfExists('social_sign_in_tickets');
        Schema::dropIfExists('social_sign_in_attempts');
        Schema::dropIfExists('customer_identities');
    }
};
