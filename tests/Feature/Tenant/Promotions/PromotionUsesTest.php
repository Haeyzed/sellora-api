<?php

declare(strict_types=1);

use App\Shared\Money\Decimal;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Promotions\Actions\FindPromotionCode;
use App\Tenant\Promotions\Actions\ReservePromotionUses;
use App\Tenant\Promotions\Actions\SettlePromotionUses;
use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Promotions\Enums\PromotionMethod;
use App\Tenant\Promotions\Exceptions\CodeNotValidException;
use App\Tenant\Promotions\Exceptions\PromotionUnavailableException;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Models\PromotionRedemption;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 11 and section 13: each use of a promotion is recorded in a ledger,
 * reserved at checkout, committed when the order is paid and released when
 * the hold runs out or the order is cancelled. Limits (in all, per code and
 * per customer by account and email together) are counted from that ledger,
 * safely under concurrency. A lock is taken only where a limit needs
 * protecting, so an unlimited sale never makes checkouts wait for each other.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('promotions-store', ['country_code' => 'NG']);
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('reserves a use for each promotion a checkout gets, updates it when reserved again, and gives back those it no longer gets', function (): void {
    $this->store->run(static function (): void {
        // Limited to one use in all and one per customer: reserving again for the same checkout doesn't count against itself.
        $tenOff = runningPromotion(['usage_limit' => 1, 'usage_limit_per_customer' => 1]);
        $variant = promotedVariant(1000);

        reservePromotionUses('checkout-1', discountedBasket([[$variant, 1]], buyer: DiscountBuyer::guest('shopper@example.com')));
        // Priced again by the same checkout: its own use doesn't count against it.
        reservePromotionUses('checkout-1', discountedBasket([[$variant, 3]], buyer: DiscountBuyer::guest('shopper@example.com'), checkout: 'checkout-1'));

        expect(PromotionRedemption::query()->get(['promotion_id', 'status', 'discount_amount'])->toArray())
            ->toBe([['promotion_id' => $tenOff->id, 'status' => 'reserved', 'discount_amount' => 300]]);

        $tenOff->update(['is_active' => false]);
        // Priced again by the same checkout: its own use doesn't count against it.
        reservePromotionUses('checkout-1', discountedBasket([[$variant, 3]], buyer: DiscountBuyer::guest('shopper@example.com'), checkout: 'checkout-1'));

        expect(PromotionRedemption::query()->pluck('status')->map->value->all())->toBe(['released']);
    });
});

it('counts a total limit from the ledger: reserved and committed uses count, released and expired ones don\'t', function (): void {
    $this->store->run(function (): void {
        runningPromotion(['usage_limit' => 2]);
        $basket = discountedBasket([[promotedVariant(1000), 1]]);

        reservePromotionUses('checkout-1', $basket);
        reservePromotionUses('checkout-2', $basket);
        expect(fn () => reservePromotionUses('checkout-3', $basket))->toThrow(PromotionUnavailableException::class);

        // Paid: still counts. Abandoned: given back.
        app(SettlePromotionUses::class)->commit(new RedemptionHolder('checkout', 'checkout-1'), new RedemptionHolder('order', 'order-1'));
        app(SettlePromotionUses::class)->release(new RedemptionHolder('checkout', 'checkout-2'));
        reservePromotionUses('checkout-3', $basket);
        expect(fn () => reservePromotionUses('checkout-4', $basket))->toThrow(PromotionUnavailableException::class);

        // A paid order cancelled gives its use back; the next checkout gets it.
        app(SettlePromotionUses::class)->release(new RedemptionHolder('order', 'order-1'));
        reservePromotionUses('checkout-4', $basket);

        // Holds that ran out stop counting at once, before anything gives them back,
        $this->travel(16)->minutes();
        expect(discountedBasket([[promotedVariant(1000), 1]])->discounts->applied)->toHaveCount(1);

        // and are given back as soon as anyone reserves the promotion, without waiting for the sweep.
        reservePromotionUses('checkout-5', $basket);
        expect(PromotionRedemption::query()->where('holder_id', 'checkout-3')->value('status')?->value)->toBe('released');
    });
});

