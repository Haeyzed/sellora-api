<?php

declare(strict_types=1);

use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\TaxIdFormat;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Payments\Enums\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Checkout\FakeGiftCardPaymentMethod;
use Tests\Fixtures\Checkout\MinimumOrderValidator;
use Tests\Fixtures\Payments\FakePaymentGateway;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16.2: starting and changing a checkout, and the quote it shows. One
 * open checkout per cart; the quote is priced by PriceCart with everything
 * the checkout knows, and keeps its version (and its exchange rate) for 15
 * minutes while nothing charged changes.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('cart-store', ['country_code' => 'NG']);
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);
        offerStoreCurrency('USD', '0.001');

        return $owner;
    });

    $nigeria = shippingZone('Nigeria', ['NG'], subdomain: 'cart-store');
    $this->standard = cartShippingMethod(['zone' => $nigeria, 'name' => ['en' => 'Standard'], 'rates' => [['from' => 0, 'to' => null, 'amount' => 150000]]]);
    FakeGiftCardPaymentMethod::reset();
    MinimumOrderValidator::$minimum = 0;
});

afterEach(function (): void {
    deleteAllStores();
});

it('starts a guest checkout with its quote, and picks the same one up again', function (): void {
    $token = checkoutCart();

    $started = checkoutRequest('POST', token: $token)->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.can_be_placed', false)
        ->assertJsonPath('data.currency', 'NGN');
    expect(checkoutIssues($started))->toBe(['shipping_method_required', 'contact_email_required', 'payment_method_required']);

    checkoutRequest('POST', token: $token)->assertCreated()->assertJsonPath('data.id', $started->json('data.id'))->assertJsonPath('data.version', 1);
    checkoutRequest('GET', token: $token)->assertOk()->assertJsonPath('data.id', $started->json('data.id'));

    expect($this->store->run(static fn (): int => Checkout::query()->count()))->toBe(1);
});

it('starts nothing for an empty cart or none, and finds no checkout before one starts', function (): void {
    $empty = newGuestCart();

    checkoutRequest('POST', token: $empty)->assertConflict()->assertJsonPath('code', 'checkout_cannot_start');
    checkoutRequest('GET', token: $empty)->assertNotFound()->assertJsonPath('code', 'checkout_not_found');
    checkoutRequest('PATCH', '', ['customer_note' => 'Hi'], token: $empty)->assertNotFound()->assertJsonPath('code', 'checkout_not_found');
    checkoutRequest('POST', token: str_repeat('a', 64))->assertNotFound()->assertJsonPath('code', 'cart_not_found');
    checkoutRequest('POST')->assertNotFound()->assertJsonPath('code', 'cart_not_found');
});

it('keeps the quote\'s version while nothing charged changes, and gives a new one when something does or it expires', function (): void {
    $token = checkoutCart();
    checkoutRequest('POST', token: $token)->assertCreated()->assertJsonPath('data.version', 1);

    // The shipping charge joins the total.
    checkoutRequest('PATCH', '', ['shipping_method' => $this->standard], token: $token)->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.shipping.amount.amount', 150000);

    // Nothing charged changes.
    $same = checkoutRequest('PATCH', '', ['contact' => ['email' => 'Ada@Example.com', 'name' => 'Ada'], 'customer_note' => 'Ring twice'], token: $token)->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.contact.email', 'ada@example.com');
    checkoutRequest('GET', token: $token)->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.expires_at', $same->json('data.expires_at'));

    // The merchant changes the price.
    $this->store->run(static fn () => ProductVariant::query()->sole()->forceFill(['price' => Money::ofMinor(12000, 'NGN')])->save());
    checkoutRequest('GET', token: $token)->assertOk()->assertJsonPath('data.version', 3)->assertJsonPath('data.lines.0.unit_price.amount', 12000);

    // The quote expires: shown again as a new version, though nothing changed.
    $this->travel(14)->minutes();
    checkoutRequest('GET', token: $token)->assertOk()->assertJsonPath('data.version', 3);
    $this->travel(2)->minutes();
    $renewed = checkoutRequest('GET', token: $token)->assertOk()->assertJsonPath('data.version', 4);
    expect($renewed->json('data.expires_at'))->toBe(now()->addMinutes(15)->toIso8601String());
});

