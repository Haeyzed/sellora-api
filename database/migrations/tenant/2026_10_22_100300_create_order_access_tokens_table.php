<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * How a guest gets back to their order (Step 16): a token given once when
 * the order is placed, sent in a header, never a URL. Only its hash is kept.
 * It lets them see the order, pay again, download its invoice and credit
 * notes, and cancel it while unpaid, for 90 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_access_tokens', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->char('token_hash', 64)->unique()->comment('SHA-256 of the token, in hex');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_access_tokens');
    }
};
