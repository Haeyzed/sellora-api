<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('platform_admin_invitations', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('email', 254)->index();
            $table->string('name', 120)->nullable();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('platform_admin_invitation_roles', static function (Blueprint $table): void {
            $table->foreignId('platform_admin_invitation_id')->constrained('platform_admin_invitations')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['platform_admin_invitation_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_admin_invitation_roles');
        Schema::dropIfExists('platform_admin_invitations');
    }
};
