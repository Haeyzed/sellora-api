<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Step 16b.1. A gateway's dashboard takes a webhook URL per mode (Paystack's
 * has a test one and a live one), so each connection gets a webhook endpoint
 * per mode, both shown to the merchant on connecting. The store's own record
 * says which endpoint serves which mode.
 *
 * Keys are no longer forgotten while money they took is still in motion
 * (section 3.3): a gateway disconnected then keeps its keys, and keys
 * replaced then are kept as retired ones, until every payment and refund of
 * the connection has settled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_connections', static function (Blueprint $table): void {
            $table->string('test_endpoint_id', 64)->nullable()->unique()->comment('Its webhook endpoint for test mode in Landlord\Integrations');
            $table->string('live_endpoint_id', 64)->nullable()->unique()->comment('Its webhook endpoint for live mode in Landlord\Integrations');
            $table->text('retired_credentials')->nullable()->comment('Encrypted; earlier keys, kept while money they took is still in motion');
        });

        DB::statement("update payment_gateway_connections set test_endpoint_id = endpoint_id where mode = 'test'");
        DB::statement("update payment_gateway_connections set live_endpoint_id = endpoint_id where mode = 'live'");

        DB::statement(<<<'SQL'
            alter table payment_gateway_connections
                drop constraint payment_gateway_connections_connected_has_credentials,
                drop column endpoint_id,
                add constraint payment_gateway_connections_connected_has_credentials check (
                    status <> 'connected' or (
                        credentials is not null
                        and mode is not null
                        and case mode when 'test' then test_endpoint_id is not null else live_endpoint_id is not null end
                    )
                ),
                add constraint payment_gateway_connections_connected_at check ((status = 'connected') = (connected_at is not null)),
                add constraint payment_gateway_connections_keys_lead_webhooks check (
                    credentials is not null or (test_endpoint_id is null and live_endpoint_id is null and retired_credentials is null)
                )
            SQL);
    }

    public function down(): void
    {
        Schema::table('payment_gateway_connections', static function (Blueprint $table): void {
            $table->string('endpoint_id', 64)->nullable()->unique()->comment('Its webhook endpoint in Landlord\Integrations');
        });

        // The old shape keeps no keys once disconnected.
        DB::statement(<<<'SQL'
            update payment_gateway_connections
            set endpoint_id = case mode when 'test' then test_endpoint_id when 'live' then live_endpoint_id end
            where status = 'connected'
            SQL);
        DB::statement("update payment_gateway_connections set credentials = null, credentials_hint = null where status = 'disconnected'");

        DB::statement(<<<'SQL'
            alter table payment_gateway_connections
                drop constraint payment_gateway_connections_keys_lead_webhooks,
                drop constraint payment_gateway_connections_connected_at,
                drop constraint payment_gateway_connections_connected_has_credentials,
                drop column test_endpoint_id,
                drop column live_endpoint_id,
                drop column retired_credentials,
                add constraint payment_gateway_connections_connected_has_credentials check (
                    (status = 'connected') = (credentials is not null and mode is not null and endpoint_id is not null and connected_at is not null)
                )
            SQL);
    }
};
