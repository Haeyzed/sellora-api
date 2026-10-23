<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\FeatureDefinition;
use App\Shared\Features\FeatureKind;
use App\Shared\Features\FeatureRegistry;
use App\Shared\Features\Features;
use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Checkout\Events\CommittedPaymentRefundFailed;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Checkout\Services\PaymentHolds;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Exceptions\PaymentGatewayUnreachableException;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Checkout\FakeGiftCardPaymentMethod;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Payments\FakePaymentGateway;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16: when an order that was paid is cancelled, each module's payment
 * method that took money for it at checkout (a gift card here) is asked to
 * give it back, whatever state the module is in short of Unavailable. What
 * was given back stops counting as paid, so the refund the order owes can
 * clear; a give-back that fails is recorded, leaves the order owing the
 * refund for staff, and is tried again by the hourly payments sweep.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('cart-store', ['country_code' => 'NG']);
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });

    $nigeria = shippingZone('Nigeria', ['NG'], subdomain: 'cart-store');
    $this->standard = cartShippingMethod(['zone' => $nigeria, 'name' => ['en' => 'Standard'], 'rates' => [['from' => 0, 'to' => null, 'amount' => 150000]]]);
    offerManualMethod(PaymentMethod::BankTransfer);
    FakeGiftCardPaymentMethod::reset();
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('gives back a gift card that paid for the whole order, and the order owes nothing more', function (): void {
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
    $order = giftCardPaidOrder();
    $reference = 'gc_'.$order->public_id;

    // Paid and not cancelled: the hourly sweep never gives anything back.
    $this->artisan('payments:check-pending')->assertSuccessful();
    expect(FakeGiftCardPaymentMethod::$steps)->toBe(['hold:160000', "commit:{$reference}"]);

    cancelAsStaff();

    // Asked once the cancellation was saved, outside any transaction.
    expect(FakeGiftCardPaymentMethod::$steps)->toBe(['hold:160000', "commit:{$reference}", "refund:{$reference}:160000"])
        ->and(FakeGiftCardPaymentMethod::$refundTransactionLevels)->toBe([0])
        ->and(refundState())->toBe(['status' => 'cancelled', 'payment_status' => 'refunded', 'refund_due' => false, 'holds' => [FakeGiftCardPaymentMethod::KEY => ['status' => 'refunded', 'refund_failure' => null]]]);
});

it('gives back the gift card\'s part, and leaves the gateway\'s part owed for staff', function (): void {
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
    useFakeGateway();
    connectFakeGateway('live');
    FakeGiftCardPaymentMethod::$balance = 5000;
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY], 'payment_method' => 'gateway:'.FakePaymentGateway::KEY]);
    $reference = (string) placeCheckout($checkout['id'], $checkout['version'], token: $token, returnUrl: 'https://shop.example.com/thanks')->assertCreated()->json('data.payment.reference');
    $payment = $this->store->run(static fn (): Payment => Payment::query()->sole());
    gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount);
    $this->getJson(storeUrl('cart-store', "/api/v1/checkout/payments/{$reference}"))->assertOk()->assertJsonPath('data.status', 'succeeded');
    expect(refundState()['payment_status'])->toBe('paid');

    cancelAsStaff();

    // Core's gateway hold took nothing of its own (its money is refunded through Payments): it is never asked.
    expect(refundState())->toBe(['status' => 'cancelled', 'payment_status' => 'partially_refunded', 'refund_due' => true, 'holds' => [
        FakeGiftCardPaymentMethod::KEY => ['status' => 'refunded', 'refund_failure' => null],
        'gateway:'.FakePaymentGateway::KEY => ['status' => 'committed', 'refund_failure' => null],
    ]])
        ->and(FakeGiftCardPaymentMethod::$steps[2])->toEndWith(':5000')
        ->and($this->store->run(static fn (): bool => Order::query()->sole()->is_test))->toBeFalse();
});

it('gives back a test order\'s gift card part the same way', function (): void {
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
    useFakeGateway();
    connectFakeGateway('test');
    FakeGiftCardPaymentMethod::$balance = 5000;
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY], 'payment_method' => 'gateway:'.FakePaymentGateway::KEY]);
    $reference = (string) placeCheckout($checkout['id'], $checkout['version'], token: $token, returnUrl: 'https://shop.example.com/thanks')->assertCreated()->assertJsonPath('data.order.is_test', true)->json('data.payment.reference');
    $payment = $this->store->run(static fn (): Payment => Payment::query()->sole());
    gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount);
    $this->getJson(storeUrl('cart-store', "/api/v1/checkout/payments/{$reference}"))->assertOk();

    cancelAsStaff();

    expect(refundState()['payment_status'])->toBe('partially_refunded')
        ->and(refundState()['holds'][FakeGiftCardPaymentMethod::KEY]['status'])->toBe('refunded');
});