it('keeps the exchange rate a quote was shown at until it expires', function (): void {
    $token = checkoutCart();
    cartRequest('PATCH', '', ['currency' => 'USD'], token: $token, ifMatch: cartVersion($token))->assertOk();

    checkoutRequest('POST', token: $token)->assertCreated()->assertJsonPath('data.currency', 'USD')->assertJsonPath('data.lines.0.unit_price.amount', 10);
    expect($this->store->run(static fn (): array => Checkout::query()->sole()->exchange_rate))->toMatchArray(['currency' => 'USD', 'source' => 'manual']);

    // The merchant doubles the rate: the quote shown still stands.
    $this->store->run(static fn () => DB::table('store_currencies')->where('currency_code', 'USD')->update(['manual_rate' => '0.002', 'manual_rate_set_at' => now()]));
    $this->travel(10)->minutes();
    checkoutRequest('GET', token: $token)->assertOk()->assertJsonPath('data.version', 1)->assertJsonPath('data.lines.0.unit_price.amount', 10);

    // Once it expires, today's rate.
    $this->travel(6)->minutes();
    checkoutRequest('GET', token: $token)->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.lines.0.unit_price.amount', 20);
});

it('quotes again at today\'s rate when the market rate a quote kept goes stale', function (): void {
    // A market rate fetched 47 hours 55 minutes ago: 5 minutes from going stale.
    $fetchedAt = now()->subHours(48)->addMinutes(5);
    DB::connection('central')->table('exchange_rates')->insert([
        ['currency' => 'USD', 'units_per_usd' => '1', 'source' => 'fake', 'fetched_at' => $fetchedAt, 'created_at' => now()],
        ['currency' => 'NGN', 'units_per_usd' => '1000', 'source' => 'fake', 'fetched_at' => $fetchedAt, 'created_at' => now()],
    ]);
    $this->store->run(static fn () => DB::table('store_currencies')->where('currency_code', 'USD')->update(['manual_rate' => null, 'manual_rate_set_at' => null]));
    $token = checkoutCart();
    cartRequest('PATCH', '', ['currency' => 'USD'], token: $token, ifMatch: cartVersion($token))->assertOk();

    // ₦100 at ₦1,000 to the dollar.
    checkoutRequest('POST', token: $token)->assertCreated()->assertJsonPath('data.currency', 'USD')->assertJsonPath('data.lines.0.unit_price.amount', 10);
    expect($this->store->run(static fn (): array => Checkout::query()->sole()->exchange_rate))->toMatchArray(['source' => 'market']);

    // A newer market rate arrives: the quote still keeps the one it was shown at.
    $this->travel(3)->minutes();
    marketRates(['NGN' => '2000']);
    checkoutRequest('GET', token: $token)->assertOk()->assertJsonPath('data.version', 1)->assertJsonPath('data.lines.0.unit_price.amount', 10);

    // The kept rate goes stale within the quote's lifetime: a new quote at today's rate.
    $this->travel(4)->minutes();
    checkoutRequest('GET', token: $token)->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.currency', 'USD')->assertJsonPath('data.lines.0.unit_price.amount', 5);
});

it('prices for who is buying: a guest\'s first-order discount once their email is known', function (): void {
    $this->store->run(static fn () => runningPromotion(['percent' => Decimal::of(10), 'first_order_only' => true]));
    $token = checkoutCart();

    checkoutRequest('POST', token: $token)->assertCreated()->assertJsonPath('data.totals.discount.amount', 0);
    checkoutRequest('PATCH', '', ['contact' => ['email' => 'ada@example.com']], token: $token)->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.totals.discount.amount', 1000);
});

it('records marketing consent only when the shopper explicitly says yes', function (): void {
    $token = checkoutCart();
    checkoutRequest('POST', token: $token)->assertCreated()
        ->assertJsonPath('data.marketing_consents', ['email' => false, 'sms' => false])
        ->assertJsonStructure(['data' => ['marketing_consent_wording' => ['email', 'sms']]]);

    checkoutRequest('PATCH', '', ['marketing_consents' => ['email' => true]], token: $token)->assertOk()->assertJsonPath('data.marketing_consents', ['email' => true, 'sms' => false]);
    checkoutRequest('PATCH', '', ['marketing_consents' => ['email' => 'yes']], token: $token)->assertUnprocessable()->assertJsonValidationErrors('marketing_consents.email');
    // Anything left out is a no.
    checkoutRequest('PATCH', '', ['marketing_consents' => []], token: $token)->assertOk()->assertJsonPath('data.marketing_consents', ['email' => false, 'sms' => false]);
    checkoutRequest('PATCH', '', ['marketing_consents' => ['email' => false, 'sms' => true]], token: $token)->assertOk()->assertJsonPath('data.marketing_consents', ['email' => false, 'sms' => true]);
    // Not sent at all, unchanged.
    checkoutRequest('PATCH', '', ['customer_note' => 'Hi'], token: $token)->assertOk()->assertJsonPath('data.marketing_consents', ['email' => false, 'sms' => true]);
});

