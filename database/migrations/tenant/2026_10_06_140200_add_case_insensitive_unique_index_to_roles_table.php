<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Role names are unique per guard ignoring case ("Manager" and "manager" can't both exist), enforced by the database so two requests racing each other can't both create one.
 *
 * A PostgreSQL functional index; the schema builder has no way to express lower(name).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('create unique index roles_lower_name_guard_name_unique on roles (lower(name), guard_name)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists roles_lower_name_guard_name_unique');
    }
};