it('records a give-back that failed, keeps the order owing it, and gives it back when the hourly sweep tries again', function (): void {
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
    giftCardPaidOrder();
    $failures = [];
    Event::listen(CommittedPaymentRefundFailed::class, static function (CommittedPaymentRefundFailed $event) use (&$failures): void {
        $failures[] = $event->hold->refund_failure;
    });
    $logged = [];
    Log::listen(static function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message->message;
    });
    FakeGiftCardPaymentMethod::$refundFailure = new RuntimeException('The gift card service is down.');

    cancelAsStaff();

    expect(refundState())->toBe(['status' => 'cancelled', 'payment_status' => 'paid', 'refund_due' => true, 'holds' => [FakeGiftCardPaymentMethod::KEY => ['status' => 'committed', 'refund_failure' => 'payment_method_refund_failed']]])
        ->and($this->store->run(static fn (): bool => CheckoutPaymentHold::query()->sole()->refund_failed_at !== null))->toBeTrue()
        ->and($failures)->toBe(['payment_method_refund_failed'])
        ->and(implode("\n", $logged))->toContain('could not give back what it took for a cancelled order');

    // Refusing for a reason of its own: that reason is kept.
    FakeGiftCardPaymentMethod::$refundFailure = new PaymentGatewayUnreachableException;
    $this->artisan('payments:check-pending')->assertSuccessful();
    expect(refundState()['holds'][FakeGiftCardPaymentMethod::KEY]['refund_failure'])->toBe('payment_gateway_unreachable')
        ->and($failures)->toBe(['payment_method_refund_failed', 'payment_gateway_unreachable']);

    // The card service is back: the next sweep gives it back, and the order owes nothing more.
    FakeGiftCardPaymentMethod::$refundFailure = null;
    $this->artisan('payments:check-pending')->assertSuccessful();
    expect(refundState())->toBe(['status' => 'cancelled', 'payment_status' => 'refunded', 'refund_due' => false, 'holds' => [FakeGiftCardPaymentMethod::KEY => ['status' => 'refunded', 'refund_failure' => null]]])
        ->and($this->store->run(static fn (): bool => CheckoutPaymentHold::query()->sole()->refund_failed_at === null))->toBeTrue();
});

it('gives back once, however often it is asked', function (): void {
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
    giftCardPaidOrder();
    cancelAsStaff();

    expect($this->store->run(static fn (): int => app(PaymentHolds::class)->refund(Order::query()->sole())))->toBe(0);
    $this->artisan('payments:check-pending')->assertSuccessful();

    expect(array_values(array_filter(FakeGiftCardPaymentMethod::$steps, static fn (string $step): bool => str_starts_with($step, 'refund:'))))->toHaveCount(1)
        ->and(refundState()['payment_status'])->toBe('refunded');
});

it('asks a module whatever its state, short of Unavailable', function (string $state, bool $isAsked): void {
    $registry = new FeatureRegistry;
    $registry->register(new FeatureDefinition('gift_cards', FeatureKind::Module));
    app()->instance(FeatureRegistry::class, $registry);
    $storeId = (string) $this->store->getTenantKey();
    $inState = static function (string $state) use ($storeId): void {
        app()->instance(FeatureSource::class, (new FakeFeatureSource)->give($storeId, match ($state) {
            'enabled' => FakeFeatureSource::snapshot(entitled: ['gift_cards']),
            'locked' => FakeFeatureSource::snapshot(removed: ['gift_cards']),
            'disabled' => FakeFeatureSource::snapshot(entitled: ['gift_cards'], merchantDisabled: ['gift_cards']),
            'suspended' => FakeFeatureSource::snapshot(entitled: ['gift_cards'], isStoreSuspended: true),
            default => FakeFeatureSource::snapshot(),
        }));
        app(Features::class)->forget($storeId);
    };
    $inState('enabled');
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class, 'gift_cards');
    giftCardPaidOrder();

    $inState($state);
    cancelAsStaff();

    expect(refundState())->toBe($isAsked
        ? ['status' => 'cancelled', 'payment_status' => 'refunded', 'refund_due' => false, 'holds' => [FakeGiftCardPaymentMethod::KEY => ['status' => 'refunded', 'refund_failure' => null]]]
        : ['status' => 'cancelled', 'payment_status' => 'paid', 'refund_due' => true, 'holds' => [FakeGiftCardPaymentMethod::KEY => ['status' => 'committed', 'refund_failure' => 'payment_method_cannot_refund']]]);
})->with([
    'enabled' => ['enabled', true],
    'locked by a downgrade' => ['locked', true],
    'switched off by the merchant' => ['disabled', true],
    'the store suspended' => ['suspended', true],
    'unavailable' => ['unavailable', false],
]);

it('never asks for a hold an unpaid order had: that is released', function (): void {
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
    FakeGiftCardPaymentMethod::$balance = 5000;
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY]]);
    placeCheckout($checkout['id'], $checkout['version'], token: $token)->assertCreated();

    cancelAsStaff();

    expect(array_map(static fn (string $step): string => explode(':', $step)[0], FakeGiftCardPaymentMethod::$steps))->toBe(['hold', 'release'])
        ->and(refundState())->toMatchArray(['payment_status' => 'unpaid', 'refund_due' => false]);
});
