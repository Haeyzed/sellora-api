<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * What staff may still change on an order after it is placed (Step 14d).
 *
 * Contact corrections are new versions, never changes to the details the
 * order was placed with: like addresses, rows are only added, and only
 * erasure may blank or delete them. Notes are staff's own (added and
 * deleted, never changed); tags are labels for finding orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_contact_corrections', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('email', 254)->nullable()->comment('In lower case; null once anonymised');
            $table->string('name', 200)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('created_by_type', 64);
            $table->string('created_by_id', 64);
            $table->timestampTz('created_at');
            $table->index(['order_id', 'id']);
        });
        DB::statement('alter table order_contact_corrections add constraint order_contact_corrections_email_lower_case check (email = lower(email))');
        DB::unprepared('create trigger order_contact_corrections_change_only_in_erasure before update or delete on order_contact_corrections for each row execute function order_personal_records_change_only_in_erasure();');
        DB::unprepared('create trigger order_contact_corrections_never_emptied before truncate on order_contact_corrections for each statement execute function order_records_never_change();');

        Schema::create('order_notes', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('author_staff_id')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->text('body');
            $table->timestampTz('created_at');
            $table->index(['order_id', 'id']);
        });

        Schema::create('order_tags', static function (Blueprint $table): void {
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('tag', 40);
            $table->primary(['order_id', 'tag']);
            $table->index('tag');
        });
        DB::statement("alter table order_tags add constraint order_tags_lower_case check (tag = lower(tag) and tag <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tags');
        Schema::dropIfExists('order_notes');
        Schema::dropIfExists('order_contact_corrections');
    }
};
