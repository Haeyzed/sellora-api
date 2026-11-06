<?php

declare(strict_types=1);

use App\Shared\Privacy\PersonalDataRegistry;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Customers\Actions\EraseCustomer;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Models\StoreSettings;
use App\Tenant\Storefront\Actions\AddToWishlist;
use App\Tenant\Storefront\Data\WishlistEntryData;
use App\Tenant\Storefront\Models\WishlistItem;
use Illuminate\Database\QueryException;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Sections 10 and 13.1: a signed-in customer's wishlist holds products, and
 * optionally the variant chosen; no quantity, price or stock is held, and
 * out-of-stock products may be saved. Saving twice changes nothing; a
 * guest's browser list is merged at sign-in; "move to cart" goes through the
 * cart's own rules. At most storefront.wishlist_max_items, held under
 * concurrency by a lock per customer. Each customer sees only their own,
 * never a stock quantity, and the wishlist is in their data export and
 * deleted when they are erased. Nothing crosses stores or guards.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('wish-store');
    $this->store->run(static fn (): bool => StoreSettings::query()->sole()->update(['default_locale' => 'en', 'enabled_locales' => ['en']]));

    [$this->ada, $this->grace, $this->products] = $this->store->run(static function (): array {
        $products = [];

        foreach (['shirt' => [2999, 1999], 'socks' => [500], 'hat' => [1500]] as $slug => $prices) {
            $products[$slug] = storefrontProduct(['en' => ucfirst($slug)], prices: $prices, attributes: ['slug' => $slug])->load('variants');

            foreach ($products[$slug]->variants as $variant) {
                stockOnHandOf($variant);
            }
        }

        return [Customer::factory()->create(), Customer::factory()->create(), $products];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('saves products and variants once each, and lists them newest first with their price now', function (): void {
    ['shirt' => $shirt, 'socks' => $socks] = $this->products;

    customerRequest('POST', 'wishlist', wishlistEntryFor($shirt), $this->ada, 'wish-store')->assertCreated()
        ->assertJsonPath('data.variant', null)->assertJsonPath('data.price.amount', 1999)->assertJsonPath('data.price_is_from', true);
    customerRequest('POST', 'wishlist', wishlistEntryFor($shirt, 0), $this->ada, 'wish-store')->assertCreated()->assertJsonPath('data.price.amount', 2999);
    customerRequest('POST', 'wishlist', ['product_id' => $socks->public_id], $this->ada, 'wish-store')->assertCreated();
    customerRequest('POST', 'wishlist', wishlistEntryFor($shirt), $this->ada, 'wish-store')->assertOk();

    $response = customerRequest('GET', 'wishlist', as: $this->ada, subdomain: 'wish-store')->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.*.product.slug', ['socks', 'shirt', 'shirt'])
        ->assertJsonPath('data.1.variant.id', $shirt->variants[0]->public_id)
        ->assertJsonPath('data.0.is_for_sale', true)
        ->assertJsonPath('data.0.available_for_sale', true)
        ->assertJsonPath('data.0.price_is_from', false);

    expectNoStockQuantity($response);

    // The database refuses a second copy too, also of a product saved without a variant (null counts as a value here).
    $this->store->run(function () use ($shirt): void {
        expect(fn () => WishlistItem::query()->create(['customer_id' => $this->ada->id, 'product_id' => $shirt->id, 'product_variant_id' => null]))->toThrow(QueryException::class);
    });
});

it('refuses what customers can\'t buy, and a variant of another product', function (): void {
    ['shirt' => $shirt, 'socks' => $socks] = $this->products;
    $draft = $this->store->run(static fn (): Product => storefrontProduct(['en' => 'Draft'], ProductStatus::Draft));

    customerRequest('POST', 'wishlist', ['product_id' => $draft->public_id], $this->ada, 'wish-store')->assertUnprocessable()->assertJsonValidationErrors('product_id');
    customerRequest('POST', 'wishlist', ['product_id' => '01J00000000000000000000000'], $this->ada, 'wish-store')->assertUnprocessable()->assertJsonValidationErrors('product_id');
    customerRequest('POST', 'wishlist', ['product_id' => $shirt->public_id, 'variant_id' => $socks->variants[0]->public_id], $this->ada, 'wish-store')
        ->assertUnprocessable()->assertJsonValidationErrors('variant_id');

    expect($this->store->run(static fn (): int => WishlistItem::query()->count()))->toBe(0);
});

it('keeps what sold out or stopped being sold, as unavailable with no price', function (): void {
    ['shirt' => $shirt, 'socks' => $socks, 'hat' => $hat] = $this->products;

    foreach ([wishlistEntryFor($shirt, 1), wishlistEntryFor($socks), wishlistEntryFor($hat)] as $entry) {
        customerRequest('POST', 'wishlist', $entry, $this->ada, 'wish-store')->assertCreated();
    }

    $this->store->run(static function () use ($shirt, $socks, $hat): void {
        soldOut($shirt->variants[1]);
        $socks->forceFill(['status' => ProductStatus::Draft])->save();
        $hat->delete();
    });

    // A sold-out variant can still be saved.
    customerRequest('POST', 'wishlist', wishlistEntryFor($shirt, 1), $this->ada, 'wish-store')->assertOk()->assertJsonPath('data.available_for_sale', false);

    customerRequest('GET', 'wishlist', as: $this->ada, subdomain: 'wish-store')->assertOk()
        ->assertJsonPath('data.*.product.slug', ['hat', 'socks', 'shirt'])
        ->assertJsonPath('data.*.is_for_sale', [false, false, true])
        ->assertJsonPath('data.0.price', null)
        ->assertJsonPath('data.1.price', null)
        ->assertJsonPath('data.2.price.amount', 1999)
        ->assertJsonPath('data.*.available_for_sale', [false, false, false]);
});

it('lets each customer see and remove only their own', function (): void {
    $adaItem = customerRequest('POST', 'wishlist', wishlistEntryFor($this->products['shirt']), $this->ada, 'wish-store')->json('data.id');
    customerRequest('POST', 'wishlist', wishlistEntryFor($this->products['socks']), $this->grace, 'wish-store')->assertCreated();

    customerRequest('GET', 'wishlist', as: $this->grace, subdomain: 'wish-store')->assertJsonPath('data.*.product.slug', ['socks']);
    customerRequest('DELETE', "wishlist/{$adaItem}", as: $this->grace, subdomain: 'wish-store')->assertNotFound();
    customerRequest('POST', "wishlist/{$adaItem}/cart", ['quantity' => 1], $this->grace, 'wish-store')->assertNotFound();
    customerRequest('DELETE', "wishlist/{$adaItem}", as: $this->ada, subdomain: 'wish-store')->assertNoContent();
    customerRequest('GET', 'wishlist', as: $this->ada, subdomain: 'wish-store')->assertJsonCount(0, 'data');

    customerRequest('GET', 'wishlist', subdomain: 'wish-store')->assertUnauthorized();
    $staff = $this->store->run(static fn (): StaffMember => StaffMember::factory()->create());
    $staffToken = staffTokenFor($staff, 'wish-store');
    customerRequest('GET', 'wishlist', as: $staffToken, subdomain: 'wish-store')->assertUnauthorized();
    customerRequest('GET', 'wishlist?per_page=1000', as: $this->ada, subdomain: 'wish-store')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

it('holds at most storefront.wishlist_max_items, and saving what is already there still works when full', function (): void {
    config(['storefront.wishlist_max_items' => 2]);
    ['shirt' => $shirt, 'socks' => $socks, 'hat' => $hat] = $this->products;

    customerRequest('POST', 'wishlist', wishlistEntryFor($shirt), $this->ada, 'wish-store')->assertCreated();
    customerRequest('POST', 'wishlist', wishlistEntryFor($socks), $this->ada, 'wish-store')->assertCreated();
    customerRequest('POST', 'wishlist', wishlistEntryFor($hat), $this->ada, 'wish-store')->assertUnprocessable()->assertJsonPath('code', 'wishlist_full');
    customerRequest('POST', 'wishlist', wishlistEntryFor($socks), $this->ada, 'wish-store')->assertOk();
    customerRequest('POST', 'wishlist', wishlistEntryFor($hat), $this->grace, 'wish-store')->assertCreated();
});

it('counts and saves under a lock per customer, so two saves at once never pass the limit', function (): void {
    $this->store->run(function (): void {
        $entry = new WishlistEntryData($this->products['hat'], null);
        $other = SecondConnection::open()->holdAdvisoryLock('wishlist:'.$this->ada->id);

        // Ada's save waits for the other one to finish before counting; Grace's doesn't.
        expect($other->blockedStatement(fn () => app(AddToWishlist::class)->handle($this->ada, $entry)))->toContain('pg_advisory_xact_lock')
            ->and($other->blocks(fn () => app(AddToWishlist::class)->handle($this->grace, $entry)))->toBeFalse();
        $other->rollBack();
    });
});

it('merges a guest\'s list at sign-in, once, skipping what is no longer sold, and all or nothing when it wouldn\'t fit', function (): void {
    ['shirt' => $shirt, 'socks' => $socks, 'hat' => $hat] = $this->products;
    $draft = $this->store->run(static fn (): Product => storefrontProduct(['en' => 'Draft'], ProductStatus::Draft));
    customerRequest('POST', 'wishlist', wishlistEntryFor($shirt), $this->ada, 'wish-store')->assertCreated();
    $guestList = ['items' => [wishlistEntryFor($shirt), wishlistEntryFor($socks), ['product_id' => $draft->public_id], wishlistEntryFor($shirt, 0), wishlistEntryFor($socks), ['product_id' => $hat->public_id, 'variant_id' => $socks->variants[0]->public_id]]];

    customerRequest('POST', 'wishlist/merges', $guestList, $this->ada, 'wish-store')->assertNoContent();
    customerRequest('POST', 'wishlist/merges', $guestList, $this->ada, 'wish-store')->assertNoContent();

    expect($this->store->run(fn (): int => WishlistItem::query()->where('customer_id', $this->ada->id)->count()))->toBe(3);

    config(['storefront.wishlist_max_items' => 4]);
    customerRequest('POST', 'wishlist/merges', ['items' => [wishlistEntryFor($hat), wishlistEntryFor($shirt, 1)]], $this->ada, 'wish-store')->assertUnprocessable()->assertJsonPath('code', 'wishlist_full');
    expect($this->store->run(fn (): int => WishlistItem::query()->where('customer_id', $this->ada->id)->count()))->toBe(3);

    customerRequest('POST', 'wishlist/merges', ['items' => array_fill(0, 5, wishlistEntryFor($hat))], $this->ada, 'wish-store')->assertUnprocessable()->assertJsonValidationErrors('items');
});

it('moves an item to the cart through the cart\'s own rules, and off the wishlist only once it is there', function (): void {
    ['shirt' => $shirt, 'socks' => $socks] = $this->products;
    $chosen = customerRequest('POST', 'wishlist', wishlistEntryFor($shirt, 0), $this->ada, 'wish-store')->json('data.id');
    $notChosen = customerRequest('POST', 'wishlist', wishlistEntryFor($socks), $this->ada, 'wish-store')->json('data.id');

    customerRequest('POST', "wishlist/{$chosen}/cart", ['quantity' => 2, 'variant_id' => $shirt->variants[1]->public_id], $this->ada, 'wish-store')->assertUnprocessable()->assertJsonValidationErrors('variant_id');
    customerRequest('POST', "wishlist/{$chosen}/cart", ['quantity' => 0], $this->ada, 'wish-store')->assertUnprocessable()->assertJsonValidationErrors('quantity');
    customerRequest('POST', "wishlist/{$chosen}/cart", ['quantity' => 2], $this->ada, 'wish-store')->assertNoContent();
    customerRequest('POST', "wishlist/{$notChosen}/cart", ['quantity' => 1], $this->ada, 'wish-store')->assertUnprocessable()->assertJsonValidationErrors('variant_id');

    $this->store->run(static fn () => soldOut($socks->variants[0]));
    customerRequest('POST', "wishlist/{$notChosen}/cart", ['quantity' => 1, 'variant_id' => $socks->variants[0]->public_id], $this->ada, 'wish-store')->assertUnprocessable();

    $this->store->run(function () use ($shirt): void {
        $lines = Cart::query()->where('customer_id', $this->ada->id)->sole()->lines;

        expect($lines)->toHaveCount(1)
            ->and($lines[0]->product_variant_id)->toBe($shirt->variants[0]->id)
            ->and($lines[0]->quantity)->toBe(2)
            ->and(WishlistItem::query()->where('customer_id', $this->ada->id)->pluck('product_id')->all())->toBe([$this->products['socks']->id]);
    });
});

it('is in the customer\'s data export, and deleted when they are erased, leaving everyone else\'s', function (): void {
    customerRequest('POST', 'wishlist', wishlistEntryFor($this->products['shirt'], 0), $this->ada, 'wish-store')->assertCreated();
    customerRequest('POST', 'wishlist', wishlistEntryFor($this->products['socks']), $this->grace, 'wish-store')->assertCreated();

    $this->store->run(function (): void {
        $export = app(PersonalDataRegistry::class)->export(CustomerDataSubject::of($this->ada))['wishlist'];

        expect($export)->toHaveCount(1)
            ->and($export[0])->toMatchArray(['product' => 'Shirt', 'sku' => $this->products['shirt']->variants[0]->sku]);

        app(EraseCustomer::class)->erase($this->ada);

        expect(WishlistItem::query()->where('customer_id', $this->ada->id)->exists())->toBeFalse()
            ->and(WishlistItem::query()->count())->toBe(1);
    });
});

it('never saves another store\'s product, and a customer\'s token works only in their store', function (): void {
    $other = createStore('other-wish-store');
    [$theirProduct, $theirCustomer] = $other->run(static fn (): array => [storefrontProduct(['en' => 'Theirs']), Customer::factory()->create()]);

    customerRequest('POST', 'wishlist', ['product_id' => $theirProduct->public_id], $this->ada, 'wish-store')->assertUnprocessable()->assertJsonValidationErrors('product_id');
    customerRequest('GET', 'wishlist', as: customerTokenFor($theirCustomer, 'other-wish-store'), subdomain: 'wish-store')->assertUnauthorized();
});
