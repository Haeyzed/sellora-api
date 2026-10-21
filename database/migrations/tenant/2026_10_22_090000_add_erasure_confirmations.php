<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Asking for erasure is never harder than signing in (section 3.3): a
 * customer without a password confirms it with a one-time token, emailed to
 * their account address or given after a fresh provider sign-in. Only the
 * token's hash is kept, with their other one-time links; a provider sign-in
 * started to confirm it is for the signed-in customer, like linking one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table customer_email_tokens drop constraint customer_email_tokens_purpose_check');
        DB::statement("alter table customer_email_tokens add constraint customer_email_tokens_purpose_check check (purpose in ('verification', 'email_change', 'erasure'))");

        DB::statement('alter table social_sign_in_attempts drop constraint social_sign_in_attempts_intent_check');
        DB::statement("alter table social_sign_in_attempts add constraint social_sign_in_attempts_intent_check check ((intent = 'sign_in' and customer_id is null) or (intent in ('link', 'reauthenticate') and customer_id is not null))");
    }

    public function down(): void
    {
        DB::table('customer_email_tokens')->where('purpose', 'erasure')->delete();
        DB::table('social_sign_in_attempts')->where('intent', 'reauthenticate')->delete();

        DB::statement('alter table customer_email_tokens drop constraint customer_email_tokens_purpose_check');
        DB::statement("alter table customer_email_tokens add constraint customer_email_tokens_purpose_check check (purpose in ('verification', 'email_change'))");

        DB::statement('alter table social_sign_in_attempts drop constraint social_sign_in_attempts_intent_check');
        DB::statement("alter table social_sign_in_attempts add constraint social_sign_in_attempts_intent_check check ((intent = 'sign_in' and customer_id is null) or (intent = 'link' and customer_id is not null))");
    }
};
