<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Money\Decimal;
use App\Tenant\Cart\Actions\MergeGuestCart;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Actions\ChangeInventorySettings;
use App\Tenant\Inventory\Data\InventorySettingsData;
use App\Tenant\Promotions\Enums\PromotionMethod;
use App\Tenant\Promotions\Models\PromotionCode;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 12: signing in never loses a guest's cart. The storefront app calls
 * the merge right after sign-in. Without a cart of their own, the guest cart
 * becomes the customer's; otherwise a variant in both keeps the larger
 * quantity, the guest's other lines come across while they can be bought
 * and fit, and the rest are listed, never dropped silently. The guest cart is
 * then gone, and so is its token.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('cart-store', ['country_code' => 'NG']);
    $this->ada = $this->store->run(static fn (): Customer => Customer::factory()->create());
});

afterEach(function (): void {
    deleteAllStores();
});

it('makes the guest cart the customer\'s when they have none, and stops its token working', function (): void {
    $token = newGuestCart();
    $variant = $this->store->run(static function (): string {
        offerStoreCurrency('USD', '0.001');

        return cartVariant(150000)->public_id;
    });
    cartRequest('POST', '/lines', ['variant' => $variant, 'quantity' => 2], token: $token)->assertOk();
    $id = cartRequest('PATCH', '', ['currency' => 'USD'], token: $token, ifMatch: '"2"')->assertOk()->json('data.id');

    cartRequest('POST', '/merges', token: $token, as: $this->ada)->assertOk()
        ->assertJsonPath('data.id', $id)->assertJsonPath('data.currency', 'USD')->assertJsonPath('data.lines.0.quantity', 2)->assertJsonPath('data.skipped_lines', []);

    cartRequest('GET', token: $token)->assertNotFound();
    cartRequest('GET', as: $this->ada)->assertOk()->assertJsonPath('data.id', $id)->assertJsonMissingPath('data.skipped_lines');
    cartRequest('POST', '/merges', token: $token, as: $this->ada)->assertNotFound()->assertJsonPath('code', 'cart_not_found');
    expect($this->store->run(static fn (): array => Cart::query()->sole()->only(['customer_id', 'token_hash'])))->toBe(['customer_id' => $this->ada->id, 'token_hash' => null]);
});

it('keeps the larger quantity of a variant in both carts, never their sum, and brings the guest\'s other lines across', function (): void {
    [$shirt, $hat, $socks] = $this->store->run(static fn (): array => [cartVariant()->public_id, cartVariant()->public_id, cartVariant()->public_id]);
    cartRequest('POST', as: $this->ada)->assertCreated();
    cartRequest('POST', '/lines', ['variant' => $shirt, 'quantity' => 2], as: $this->ada)->assertOk();
    cartRequest('POST', '/lines', ['variant' => $hat, 'quantity' => 4], as: $this->ada)->assertOk();

    $token = newGuestCart();
    cartRequest('POST', '/lines', ['variant' => $shirt, 'quantity' => 5], token: $token)->assertOk();
    cartRequest('POST', '/lines', ['variant' => $hat, 'quantity' => 1], token: $token)->assertOk();
    cartRequest('POST', '/lines', ['variant' => $socks, 'quantity' => 3], token: $token)->assertOk();

    $cart = cartRequest('POST', '/merges', token: $token, as: $this->ada)->assertOk()->assertHeader('ETag', '"4"');

    expect(array_combine($cart->json('data.lines.*.variant.id'), $cart->json('data.lines.*.quantity')))->toBe([$shirt => 5, $hat => 4, $socks => 3])
        ->and($this->store->run(static fn (): int => Cart::query()->count()))->toBe(1);
});

it('lists the guest\'s lines that can no longer be bought or don\'t fit, rather than dropping them silently', function (): void {
    $variants = $this->store->run(static fn (): array => [cartVariant(), cartVariant(), cartVariant(), cartVariant(), cartVariant()]);
    cartRequest('POST', as: $this->ada)->assertCreated();
    cartRequest('POST', '/lines', ['variant' => $variants[0]->public_id], as: $this->ada)->assertOk();

    $token = newGuestCart();
    foreach (array_slice($variants, 1) as $variant) {
        cartRequest('POST', '/lines', ['variant' => $variant->public_id], token: $token)->assertOk();
    }

    // Room for one more line in the customer's cart.
    config(['cart.max_lines' => 2]);
    $this->store->run(static function () use ($variants): void {
        $variants[1]->product->forceFill(['status' => ProductStatus::Archived])->save();
        app(ChangeInventorySettings::class)->handle($variants[2], new InventorySettingsData(tracksStock: true));
    });

    $cart = cartRequest('POST', '/merges', token: $token, as: $this->ada)->assertOk();

    expect($cart->json('data.lines.*.variant.id'))->toBe([$variants[0]->public_id, $variants[3]->public_id])
        ->and($cart->json('data.skipped_lines'))->toBe([
            ['variant' => ['id' => $variants[1]->public_id, 'sku' => $variants[1]->sku], 'quantity' => 1, 'reason' => 'variant_not_available'],
            ['variant' => ['id' => $variants[2]->public_id, 'sku' => $variants[2]->sku], 'quantity' => 1, 'reason' => 'variant_out_of_stock'],
            ['variant' => ['id' => $variants[4]->public_id, 'sku' => $variants[4]->sku], 'quantity' => 1, 'reason' => 'cart_full'],
        ]);
});

