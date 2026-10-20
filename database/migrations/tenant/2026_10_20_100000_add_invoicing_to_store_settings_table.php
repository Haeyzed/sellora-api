<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * What a store's invoices and credit notes say about it, and how they are
 * numbered (Step 15b). Every value is copied onto each document when it is
 * issued, so changing a setting never changes a document already issued.
 *
 * Prefixes hold letters and - / _ only, never digits, so a number can never
 * be read as another series' (INV-2026-000001 against INV-000001), and never
 * start with "TEST", which test orders' documents use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->string('legal_name', 200)->nullable()->comment('The name invoices are issued in; null for the store name');
            $table->string('tax_registration_label', 40)->nullable()->comment('Such as "VAT" or "TIN"; null for the store country\'s usual one');
            $table->string('tax_registration_number', 64)->nullable();
            $table->string('invoice_prefix', 12)->default('INV-');
            $table->string('credit_note_prefix', 12)->default('CN-');
            $table->string('invoice_numbering', 16)->default('yearly')->comment('yearly: numbers start again each year, which is in the number; continuous: never');
            $table->jsonb('invoice_footer')->nullable()->comment('Printed at the foot of invoices and credit notes, by language');
        });

        DB::statement(<<<'SQL'
            alter table store_settings
                add constraint store_settings_invoice_numbering_check check (invoice_numbering in ('yearly', 'continuous')),
                add constraint store_settings_document_prefixes_check check (
                    invoice_prefix ~ '^[A-Za-z/_-]{1,12}$' and upper(invoice_prefix) not like 'TEST%'
                    and credit_note_prefix ~ '^[A-Za-z/_-]{1,12}$' and upper(credit_note_prefix) not like 'TEST%'
                )
            SQL);
    }

    public function down(): void
    {
        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->dropColumn(['legal_name', 'tax_registration_label', 'tax_registration_number', 'invoice_prefix', 'credit_note_prefix', 'invoice_numbering', 'invoice_footer']);
        });
    }
};
