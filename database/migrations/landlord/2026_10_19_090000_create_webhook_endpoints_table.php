<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Where third-party webhooks find their store (Landlord\Integrations). A
 * store's connection to a provider that signs webhooks with the store's own
 * key (such as Paystack) gets a random endpoint ID for its webhook URL, so the
 * URL never carries the store ID. An endpoint left behind by a connection that
 * never finished saving leads nowhere and is pruned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', static function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 64)->unique()->comment('Random; the only part of the webhook URL that names it');
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('integration', 64)->comment('Such as "paystack"');
            $table->timestampTz('created_at');
            $table->index(['tenant_id', 'integration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
