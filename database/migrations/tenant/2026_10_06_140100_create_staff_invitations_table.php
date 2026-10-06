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
        Schema::create('staff_invitations', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('email', 254)->index();
            $table->string('name', 120)->nullable();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by_id')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('staff_invitation_roles', static function (Blueprint $table): void {
            $table->foreignId('staff_invitation_id')->constrained('staff_invitations')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['staff_invitation_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_invitation_roles');
        Schema::dropIfExists('staff_invitations');
    }
};
