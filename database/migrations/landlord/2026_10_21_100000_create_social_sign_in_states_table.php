<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Customer social sign-in (Step 10b): what the central relay page needs to
 * send a provider's answer back to the store that asked, and nothing else.
 *
 * Every provider redirects to one relay address on the central domain. The
 * relay finds the store by the hash of the one-time state, and redirects only
 * to that store's own domain. Everything about the customer (whether they
 * sign in or link, the PKCE verifier, the nonce, the account being linked,
 * anything the provider sends) stays in the store's own database, keyed by
 * the same hash; none of it is ever written here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_sign_in_states', static function (Blueprint $table): void {
            $table->id();
            $table->char('state_hash', 64)->unique()->comment('SHA-256 of the one-time state; the state itself is never stored');
            $table->string('tenant_id');
            $table->string('provider', 16);
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('used_at')->nullable()->comment('When the relay sent it on; it never does twice');
            $table->timestampTz('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
        });

        DB::statement("alter table social_sign_in_states add constraint social_sign_in_states_provider_check check (provider in ('google', 'apple'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('social_sign_in_states');
    }
};
