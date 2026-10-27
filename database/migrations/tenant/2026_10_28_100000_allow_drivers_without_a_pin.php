<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Drivers choose their own PIN (Step 17): staff create the account without
 * one and issue a one-time setup code, and issuing a new code clears the old
 * PIN. An erased driver keeps the row, with name, phone and PIN cleared, so
 * their past deliveries still point at someone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', static function (Blueprint $table): void {
            $table->timestampTz('erased_at')->nullable()->after('last_signed_in_at')->comment('When the driver\'s personal data was erased; the row stays for their past deliveries.');
        });

        DB::statement('alter table drivers alter column name drop not null');
        DB::statement('alter table drivers alter column phone drop not null');
        DB::statement('alter table drivers alter column pin drop not null');
        DB::statement(<<<'SQL'
            alter table drivers
                add constraint drivers_erased_have_no_details check (
                    (erased_at is null and name is not null and phone is not null)
                    or (erased_at is not null and name is null and phone is null and pin is null and is_active = false)
                )
            SQL);
        DB::statement("comment on column drivers.pin is 'Hashed like a password. Null until the driver chooses one with a setup code, and again after staff issue a new code.'");
        DB::statement("comment on column drivers.name is 'Null once erased.'");
        DB::statement("comment on column drivers.phone is 'E.164, for example +2348012345678; the driver signs in with it. Null once erased.'");
    }

    public function down(): void
    {
        DB::statement('alter table drivers drop constraint drivers_erased_have_no_details');
        DB::statement('alter table drivers alter column pin set not null');
        DB::statement('alter table drivers alter column phone set not null');
        DB::statement('alter table drivers alter column name set not null');
        DB::statement("comment on column drivers.pin is 'hashed like a password'");
        DB::statement('comment on column drivers.name is null');
        DB::statement("comment on column drivers.phone is 'E.164, for example +2348012345678'");

        Schema::table('drivers', static function (Blueprint $table): void {
            $table->dropColumn('erased_at');
        });
    }
};
