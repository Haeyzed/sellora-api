<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

/*
 * Section 10: role names are unique per guard ignoring case, held by the
 * database itself, so it stays true even when two requests race past the
 * validation at the same moment.
 */
uses(LazilyRefreshDatabase::class);

it('refuses a second platform role whose name differs only in case', function (): void {
    Role::query()->create(['name' => 'Support', 'guard_name' => 'platform']);

    expect(static fn () => Role::query()->create(['name' => 'SUPPORT', 'guard_name' => 'platform']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows the same name on a different guard', function (): void {
    Role::query()->create(['name' => 'Support', 'guard_name' => 'platform']);

    expect(Role::query()->create(['name' => 'support', 'guard_name' => 'staff'])->exists)->toBeTrue();
});
