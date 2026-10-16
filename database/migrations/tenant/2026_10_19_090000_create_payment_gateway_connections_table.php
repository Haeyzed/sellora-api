<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The store's own accounts with payment gateways (Step 15.1). Credentials
 * are encrypted and never leave the store's database: not in the API, audits
 * or exports. A disconnected gateway keeps its row (payments name it) but no
 * credentials. Test or live is worked out from the keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_connections', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('gateway', 64)->unique()->comment('The gateway integration key, such as "paystack"');
            $table->string('status', 16);
            $table->boolean('enabled')->default(true)->comment('Offered for new payments; turning it off never stops money already in motion');
            $table->smallInteger('priority')->default(0)->comment('Lower is offered first');
            $table->string('mode', 8)->nullable();
            $table->text('credentials')->nullable()->comment('Encrypted; null once disconnected');
            $table->string('credentials_hint', 32)->nullable()->comment('Such as the last four characters of the public key');
            $table->string('account_id', 128)->nullable()->comment('The gateway account the keys belong to, when it can tell');
            $table->string('endpoint_id', 64)->nullable()->unique()->comment('Its webhook endpoint in Landlord\Integrations');
            $table->timestampTz('connected_at')->nullable();
            $table->timestampTz('checked_at')->nullable();
            $table->timestampTz('disconnected_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            alter table payment_gateway_connections
                add constraint payment_gateway_connections_status_check check (status in ('connected', 'disconnected')),
                add constraint payment_gateway_connections_mode_check check (mode is null or mode in ('test', 'live')),
                add constraint payment_gateway_connections_connected_has_credentials check (
                    (status = 'connected') = (credentials is not null and mode is not null and endpoint_id is not null and connected_at is not null)
                ),
                add constraint payment_gateway_connections_disconnected_at check ((status = 'disconnected') = (disconnected_at is not null))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_connections');
    }
};
