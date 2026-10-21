<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Proving ownership of an existing account by a link emailed to it (Step
 * 10b.4), allowed only when the provider is trusted and says the email is
 * verified. The link carries its own one-time token, so it works when opened
 * on another device; only its hash is stored, on the ticket it confirms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_sign_in_tickets', static function (Blueprint $table): void {
            $table->char('email_link_token_hash', 64)->nullable()->unique()->comment('SHA-256 of the emailed link\'s token, when one was sent');
            $table->timestampTz('email_link_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('social_sign_in_tickets', static function (Blueprint $table): void {
            $table->dropColumn(['email_link_token_hash', 'email_link_sent_at']);
        });
    }
};
