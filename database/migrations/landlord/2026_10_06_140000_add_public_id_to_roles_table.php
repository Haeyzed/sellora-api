<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', static function (Blueprint $table): void {
            $table->ulid('public_id')->nullable()->after('id');
        });

        foreach (DB::table('roles')->whereNull('public_id')->pluck('id') as $roleId) {
            DB::table('roles')->where('id', $roleId)->update(['public_id' => (string) Str::ulid()]);
        }

        Schema::table('roles', static function (Blueprint $table): void {
            $table->ulid('public_id')->nullable(false)->change();
            $table->unique('public_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', static function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
