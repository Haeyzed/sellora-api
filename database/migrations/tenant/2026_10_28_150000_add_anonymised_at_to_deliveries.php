<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * When an order is anonymised (its buyer erased, or its retention ended), its
 * deliveries lose who took the goods and the failure notes (Step 17). A
 * delivered delivery still needs who took the goods, unless it has been
 * anonymised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', static function (Blueprint $table): void {
            $table->timestampTz('anonymised_at')->nullable()->comment('When the order was anonymised and the recipient\'s name, failure note and proof were cleared; null until then.');
        });

        DB::statement(<<<'SQL'
            alter table deliveries
                drop constraint deliveries_delivered,
                add constraint deliveries_delivered check ((status = 'delivered') = (delivered_at is not null) and (status <> 'delivered' or recipient_name is not null or anonymised_at is not null))
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            alter table deliveries
                drop constraint deliveries_delivered,
                add constraint deliveries_delivered check ((status = 'delivered') = (delivered_at is not null) and (status <> 'delivered' or recipient_name is not null))
            SQL);

        Schema::table('deliveries', static function (Blueprint $table): void {
            $table->dropColumn('anonymised_at');
        });
    }
};
