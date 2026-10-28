<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * What staff kept back from a refund of lines (Step 18): when returning
 * them breaks the condition a discount depended on, such as returning the
 * paid items of a "buy 2, get 1 free" deal and keeping the free one. It is
 * taken off the lines given back, so their credit note credits only what
 * went back, and always comes with the reason. Earlier refunds kept nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', static function (Blueprint $table): void {
            $table->bigInteger('withheld_amount')->default(0)->comment('What staff kept back from the lines given back, in minor units; their amounts are already less this');
            $table->string('withheld_reason', 500)->nullable()->comment('Why it was kept back; set exactly when something was');
        });

        DB::statement(<<<'SQL'
            alter table refunds
                add constraint refunds_withheld_with_reason check (
                    (withheld_amount = 0 and withheld_reason is null)
                    or (withheld_amount > 0 and withheld_reason is not null)
                )
            SQL);
    }

    public function down(): void
    {
        Schema::table('refunds', static function (Blueprint $table): void {
            $table->dropColumn(['withheld_amount', 'withheld_reason']);
        });
    }
};