it('counts a limit per customer by account and email together, so checking out as a guest doesn\'t start again', function (): void {
    $this->store->run(function (): void {
        runningPromotion(['usage_limit_per_customer' => 1]);
        // Priced before anyone used it, as each checkout's basket was.
        $basket = discountedBasket([[promotedVariant(1000), 1]], buyer: DiscountBuyer::guest('someone@example.com'));
        $ada = Customer::factory()->create(['email' => 'ada@example.com']);

        reservePromotionUses('checkout-1', $basket, DiscountBuyer::customer($ada));

        expect(fn () => reservePromotionUses('checkout-2', $basket, DiscountBuyer::customer($ada)))->toThrow(PromotionUnavailableException::class)
            ->and(fn () => reservePromotionUses('checkout-3', $basket, DiscountBuyer::guest('ADA@example.com')))->toThrow(PromotionUnavailableException::class)
            // A guest can't be counted without an email.
            ->and(fn () => reservePromotionUses('checkout-4', $basket, DiscountBuyer::guest(null)))->toThrow(PromotionUnavailableException::class);

        reservePromotionUses('checkout-5', $basket, DiscountBuyer::guest('grace@example.com'));
        reservePromotionUses('checkout-6', $basket, DiscountBuyer::customer(Customer::factory()->create()));
    });
});

it('counts a code\'s own limit, and treats a used-up code as one that can\'t be used', function (): void {
    $this->store->run(function (): void {
        $promotion = runningPromotion(['method' => PromotionMethod::Code]);
        $promotion->codes()->create(['code' => 'ONCE', 'usage_limit' => 1]);
        $promotion->codes()->create(['code' => 'ALSO']);
        $variant = promotedVariant(1000);

        reservePromotionUses('checkout-1', discountedBasket([[$variant, 1]], code: 'once'));
        expect(PromotionRedemption::query()->sole()->code?->code)->toBe('ONCE');

        expect(fn () => app(FindPromotionCode::class)->find('ONCE'))->toThrow(CodeNotValidException::class)
            ->and(discountedBasket([[$variant, 1]], code: 'ONCE')->discounts->codeReason)->toBe('code_not_valid')
            ->and(fn () => reservePromotionUses('checkout-2', discountedBasket([[$variant, 1]], code: 'ALSO')))->not->toThrow(PromotionUnavailableException::class);

        $promotion->update(['usage_limit' => 2]);
        expect(fn () => app(FindPromotionCode::class)->find('ALSO'))->toThrow(CodeNotValidException::class);
    });
});

it('stops offering a promotion that is used up or that the buyer can no longer use, and says why for a code', function (): void {
    $this->store->run(function (): void {
        runningPromotion(['usage_limit' => 1]);
        $variant = promotedVariant(1000);

        reservePromotionUses('checkout-1', discountedBasket([[$variant, 1]]));
        expect(discountedBasket([[$variant, 1]])->discounts->applied)->toBe([]);

        $code = runningPromotion(['method' => PromotionMethod::Code, 'percent' => Decimal::of(20), 'usage_limit_per_customer' => 1]);
        $code->codes()->create(['code' => 'TWENTY']);
        $ada = DiscountBuyer::guest('ada@example.com');

        reservePromotionUses('checkout-2', discountedBasket([[$variant, 1]], code: 'TWENTY', buyer: $ada), $ada);

        expect(discountedBasket([[$variant, 1]], code: 'TWENTY', buyer: $ada)->discounts->codeReason)->toBe('customer_limit_reached')
            ->and(discountedBasket([[$variant, 1]], code: 'TWENTY', buyer: DiscountBuyer::guest('grace@example.com'))->discounts->codeApplies())->toBeTrue()
            ->and(discountedBasket([[$variant, 1]], code: 'TWENTY')->discounts->codeReason)->toBe('email_needed');
    });
});

it('never lets two checkouts take the last use of a promotion', function (): void {
    $this->store->run(static function (): void {
        $promotion = runningPromotion(['usage_limit' => 1]);
        $basket = discountedBasket([[promotedVariant(1000), 1]]);

        // The other checkout holds the promotion's limit, as reserving does, and has reserved its last use, but not yet committed.
        $other = SecondConnection::open()->holdRowLock($promotion, ReservePromotionUses::ROW_LOCK);
        $other->run(static fn ($connection) => $connection->table('promotion_redemptions')->insert(otherCheckoutUse($promotion)));

        expect($other->blocks(static fn () => reservePromotionUses('mine', $basket)))->toBeTrue();
        $other->commit();

        expect(fn () => reservePromotionUses('mine', $basket))->toThrow(PromotionUnavailableException::class);
    });
});

