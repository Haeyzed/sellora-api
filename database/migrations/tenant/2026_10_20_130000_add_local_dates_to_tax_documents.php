<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The dates printed on invoices and credit notes, in the store's timezone
 * when each was issued (Step 15b): copied like everything else, so changing
 * the store's timezone later never changes a document's date. Documents
 * issued before this are dated in the store's timezone as it is now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', static function (Blueprint $table): void {
            $table->date('issued_on')->nullable()->comment('The issue date in the store\'s timezone');
            $table->date('paid_on')->nullable()->comment('The date of the sale (paid in full) in the store\'s timezone');
        });
        Schema::table('credit_notes', static function (Blueprint $table): void {
            $table->date('issued_on')->nullable()->comment('The issue date in the store\'s timezone');
        });

        DB::unprepared(<<<'SQL'
            alter table invoices disable trigger invoices_are_tax_documents;
            alter table credit_notes disable trigger credit_notes_are_tax_documents;

            update invoices set issued_on = (issued_at at time zone settings.timezone)::date, paid_on = (paid_at at time zone settings.timezone)::date
                from (select timezone from store_settings limit 1) as settings;
            update credit_notes set issued_on = (issued_at at time zone settings.timezone)::date
                from (select timezone from store_settings limit 1) as settings;

            alter table invoices enable trigger invoices_are_tax_documents;
            alter table credit_notes enable trigger credit_notes_are_tax_documents;

            alter table invoices alter column issued_on set not null, alter column paid_on set not null;
            alter table credit_notes alter column issued_on set not null;
            SQL);
    }

    public function down(): void
    {
        Schema::table('credit_notes', static function (Blueprint $table): void {
            $table->dropColumn('issued_on');
        });
        Schema::table('invoices', static function (Blueprint $table): void {
            $table->dropColumn(['issued_on', 'paid_on']);
        });
    }
};
