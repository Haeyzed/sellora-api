<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A sale can be undone without a refund: an order paid in full by a payment
 * method that only held the money (a gift card whose commit failed) and was
 * then cancelled, once what was held is released. Nothing went back, so no
 * refund exists, but its invoice still needs crediting in full. Such a
 * credit note has the order's public ID as its reference (one per order) and
 * no refund.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_notes', static function (Blueprint $table): void {
            $table->string('reference', 64)->comment('The public ID of the refund part it credits, or of the order for a sale undone without a refund: one credit note each')->change();
            $table->string('refund_reference', 64)->nullable()->comment('The public ID of the refund; null for a sale undone without a refund')->change();
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', static function (Blueprint $table): void {
            $table->string('reference', 64)->comment('The public ID of the refund part it credits: one credit note per part')->change();
            $table->string('refund_reference', 64)->nullable(false)->comment('The public ID of the refund')->change();
        });
    }
};