it('keeps the customer\'s code, or else the guest\'s while it can still be used, and the customer\'s currency', function (bool $customerHasCode, bool $guestCodeStillWorks, ?string $expected): void {
    $this->store->run(static function (): void {
        offerStoreCurrency('USD', '0.001');
        runningPromotion(['method' => PromotionMethod::Code, 'percent' => Decimal::of(10)])->codes()->create(['code' => 'MINE']);
        runningPromotion(['method' => PromotionMethod::Code, 'percent' => Decimal::of(20)])->codes()->create(['code' => 'GUEST']);
    });
    cartRequest('POST', as: $this->ada)->assertCreated();

    if ($customerHasCode) {
        cartRequest('PUT', '/code', ['code' => 'MINE'], as: $this->ada, ifMatch: '"1"')->assertOk();
    }

    $token = newGuestCart();
    cartRequest('PUT', '/code', ['code' => 'GUEST'], token: $token, ifMatch: '"1"')->assertOk();
    cartRequest('PATCH', '', ['currency' => 'USD'], token: $token, ifMatch: '"2"')->assertOk();

    if (! $guestCodeStillWorks) {
        $this->store->run(static fn () => PromotionCode::query()->where('code', 'GUEST')->update(['is_active' => false]));
    }

    cartRequest('POST', '/merges', token: $token, as: $this->ada)->assertOk()
        ->assertJsonPath('data.code.code', $expected)
        ->assertJsonPath('data.currency', 'NGN');
})->with([
    'the customer has one' => [true, true, 'MINE'],
    'only the guest has one' => [false, true, 'GUEST'],
    'the guest\'s no longer works' => [false, false, null],
]);

it('merges only for a signed-in customer, with a guest token that finds a cart', function (): void {
    $token = newGuestCart();
    $staffToken = $this->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue(StaffMember::factory()->create(), StaffMember::GUARD, 'test')->plainTextToken);

    cartRequest('POST', '/merges', token: $token)->assertUnauthorized();
    cartRequest('POST', '/merges', token: $token, as: $staffToken)->assertUnauthorized();
    cartRequest('POST', '/merges', as: $this->ada)->assertNotFound()->assertJsonPath('code', 'cart_not_found');
    cartRequest('GET', token: $token)->assertOk();
});

it('waits for a merge already running, then finds the guest cart gone', function (): void {
    $token = newGuestCart();
    $ada = $this->ada;

    $this->store->run(static function () use ($token, $ada): void {
        $guest = Cart::query()->sole();

        // Another request is merging this guest cart: it holds it, and has deleted it, but not yet committed.
        $other = SecondConnection::open()->holdRowLock($guest, 'for no key update');
        $other->run(static fn ($connection) => $connection->table('carts')->where('id', $guest->id)->delete());

        $merge = static fn () => app(MergeGuestCart::class)->handle($ada, $token);
        expect($other->blockedStatement($merge))->toStartWith('select * from "carts"')->toContain('for no key update');
        $other->commit();

        expect($merge)->toThrow(CartNotFoundException::class);
    });
});

it('notices when another merge finished between finding the guest cart and locking it', function (): void {
    $token = newGuestCart();
    $ada = $this->ada;

    $this->store->run(static function () use ($token, $ada): void {
        $guest = Cart::query()->sole();
        $other = SecondConnection::open();
        $other->run(static fn ($connection) => $connection->table('carts')->where('id', $guest->id)->delete());
        $finished = false;

        // The other merge commits right after this one has found the guest cart.
        Illuminate\Support\Facades\DB::listen(static function (Illuminate\Database\Events\QueryExecuted $query) use ($other, &$finished): void {
            if (! $finished && str_contains($query->sql, '"token_hash" = ?')) {
                $finished = true;
                $other->commit();
            }
        });

        expect(static fn () => app(MergeGuestCart::class)->handle($ada, $token))->toThrow(CartNotFoundException::class)
            ->and($finished)->toBeTrue()
            ->and(Cart::query()->count())->toBe(0);
    });
});

it('merges into the customer\'s own cart when another request made it while the guest cart was being handed over', function (): void {
    $token = newGuestCart();
    $ada = $this->ada;

    $this->store->run(static function () use ($token, $ada): void {
        $variant = cartVariant();
        $guest = Cart::query()->sole();
        $guest->lines()->create(['product_variant_id' => $variant->id, 'quantity' => 2, 'seen_price' => $variant->price, 'seen_price_source' => App\Tenant\Cart\Enums\SeenPriceSource::Converted]);
        $made = null;

        // Just as the guest cart is about to become Ada's, her own cart is made and saved elsewhere.
        Event::listen('eloquent.updating: '.Cart::class, static function (Cart $cart) use ($ada, &$made): void {
            if ($made === null && $cart->customer_id === $ada->id) {
                $other = SecondConnection::open();
                $made = $other->run(static fn ($connection): int => $connection->table('carts')->insertGetId(['public_id' => strtolower((string) Str::ulid()), 'customer_id' => $ada->id, 'last_activity_at' => now(), 'created_at' => now(), 'updated_at' => now()]));
                $other->commit();
            }
        });

        $merged = app(MergeGuestCart::class)->handle($ada, $token);

        expect($merged->cart->id)->toBe($made)
            ->and($merged->cart->lines()->pluck('quantity')->all())->toBe([2])
            ->and(Cart::query()->pluck('id')->all())->toBe([$made]);
    });
});
