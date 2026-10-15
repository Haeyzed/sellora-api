<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Tenant\Cart\CartToken;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Models\CartLine;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 12: a shopper's cart. A guest's is reached only with the token it was
 * given once (stored as a hash); a signed-in customer has one of their own.
 * Lines hold variants and quantities within limits; only variants for sale
 * and not out of stock go in, and nobody is told how many are left. Changes
 * that replace or remove something name the version they were made from, so
 * two tabs never undo each other. Limits are per cart or customer, under a
 * ceiling per IP address.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('cart-store', ['country_code' => 'NG']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('gives a guest a new cart and its token once, storing only the token\'s hash', function (): void {
    $response = cartRequest('POST')->assertCreated()->assertHeader('ETag', '"1"');
    $token = $response->json('data.token');

    expect($token)->toMatch(CartToken::FORMAT)
        ->and($this->store->run(static fn (): array => Cart::query()->sole()->only(['customer_id', 'token_hash'])))->toBe(['customer_id' => null, 'token_hash' => hash('sha256', $token)])
        ->and(json_encode($this->store->run(static fn (): array => DB::table('carts')->get()->all())))->not->toContain($token);

    cartRequest('GET', token: $token)->assertOk()->assertJsonPath('data.id', $response->json('data.id'))->assertJsonMissingPath('data.token');
    // Each guest who asks gets a cart of their own.
    expect(newGuestCart())->not->toBe($token);
});

it('gives a signed-in customer their one cart, made the first time they ask, and never with a token', function (): void {
    $ada = $this->store->run(static fn (): Customer => Customer::factory()->create());

    $id = cartRequest('POST', as: $ada)->assertCreated()->assertJsonMissingPath('data.token')->json('data.id');
    cartRequest('POST', as: $ada)->assertOk()->assertJsonPath('data.id', $id);

    // A guest token sent with a sign-in is ignored: the customer's own cart answers.
    cartRequest('GET', token: newGuestCart(), as: $ada)->assertOk()->assertJsonPath('data.id', $id);
    expect($this->store->run(static fn (): int => Cart::query()->whereNotNull('customer_id')->count()))->toBe(1);
});

it('finds no cart for a missing, malformed or unknown token, nor for a customer without one, and refuses a token that isn\'t a customer\'s sign-in', function (): void {
    [$ada, $staffToken] = $this->store->run(static fn (): array => [
        Customer::factory()->create(),
        app(AccessTokenIssuer::class)->issue(StaffMember::factory()->create(), StaffMember::GUARD, 'test')->plainTextToken,
    ]);
    newGuestCart();

    foreach ([null, 'not-a-token', str_repeat('a', 43)] as $token) {
        cartRequest('GET', token: $token)->assertNotFound()->assertJsonPath('code', 'cart_not_found');
    }

    cartRequest('GET', as: $ada)->assertNotFound()->assertJsonPath('code', 'cart_not_found');
    cartRequest('GET', as: 'wrong-token')->assertUnauthorized();
    cartRequest('GET', as: $staffToken)->assertUnauthorized();
});

it('never opens one store\'s cart in another store', function (): void {
    createStore('other-cart-store', ['country_code' => 'NG']);
    $token = newGuestCart();

    cartRequest('GET', token: $token, subdomain: 'other-cart-store')->assertNotFound()->assertJsonPath('code', 'cart_not_found');
    cartRequest('GET', token: $token)->assertOk();
});

it('adds to a variant\'s line, starting one when it isn\'t there, and raises the version each time', function (): void {
    $token = newGuestCart();
    [$shirt, $hat] = $this->store->run(static fn (): array => [cartVariant()->public_id, cartVariant(name: 'Hat')->public_id]);

    cartRequest('POST', '/lines', ['variant' => $shirt], token: $token)->assertOk()->assertHeader('ETag', '"2"')->assertJsonPath('data.lines.0.quantity', 1);
    cartRequest('POST', '/lines', ['variant' => $shirt, 'quantity' => 2], token: $token)->assertOk()->assertJsonPath('data.version', 3);
    $cart = cartRequest('POST', '/lines', ['variant' => $hat, 'quantity' => 4], token: $token)->assertOk();

    expect($cart->json('data.lines.*.quantity'))->toBe([3, 4])
        ->and($cart->json('data.lines.0.variant'))->toMatchArray(['id' => $shirt])
        ->and($cart->json('data.lines.1.variant.product.name'))->toBe('Hat');
});

it('lets only variants that are for sale, and not out of stock, go in, without saying how many are left', function (string $kind, ?string $error): void {
    $token = newGuestCart();
    $variant = $this->store->run(static function () use ($kind): string {
        $variant = match ($kind) {
            'out of stock', 'sold out' => cartVariant(stock: $kind === 'sold out' ? 0 : null),
            'on backorder' => cartVariant(stock: 0, backorder: true),
            'in stock' => cartVariant(stock: 3),
            default => cartVariant(),
        };

        match ($kind) {
            'a draft' => $variant->product->forceFill(['status' => ProductStatus::Draft])->save(),
            'archived' => $variant->product->forceFill(['status' => ProductStatus::Archived])->save(),
            'unpriced' => $variant->forceFill(['price' => null, 'compare_at_price' => null, 'currency' => null])->save(),
            'in the trash' => $variant->delete(),
            'its product in the trash' => $variant->product->delete(),
            'out of stock' => app(App\Tenant\Inventory\Actions\ChangeInventorySettings::class)->handle($variant, new App\Tenant\Inventory\Data\InventorySettingsData(tracksStock: true)),
            default => null,
        };

        return $variant->public_id;
    });

    $response = cartRequest('POST', '/lines', ['variant' => $variant, 'quantity' => 5], token: $token);

    if ($error === null) {
        $response->assertOk()->assertJsonPath('data.lines.0.quantity', 5);

        return;
    }

    $response->assertUnprocessable()->assertJsonPath('code', $error);
    expect($response->getContent())->not->toMatch('/\d+ (left|available|in stock)/');
})->with([
    'in stock' => ['in stock', null],
    'not counted' => ['not counted', null],
    'on backorder' => ['on backorder', null],
    'out of stock' => ['out of stock', 'variant_out_of_stock'],
    'sold out' => ['sold out', 'variant_out_of_stock'],
    'a draft' => ['a draft', 'variant_not_available'],
    'archived' => ['archived', 'variant_not_available'],
    'unpriced' => ['unpriced', 'variant_not_available'],
    'in the trash' => ['in the trash', 'variant_not_available'],
    'its product in the trash' => ['its product in the trash', 'variant_not_available'],
]);

it('holds at most so many different variants, and so many of one', function (): void {
    config(['cart.max_lines' => 2, 'cart.max_line_quantity' => 5]);
    $token = newGuestCart();
    [$a, $b, $c] = $this->store->run(static fn (): array => [cartVariant()->public_id, cartVariant()->public_id, cartVariant()->public_id]);

    cartRequest('POST', '/lines', ['variant' => $a, 'quantity' => 3], token: $token)->assertOk();
    cartRequest('POST', '/lines', ['variant' => $a, 'quantity' => 3], token: $token)->assertUnprocessable()->assertJsonPath('code', 'cart_line_quantity_too_high');
    cartRequest('POST', '/lines', ['variant' => $a, 'quantity' => 6], token: $token)->assertUnprocessable()->assertJsonValidationErrors('quantity');
    cartRequest('POST', '/lines', ['variant' => $b], token: $token)->assertOk();
    cartRequest('POST', '/lines', ['variant' => $c], token: $token)->assertUnprocessable()->assertJsonPath('code', 'cart_full');
    // More of a variant already there still fits.
    cartRequest('POST', '/lines', ['variant' => $b, 'quantity' => 2], token: $token)->assertOk()->assertJsonPath('data.lines.1.quantity', 3);
});

it('changes and removes lines only from the cart version the shopper was looking at', function (): void {
    $token = newGuestCart();
    $variants = $this->store->run(static fn (): array => [cartVariant()->public_id, cartVariant()->public_id]);

    foreach ($variants as $variant) {
        cartRequest('POST', '/lines', ['variant' => $variant], token: $token)->assertOk();
    }

    $cart = cartRequest('GET', token: $token)->assertOk()->assertHeader('ETag', '"3"');
    [$first, $second] = $cart->json('data.lines.*.id');

    cartRequest('PATCH', "/lines/{$first}", ['quantity' => 4], token: $token)->assertStatus(428)->assertJsonPath('code', 'cart_version_required');
    cartRequest('PATCH', "/lines/{$first}", ['quantity' => 4], token: $token, ifMatch: '"2"')->assertStatus(412)->assertJsonPath('code', 'cart_changed');
    cartRequest('PATCH', "/lines/{$first}", ['quantity' => 4], token: $token, ifMatch: '"3"')->assertOk()->assertHeader('ETag', '"4"')->assertJsonPath('data.lines.0.quantity', 4);

    // The other tab, still on version 3, can't undo it.
    cartRequest('DELETE', "/lines/{$first}", token: $token, ifMatch: '"3"')->assertStatus(412);
    cartRequest('DELETE', "/lines/{$first}", token: $token, ifMatch: 'W/"4"')->assertOk()->assertJsonCount(1, 'data.lines');
    cartRequest('DELETE', '/lines', token: $token, ifMatch: '5')->assertOk()->assertJsonCount(0, 'data.lines')->assertJsonPath('data.version', 6);

    expect($this->store->run(static fn (): array => [Cart::query()->count(), CartLine::query()->count()]))->toBe([1, 0])
        ->and($second)->toBeString();
});

it('finds only lines of the shopper\'s own cart', function (): void {
    $mine = newGuestCart();
    $theirs = newGuestCart();
    $variant = $this->store->run(static fn (): string => cartVariant()->public_id);
    $theirLine = cartRequest('POST', '/lines', ['variant' => $variant], token: $theirs)->json('data.lines.0.id');

    cartRequest('PATCH', "/lines/{$theirLine}", ['quantity' => 9], token: $mine, ifMatch: '"1"')->assertNotFound();
    cartRequest('DELETE', "/lines/{$theirLine}", token: $mine, ifMatch: '"1"')->assertNotFound();
    cartRequest('GET', token: $theirs)->assertJsonPath('data.lines.0.quantity', 1);
});

it('never lets two adds at once take a cart past its limit', function (): void {
    config(['cart.max_lines' => 1]);
    $token = newGuestCart();

    $this->store->run(static function () use ($token): void {
        $cart = Cart::query()->sole();
        [$mine, $theirs] = [cartVariant(), cartVariant()];

        // The other tab holds the cart and has added its line, but not yet committed.
        $other = SecondConnection::open()->holdRowLock($cart, 'for no key update');
        $other->run(static fn ($connection) => $connection->table('cart_lines')->insert([
            'public_id' => strtolower((string) Str::ulid()), 'cart_id' => $cart->id, 'product_variant_id' => $theirs->id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]));

        $add = static fn () => app(App\Tenant\Cart\Actions\AddCartLine::class)->handle(App\Tenant\Cart\Data\Shopper::guest($token), $mine, 1);
        // It waits at its own lock on the cart, before counting the lines, not only at a later write.
        expect($other->blockedStatement($add))->toStartWith('select * from "carts"')->toContain('for no key update');
        $other->commit();

        expect($add)->toThrow(App\Tenant\Cart\Exceptions\CartFullException::class);
    });
});

it('refuses carts the database must never hold, with nulls in place', function (array $cart, ?int $quantity): void {
    $this->store->run(static function () use ($cart, $quantity): void {
        $customer = Customer::factory()->create();
        $row = ['public_id' => strtolower((string) Str::ulid()), 'last_activity_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        $values = array_map(static fn (mixed $value): mixed => $value === 'customer' ? $customer->id : $value, $cart);

        if ($quantity === null) {
            expect(static fn () => DB::table('carts')->insert([...$row, ...$values]))->toThrow(Illuminate\Database\QueryException::class);

            return;
        }

        $cartId = DB::table('carts')->insertGetId([...$row, ...$values]);
        expect(static fn () => DB::table('cart_lines')->insert(['public_id' => strtolower((string) Str::ulid()), 'cart_id' => $cartId, 'product_variant_id' => cartVariant()->id, 'quantity' => $quantity]))
            ->toThrow(Illuminate\Database\QueryException::class);
    });
})->with([
    'neither a customer nor a token' => [['customer_id' => null, 'token_hash' => null], null],
    'both a customer and a token' => [['customer_id' => 'customer', 'token_hash' => str_repeat('a', 64)], null],
    'a token that isn\'t a hash' => [['customer_id' => null, 'token_hash' => str_repeat('A', 64)], null],
    'version 0' => [['customer_id' => 'customer', 'token_hash' => null, 'version' => 0], null],
    'a line of nothing' => [['customer_id' => 'customer', 'token_hash' => null], 0],
]);

it('limits each cart on its own, so shoppers sharing an address don\'t hold each other up', function (): void {
    config(['api.rate_limits.cart' => 3, 'api.rate_limits.cart_ip_ceiling' => 100]);
    $tokens = [newGuestCart(), newGuestCart(), newGuestCart()];

    foreach ($tokens as $token) {
        foreach (range(1, 3) as $request) {
            cartRequest('GET', token: $token)->assertOk();
        }
    }

    // One cart hammering is stopped; the others behind the same address aren't.
    cartRequest('GET', token: $tokens[0])->assertTooManyRequests();
    cartRequest('GET', token: newGuestCart())->assertOk();
    // Another address is counted apart.
    cartRequest('GET', token: $tokens[0], ip: '198.51.100.7')->assertTooManyRequests();
});

it('still stops one address flooding the store with carts or requests', function (): void {
    config(['api.rate_limits.cart_creation_ip_ceiling' => 3, 'api.rate_limits.cart' => 100, 'api.rate_limits.cart_ip_ceiling' => 5]);

    $tokens = [newGuestCart(), newGuestCart(), newGuestCart()];
    cartRequest('POST')->assertTooManyRequests();
    cartRequest('POST', ip: '198.51.100.7')->assertCreated();

    foreach (range(1, 5) as $request) {
        cartRequest('GET', token: $tokens[$request % 3])->assertOk();
    }

    cartRequest('GET', token: $tokens[0])->assertTooManyRequests();
    cartRequest('GET', token: $tokens[0], ip: '198.51.100.7')->assertOk();
});