it('never lets one customer use a per-customer promotion twice at once, while other customers don\'t wait', function (): void {
    $this->store->run(static function (): void {
        $promotion = runningPromotion(['usage_limit_per_customer' => 1]);
        $ada = DiscountBuyer::guest('ada@example.com');
        $basket = discountedBasket([[promotedVariant(1000), 1]], buyer: $ada);

        // Ada's other checkout holds her lock for this promotion and has reserved, but not yet committed.
        $other = SecondConnection::open()->holdAdvisoryLock("promotion-uses:{$promotion->id}:email:ada@example.com");
        $other->run(static fn ($connection) => $connection->table('promotion_redemptions')->insert([...otherCheckoutUse($promotion), 'email' => 'ada@example.com']));

        expect($other->blocks(static fn () => reservePromotionUses('ada-again', $basket, $ada)))->toBeTrue()
            ->and($other->blocks(static fn () => reservePromotionUses('grace', $basket, DiscountBuyer::guest('grace@example.com'))))->toBeFalse();
        $other->commit();

        expect(fn () => reservePromotionUses('ada-again', $basket, $ada))->toThrow(PromotionUnavailableException::class);
    });
});

it('takes no lock for an unlimited promotion, so checkouts using it never wait for each other', function (): void {
    $this->store->run(static function (): void {
        $promotion = runningPromotion();
        $basket = discountedBasket([[promotedVariant(1000), 1]]);

        // Another checkout's transaction is still open, holding the lock a limited promotion's checkouts take, and its own use.
        $other = SecondConnection::open()->holdRowLock($promotion, ReservePromotionUses::ROW_LOCK);
        $other->run(static fn ($connection) => $connection->table('promotion_redemptions')->insert(otherCheckoutUse($promotion)));

        expect($other->blocks(static fn () => reservePromotionUses('mine', $basket)))->toBeFalse();
        $other->rollBack();

        expect(PromotionRedemption::query()->where('holder_id', 'mine')->value('status')?->value)->toBe('reserved');
    });
});

it('gives back holds that ran out in the same 5-minute sweep as stock holds', function (): void {
    $this->store->run(function (): void {
        runningPromotion();
        reservePromotionUses('checkout-1', discountedBasket([[promotedVariant(1000), 1]]));
        $this->travel(16)->minutes();
    });

    $this->artisan('inventory:release-expired-reservations')->assertSuccessful();

    expect($this->store->run(static fn (): string => PromotionRedemption::query()->sole()->status->value))->toBe('released');
});

it('keeps a code that was used, and shows how often a promotion was used', function (): void {
    [$promotion, $code] = $this->store->run(static function (): array {
        $promotion = runningPromotion(['method' => PromotionMethod::Code]);
        $code = $promotion->codes()->create(['code' => 'USED']);
        $basket = discountedBasket([[promotedVariant(1000), 1]], code: 'USED');
        reservePromotionUses('checkout-1', $basket);
        reservePromotionUses('checkout-2', $basket);
        app(SettlePromotionUses::class)->commit(new RedemptionHolder('checkout', 'checkout-1'), new RedemptionHolder('order', 'order-1'));

        return [$promotion->public_id, $code->public_id];
    });

    promotionRequest('DELETE', "/{$promotion}/codes/{$code}")->assertConflict()->assertJsonPath('code', 'promotion_code_in_use');
    promotionRequest('GET', "/{$promotion}")->assertOk()->assertJsonPath('data.times_used', 1)->assertJsonPath('data.times_reserved', 1);
});

it('keeps the ledger consistent in the database too', function (array $row, string $constraint): void {
    $this->store->run(static function () use ($row, $constraint): void {
        $promotion = runningPromotion();
        DB::table('promotion_redemptions')->insert(otherCheckoutUse($promotion));

        expect(static fn () => DB::table('promotion_redemptions')->insert([...otherCheckoutUse($promotion), 'holder_id' => 'another', ...$row]))->toThrow(QueryException::class, $constraint);
    });
})->with([
    'a second live use for one holder' => [['holder_id' => 'other-checkout'], 'promotion_redemptions_one_live_per_holder'],
    'a committed use that expires' => [['status' => 'committed', 'settled_at' => now()], 'promotion_redemptions_committed_never_expire'],
    'a settled use without its date' => [['status' => 'released', 'expires_at' => null], 'promotion_redemptions_settled_when_not_reserved'],
    'an email in upper case' => [['email' => 'Ada@Example.com'], 'promotion_redemptions_email_lower_case'],
    'an unknown status' => [['status' => 'pending', 'settled_at' => now()], 'promotion_redemptions_status_check'],
]);