it('uses a signed-in customer\'s account email, which they can\'t replace', function (): void {
    [$ada, $variant] = $this->store->run(static fn (): array => [Customer::factory()->create(['email' => 'ada@example.com']), cartVariant(10000)->public_id]);
    cartRequest('POST', as: $ada)->assertCreated();
    cartRequest('POST', '/lines', ['variant' => $variant, 'quantity' => 1], as: $ada)->assertOk();

    checkoutRequest('POST', as: $ada)->assertCreated()->assertJsonPath('data.contact.email', 'ada@example.com');
    checkoutRequest('PATCH', '', ['contact' => ['email' => 'someone@example.com']], as: $ada)->assertUnprocessable()->assertJsonValidationErrors('contact.email');

    // Their account's email changes: so does the checkout's.
    $this->store->run(static fn () => $ada->forceFill(['email' => 'ada.l@example.com'])->save());
    checkoutRequest('PATCH', '', ['customer_note' => 'Hi'], as: $ada)->assertOk()->assertJsonPath('data.contact.email', 'ada.l@example.com');
    checkoutRequest('POST', as: $ada)->assertCreated()->assertJsonPath('data.contact.email', 'ada.l@example.com');
});

it('lets guests check out only while the store allows it', function (): void {
    $token = checkoutCart();
    checkoutRequest('POST', token: $token)->assertCreated();
    [$ada, $variant] = $this->store->run(static fn (): array => [Customer::factory()->create(), cartVariant(10000)->public_id]);
    cartRequest('POST', as: $ada)->assertCreated();
    cartRequest('POST', '/lines', ['variant' => $variant, 'quantity' => 1], as: $ada)->assertOk();

    storeSettingsRequest('PATCH', ['guest_checkout' => false], subdomain: 'cart-store')->assertOk()->assertJsonPath('data.guest_checkout', false);

    checkoutRequest('POST', token: $token)->assertForbidden()->assertJsonPath('code', 'guest_checkout_not_allowed');
    checkoutRequest('GET', token: $token)->assertForbidden()->assertJsonPath('code', 'guest_checkout_not_allowed');
    checkoutRequest('PATCH', '', ['customer_note' => 'Hi'], token: $token)->assertForbidden()->assertJsonPath('code', 'guest_checkout_not_allowed');
    checkoutRequest('POST', as: $ada)->assertCreated();
});

it('needs a billing address when nothing ships, and works tax out to it', function (): void {
    $this->store->run(static function (): void {
        addTaxRate('Standard', 'NG', null, '7.5');
        addTaxRate('Standard', 'DE', null, '19');
    });
    $token = shippingCart([[10000, 1, false]]);

    $started = checkoutRequest('POST', token: $token)->assertCreated()->assertJsonPath('data.shipping.required', false);
    expect(checkoutIssues($started))->toContain('billing_country_required')->not->toContain('shipping_method_required');

    $billed = checkoutRequest('PATCH', '', ['billing_address' => berlinAddress()], token: $token)->assertOk()->assertJsonPath('data.billing_address.country_code', 'DE');
    expect(checkoutIssues($billed))->not->toContain('billing_country_required')
        ->and($billed->json('data.totals.tax.amount'))->toBeGreaterThan($started->json('data.totals.tax.amount'));
});

