<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Messaging settings (Step 20a): who the store's emails come from and where
 * replies go, and whether staff are emailed about every new order. One row,
 * created with every store (this migration gives existing stores theirs);
 * reads never fill it in (CLAUDE.md section 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_settings', static function (Blueprint $table): void {
            $table->comment('Who the store\'s emails come from, where replies go, and which staff alerts are emailed. One row, created with the store and never missing.');
            $table->smallInteger('id')->primary();
            $table->string('sender_name', 80)->nullable()->comment('The name the store\'s emails come from; null for the store\'s own name.');
            $table->string('reply_to_email', 254)->nullable()->comment('Where replies to the store\'s emails go; null for the store\'s contact email.');
            $table->boolean('has_new_order_alert_emails')->default(true)->comment('Whether staff who fulfil orders are also emailed about each new order; it is always in their alert list.');
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            alter table messaging_settings
                add constraint messaging_settings_single_row check (id = 1),
                add constraint messaging_settings_sender_name_not_blank check (sender_name is null or length(btrim(sender_name)) > 0)
            SQL);

        $now = CarbonImmutable::now();

        DB::table('messaging_settings')->insert([
            'id' => 1,
            'has_new_order_alert_emails' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_settings');
    }
};
