<?php

declare(strict_types=1);

use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Returns\Enums\ReturnReason;
use App\Tenant\Returns\Models\ReturnSettings;
use OwenIt\Auditing\Models\Audit;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18.4: how a store takes returns. The window after delivery (14 days
 * unless the store changes it, 0 for staff-only returns), whether buyers may
 * ask themselves (on unless the store turns it off), and whether a photo is
 * required for damage or the wrong item (off unless the store turns it on).
 * Staff with returns.view see them; only returns.manage changes them.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    $this->store = createStore('first-store', ['country_code' => 'NG']);
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('shows the defaults until staff first change them', function (): void {
    returnsRequest('GET', 'settings', as: $this->owner)
        ->assertOk()
        ->assertExactJson(['data' => ['window_days' => 14, 'customer_requests' => true, 'photos_required' => false]]);

    expect($this->store->run(static fn (): int => ReturnSettings::query()->count()))->toBe(0);
});

it('changes only what is sent, keeping the rest, and records who changed what', function (): void {
    returnsRequest('PATCH', 'settings', ['window_days' => 30], as: $this->owner)
        ->assertOk()
        ->assertExactJson(['data' => ['window_days' => 30, 'customer_requests' => true, 'photos_required' => false]]);
    returnsRequest('PATCH', 'settings', ['customer_requests' => false, 'photos_required' => true], as: $this->owner)
        ->assertOk()
        ->assertExactJson(['data' => ['window_days' => 30, 'customer_requests' => false, 'photos_required' => true]]);
    returnsRequest('PATCH', 'settings', ['window_days' => 0], as: $this->owner)->assertOk()->assertJsonPath('data.window_days', 0);

    $this->store->run(function (): void {
        $audits = Audit::query()->where('auditable_type', 'return_settings')->orderBy('id')->get();

        expect(ReturnSettings::current()->only(['window_days', 'customer_requests', 'photos_required']))->toBe(['window_days' => 0, 'customer_requests' => false, 'photos_required' => true])
            // The row is made from the defaults first, so each change is recorded as one.
            ->and($audits->pluck('event')->all())->toBe(['updated', 'updated', 'updated'])
            ->and($audits->pluck('user_id')->unique()->all())->toBe([$this->owner->id]);
    });
});

it('refuses settings that don\'t fit', function (array $data, string $field): void {
    returnsRequest('PATCH', 'settings', $data, as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'a negative window' => [['window_days' => -1], 'window_days'],
    'a window over a year' => [['window_days' => 366], 'window_days'],
    'a window as text' => [['window_days' => '14'], 'window_days'],
    'customer requests as a number' => [['customer_requests' => 1], 'customer_requests'],
    'photos required as text' => [['photos_required' => 'yes'], 'photos_required'],
]);

it('lets staff with returns.view see the settings, and only returns.manage change them', function (): void {
    $viewer = returnsStaffWith($this->store, ['returns.view']);
    $manager = returnsStaffWith($this->store, ['returns.view', 'returns.manage']);
    $other = returnsStaffWith($this->store, ['orders.view']);

    returnsRequest('GET', 'settings', as: $viewer)->assertOk();
    returnsRequest('PATCH', 'settings', ['window_days' => 7], as: $viewer)->assertForbidden();
    returnsRequest('GET', 'settings', as: $other)->assertForbidden();
    returnsRequest('PATCH', 'settings', ['window_days' => 7], as: $manager)->assertOk();
    returnsRequest('GET', 'settings')->assertUnauthorized();
});

it('names each reason in the buyer\'s language, with damage and the wrong item as the store\'s fault', function (): void {
    expect(ReturnReason::Damaged->label())->toBe('Arrived damaged')
        ->and(array_values(array_filter(ReturnReason::cases(), static fn (ReturnReason $reason): bool => $reason->isStoresFault())))->toBe([ReturnReason::Damaged, ReturnReason::WrongItem]);

    foreach (glob(lang_path('*'), GLOB_ONLYDIR) ?: [] as $folder) {
        app()->setLocale(basename($folder));

        foreach (ReturnReason::cases() as $reason) {
            expect($reason->label())->not->toBe('returns.reasons.'.$reason->value);
        }
    }
});
