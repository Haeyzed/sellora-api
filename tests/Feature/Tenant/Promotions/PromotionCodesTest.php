<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Promotions\Actions\FindPromotionCode;
use App\Tenant\Promotions\Actions\ManagePromotionCodes;
use App\Tenant\Promotions\Exceptions\CodeNotValidException;
use App\Tenant\Promotions\Exceptions\TooManyCodeAttemptsException;
use App\Tenant\Promotions\Jobs\GeneratePromotionCodes;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Models\PromotionCode;
use App\Tenant\Promotions\Services\PromotionCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use OwenIt\Auditing\Models\Audit;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 11: codes customers enter to get a code promotion, unique in the
 * store ignoring case. Staff add them one at a time or generate random ones
 * (no look-alike characters), in the background beyond 1,000. A code that
 * can't be used now always gets the same answer, code_not_valid; each
 * shopper may only try so many codes a minute, and a store-wide count of
 * wrong codes is logged as a warning without the codes tried.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('promotions-store', ['country_code' => 'NG']);

    [$this->owner, $this->viewer, $this->promotion] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $marketing = Role::findOrCreate('Marketing', StaffMember::GUARD);
        $marketing->givePermissionTo(Permission::findByName('promotions.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($marketing);

        return [$owner, $viewer, Promotion::factory()->withCode()->create()];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('adds codes kept as written, unique in the store ignoring case, and only to code promotions', function (): void {
    $id = $this->promotion->public_id;

    promotionRequest('POST', "/{$id}/codes", ['code' => 'Welcome10', 'usage_limit' => 500])
        ->assertCreated()
        ->assertJsonPath('data.code', 'Welcome10')
        ->assertJsonPath('data.usage_limit', 500)
        ->assertJsonPath('data.is_active', true);

    $other = $this->store->run(static fn (): string => Promotion::factory()->withCode()->create()->public_id);
    promotionRequest('POST', "/{$other}/codes", ['code' => 'WELCOME10'])->assertUnprocessable()->assertJsonPath('code', 'promotion_code_taken')->assertJsonValidationErrors('code');

    $automatic = $this->store->run(static fn (): string => Promotion::factory()->create()->public_id);
    promotionRequest('POST', "/{$automatic}/codes", ['code' => 'AUTO'])->assertUnprocessable()->assertJsonPath('code', 'promotion_takes_no_codes');

    foreach (['AB', str_repeat('A', 33), 'SAVE 10', 'SAVE%'] as $bad) {
        promotionRequest('POST', "/{$id}/codes", ['code' => $bad])->assertJsonValidationErrors('code');
    }

    expect($this->store->run(static fn (): array => Audit::query()->where('auditable_type', 'promotion_code')->pluck('event')->all()))->toBe(['created']);
});

it('keeps codes unique ignoring case and in their format in the database too', function (string $code): void {
    $this->store->run(fn () => $this->promotion->codes()->create(['code' => 'SAVE10']));

    expect(fn () => $this->store->run(fn () => DB::table('promotion_codes')->insert([
        'public_id' => strtolower((string) Str::ulid()), 'promotion_id' => $this->promotion->id, 'code' => $code, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(QueryException::class);
})->with(['another case' => 'save10', 'too short' => 'AB', 'a space' => 'SAVE 20']);

it('generates codes from an alphabet without look-alikes, with a prefix and a limit each, and logs the batch', function (): void {
    $this->withHeader('Idempotency-Key', 'generate-first-batch');
    promotionRequest('POST', "/{$this->promotion->public_id}/codes/generate", ['count' => 25, 'prefix' => 'xmas-', 'length' => 8, 'usage_limit' => 1])
        ->assertOk()
        ->assertExactJson(['count' => 25, 'queued' => false]);

    $codes = $this->store->run(static fn (): array => PromotionCode::query()->get(['code', 'usage_limit'])->toArray());
    expect($codes)->toHaveCount(25)
        ->and(array_unique(array_column($codes, 'usage_limit')))->toBe([1])
        ->and(array_filter(array_column($codes, 'code'), static fn (string $code): bool => preg_match('/^XMAS-[A-HJ-NP-Z2-9]{8}$/', $code) !== 1))->toBe([]);

    $entry = $this->store->run(static fn (): Activity => Activity::query()->inLog('promotions')->sole());
    expect($entry->event)->toBe('promotion_codes_generated')
        ->and($entry->properties->toArray())->toBe(['count' => 25, 'queued' => false]);
});

it('needs an idempotency key to generate codes, so a retry never makes a second batch', function (): void {
    promotionRequest('POST', "/{$this->promotion->public_id}/codes/generate", ['count' => 5])->assertBadRequest()->assertJsonPath('code', 'idempotency_key_missing');

    $this->withHeader('Idempotency-Key', 'generate-once-only');
    promotionRequest('POST', "/{$this->promotion->public_id}/codes/generate", ['count' => 5])->assertOk();
    $this->withHeader('Idempotency-Key', 'generate-once-only');
    promotionRequest('POST', "/{$this->promotion->public_id}/codes/generate", ['count' => 5])->assertOk()->assertHeader('Idempotent-Replayed', 'true');

    expect($this->store->run(static fn (): int => PromotionCode::query()->count()))->toBe(5);
});

it('makes large batches on the bulk queue', function (): void {
    config(['promotions.generated_codes.made_at_once' => 10]);
    Queue::fake();

    $this->withHeader('Idempotency-Key', 'generate-big-batch');
    promotionRequest('POST', "/{$this->promotion->public_id}/codes/generate", ['count' => 30])->assertAccepted()->assertExactJson(['count' => 30, 'queued' => true]);

    Queue::assertPushedOn('bulk', GeneratePromotionCodes::class, fn (GeneratePromotionCodes $job): bool => $job->promotionId === $this->promotion->id && $job->count === 30);
    expect($this->store->run(static fn (): int => PromotionCode::query()->count()))->toBe(0);

    $this->store->run(fn () => app()->call([new GeneratePromotionCodes($this->promotion->id, 30, 12, '', null), 'handle']));
    expect($this->store->run(static fn (): int => PromotionCode::query()->count()))->toBe(30);
});

it('makes fresh codes for any that clash with a code already in the store, so a batch always has its full count', function (): void {
    // The same seed makes the same codes, so the first one is taken before the batch is made.
    $first = (new PromotionCodeGenerator(new Randomizer(new Mt19937(7))))->make(1, 8)[0];
    app()->instance(PromotionCodeGenerator::class, new PromotionCodeGenerator(new Randomizer(new Mt19937(7))));

    $this->store->run(function () use ($first): void {
        $this->promotion->codes()->create(['code' => mb_strtolower($first)]);
        app(ManagePromotionCodes::class)->insertGenerated($this->promotion, 20, 8, '', null);
    });

    expect($this->store->run(static fn (): int => PromotionCode::query()->count()))->toBe(21);
});

it('lists a promotion\'s codes newest first, by the start of the code or by whether they are on', function (): void {
    $this->store->run(function (): void {
        $this->promotion->codes()->create(['code' => 'SPRING-1']);
        $this->promotion->codes()->create(['code' => 'Spring-2', 'is_active' => false]);
        $this->promotion->codes()->create(['code' => 'AUTUMN']);
        Promotion::factory()->withCode()->create()->codes()->create(['code' => 'ELSEWHERE']);
    });
    $codes = fn (string $query = ''): array => array_column(promotionRequest('GET', "/{$this->promotion->public_id}/codes{$query}", as: $this->viewer)->assertOk()->json('data'), 'code');

    expect($codes())->toBe(['AUTUMN', 'Spring-2', 'SPRING-1'])
        ->and($codes('?search=spring'))->toBe(['Spring-2', 'SPRING-1'])
        ->and($codes('?is_active=0'))->toBe(['Spring-2']);
});

it('switches codes off or on, changes their limit, deletes them, and only through their own promotion', function (): void {
    [$code, $elsewhere] = $this->store->run(fn (): array => [
        $this->promotion->codes()->create(['code' => 'SAVE10'])->public_id,
        Promotion::factory()->withCode()->create()->codes()->create(['code' => 'OTHER'])->public_id,
    ]);
    $id = $this->promotion->public_id;

    promotionRequest('PATCH', "/{$id}/codes/{$code}", ['is_active' => false, 'usage_limit' => 3])->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.usage_limit', 3);
    promotionRequest('PATCH', "/{$id}/codes/{$elsewhere}", ['is_active' => false])->assertNotFound();
    promotionRequest('DELETE', "/{$id}/codes/{$elsewhere}")->assertNotFound();
    promotionRequest('DELETE', "/{$id}/codes/{$code}")->assertNoContent();

    expect($this->store->run(static fn (): array => PromotionCode::query()->pluck('code')->all()))->toBe(['OTHER']);
});

it('finds a code in any case, and gives the same answer for every code that can\'t be used now', function (Closure $spoil): void {
    $this->store->run(function () use ($spoil): void {
        $this->promotion->codes()->create(['code' => 'Welcome10']);

        expect(app(FindPromotionCode::class)->find(' welcome10 ')->code)->toBe('Welcome10');

        $spoil($this->promotion);

        expect(fn () => app(FindPromotionCode::class)->find('WELCOME10'))->toThrow(CodeNotValidException::class);
    });
})->with([
    'the code switched off' => [static fn (Promotion $promotion) => $promotion->codes()->update(['is_active' => false])],
    'the promotion deactivated' => [static fn (Promotion $promotion) => $promotion->update(['is_active' => false])],
    'the promotion archived' => [static fn (Promotion $promotion) => $promotion->delete()],
    'the promotion not started' => [static fn (Promotion $promotion) => $promotion->update(['starts_at' => now()->addHour()])],
    'the promotion ended' => [static fn (Promotion $promotion) => $promotion->update(['ends_at' => now()])],
    'another code' => [static fn (Promotion $promotion) => $promotion->codes()->update(['code' => 'OTHER10'])],
]);

it('lets each shopper try only so many codes a minute, whatever the answer', function (): void {
    config(['api.rate_limits.coupon_redemption' => 3]);

    $this->store->run(function (): void {
        $this->promotion->codes()->create(['code' => 'SAVE10']);
        $find = app(FindPromotionCode::class);

        foreach (['NOPE1', 'NOPE2', 'SAVE10'] as $code) {
            try {
                $find->forShopper($code, 'cart:abc');
            } catch (CodeNotValidException) {
            }
        }

        expect(fn () => $find->forShopper('SAVE10', 'cart:abc'))->toThrow(TooManyCodeAttemptsException::class)
            ->and($find->forShopper('SAVE10', 'cart:xyz')->code)->toBe('SAVE10');

        $this->travel(61)->seconds();
        expect($find->forShopper('SAVE10', 'cart:abc')->code)->toBe('SAVE10');
    });
});

it('counts wrong codes for the whole store and logs a warning once past the threshold, without the codes tried and without stopping anyone', function (): void {
    config(['promotions.failed_code_attempts_warning_per_hour' => 3]);
    Log::spy();

    $this->store->run(function (): void {
        $this->promotion->codes()->create(['code' => 'SAVE10']);
        $find = app(FindPromotionCode::class);

        foreach (range(1, 5) as $attempt) {
            expect(fn () => $find->forShopper("GUESS{$attempt}", "ip:10.0.0.{$attempt}"))->toThrow(CodeNotValidException::class);
        }

        expect($find->forShopper('SAVE10', 'ip:10.0.0.9')->code)->toBe('SAVE10');
    });

    Log::shouldHaveReceived('warning')->once()->withArgs(static fn (string $message, array $context): bool => $context === ['failed_attempts' => 3] && ! str_contains($message.json_encode($context), 'GUESS'));
});

it('needs promotions.view to see codes and promotions.manage to change them', function (): void {
    $code = $this->store->run(fn (): string => $this->promotion->codes()->create(['code' => 'SAVE10'])->public_id);
    $id = $this->promotion->public_id;

    promotionRequest('POST', "/{$id}/codes", ['code' => 'MINE'], $this->viewer)->assertForbidden();
    $this->withHeader('Idempotency-Key', 'viewer-generate');
    promotionRequest('POST', "/{$id}/codes/generate", ['count' => 1], $this->viewer)->assertForbidden();
    promotionRequest('PATCH', "/{$id}/codes/{$code}", ['is_active' => false], $this->viewer)->assertForbidden();
    promotionRequest('DELETE', "/{$id}/codes/{$code}", as: $this->viewer)->assertForbidden();
    promotionRequest('GET', "/{$id}/codes", as: $this->store->run(static fn (): StaffMember => StaffMember::factory()->create()))->assertForbidden();
});