it('asks for the buyer\'s tax ID when the store requires it, and checks its form', function (): void {
    $token = checkoutCart();
    expect(checkoutRequest('POST', token: $token)->assertJsonPath('data.buyer_tax_id_required', false)->json('data.issues.*.code'))->not->toContain('buyer_tax_id_required');

    storeSettingsRequest('PATCH', ['buyer_tax_id_requirement' => 'required'], subdomain: 'cart-store')->assertOk();
    expect(checkoutIssues(checkoutRequest('GET', token: $token)->assertJsonPath('data.buyer_tax_id_required', true)))->toContain('buyer_tax_id_required');

    checkoutRequest('PATCH', '', ['buyer_tax_id' => ['id' => '!!', 'country' => 'DE']], token: $token)->assertUnprocessable()->assertJsonValidationErrors('buyer_tax_id.id');
    checkoutRequest('PATCH', '', ['buyer_tax_id' => ['id' => 'DE 123.456-789']], token: $token)->assertUnprocessable()->assertJsonValidationErrors('buyer_tax_id.country');
    $given = checkoutRequest('PATCH', '', ['buyer_tax_id' => ['id' => 'DE 123.456-789', 'country' => 'de']], token: $token)->assertOk()
        ->assertJsonPath('data.buyer_tax_id', ['id' => TaxIdFormat::normalise('DE 123.456-789'), 'country' => 'DE']);
    expect(checkoutIssues($given))->not->toContain('buyer_tax_id_required');

    checkoutRequest('PATCH', '', ['buyer_tax_id' => null], token: $token)->assertOk()->assertJsonPath('data.buyer_tax_id', null);
});

it('offers core\'s ways to pay: connected gateways, a bank transfer, and cash on delivery once there is something to deliver', function (): void {
    useFakeGateway();
    connectFakeGateway();
    offerManualMethod(PaymentMethod::BankTransfer);
    offerManualMethod(PaymentMethod::CashOnDelivery);
    $token = checkoutCart();

    $started = checkoutRequest('POST', token: $token)->assertCreated();
    expect(array_column($started->json('data.payment_methods'), 'key'))->toBe(['gateway:'.FakePaymentGateway::KEY, 'bank_transfer'])
        ->and($started->json('data.payment_methods.0'))->toMatchArray(['kind' => 'gateway', 'is_test' => true]);

    $shipped = checkoutRequest('PATCH', '', ['shipping_method' => $this->standard], token: $token)->assertOk();
    expect(array_column($shipped->json('data.payment_methods'), 'key'))->toBe(['gateway:'.FakePaymentGateway::KEY, 'bank_transfer', 'cash_on_delivery'])
        ->and($shipped->json('data.payment_methods.2.pay_on_delivery'))->toBeTrue();

    expect(checkoutIssues(checkoutRequest('PATCH', '', ['payment_method' => 'cheque'], token: $token)))->toContain('payment_method_not_offered');

    $chosen = checkoutRequest('PATCH', '', ['payment_method' => 'gateway:'.FakePaymentGateway::KEY, 'contact' => ['email' => 'ada@example.com']], token: $token)->assertOk();
    expect(checkoutIssues($chosen))->toBe(['shipping_address_required']);

    checkoutRequest('PATCH', '', ['shipping_address' => lagosAddress()], token: $token)->assertOk()
        ->assertJsonPath('data.issues', [])
        ->assertJsonPath('data.can_be_placed', true);
});

it('applies modules\' payment methods first, and asks modules\' validators', function (): void {
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
    app(CheckoutExtensions::class)->addValidator(MinimumOrderValidator::class);
    offerManualMethod(PaymentMethod::BankTransfer);
    FakeGiftCardPaymentMethod::reset(balance: 5000);
    $token = checkoutCart();
    checkoutRequest('POST', token: $token)->assertCreated();

    $covered = checkoutRequest('PATCH', '', ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY], 'shipping_method' => $this->standard], token: $token)->assertOk()
        ->assertJsonPath('data.module_payment_methods', [FakeGiftCardPaymentMethod::KEY]);
    expect($covered->json('data.totals.amount_due.amount'))->toBe($covered->json('data.totals.total.amount') - 5000)
        ->and(checkoutIssues($covered))->toContain('payment_method_required');

    // A balance that covers everything leaves nothing to choose a method for.
    FakeGiftCardPaymentMethod::reset(balance: 10_000_000);
    $whole = checkoutRequest('GET', token: $token)->assertOk()->assertJsonPath('data.totals.amount_due.amount', 0);
    expect(checkoutIssues($whole))->not->toContain('payment_method_required');

    // Only a module's method can be applied first.
    expect(checkoutIssues(checkoutRequest('PATCH', '', ['module_payment_methods' => ['bank_transfer']], token: $token)))->toContain('payment_method_not_offered');

    MinimumOrderValidator::$minimum = 100_000_000;
    expect(checkoutIssues(checkoutRequest('GET', token: $token)))->toContain('minimum_order_not_reached');
});
