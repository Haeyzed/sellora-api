<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A credit note has no refund behind it only when part or all of a sale was
 * undone without one: a hold released, or a method giving back what it took.
 * The kind says which it is, and the database holds the two together, so a
 * credit note can't lose its refund by accident. Any already issued without a
 * refund are marked as such. Each such credit note now refers to the hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_notes', static function (Blueprint $table): void {
            $table->string('kind', 16)->default('refund')->after('refund_reference')->comment('"refund" for a refund that reduced the sale; "sale_undone" for a held amount that stopped counting toward it, with no refund behind it');
            $table->string('reference', 64)->comment('The public ID of the refund part or the payment hold it credits: one credit note each')->change();
            $table->string('refund_reference', 64)->nullable()->comment('The public ID of the refund; null only for kind "sale_undone"')->change();
        });

        DB::unprepared(<<<'SQL'
            alter table credit_notes disable trigger credit_notes_are_tax_documents;
            update credit_notes set kind = 'sale_undone' where refund_reference is null;
            alter table credit_notes enable trigger credit_notes_are_tax_documents;
            SQL);

        DB::statement(<<<'SQL'
            alter table credit_notes
                add constraint credit_notes_refund_reference_matches_kind check (
                    (kind = 'refund' and refund_reference is not null)
                    or (kind = 'sale_undone' and refund_reference is null)
                )
            SQL);
    }

    public function down(): void
    {
        DB::statement('alter table credit_notes drop constraint credit_notes_refund_reference_matches_kind');

        Schema::table('credit_notes', static function (Blueprint $table): void {
            $table->dropColumn('kind');
            $table->string('reference', 64)->comment('The public ID of the refund part it credits, or of the order for a sale undone without a refund: one credit note each')->change();
            $table->string('refund_reference', 64)->nullable()->comment('The public ID of the refund; null for a sale undone without a refund')->change();
        });
    }
};
