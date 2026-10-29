<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Actions\CancelExpiredOrders;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\FindCashToCollect;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Checkout\FakeGiftCardPaymentMethod;
use Tests\Fixtures\Checkout\FakeWalletPaymentMethod;
use Tests\Fixtures\Payments\FakePaymentGateway;
use Tests\Support\TruncatesCentralDatabase;

/*
 * One order paid by two modules' payment methods (a gift card, then a
 * wallet) and core's method for the rest, through every step of its holds:
 * each module holds its part at placement, both commit once the order is
 * paid, both are released when it expires or is cancelled unpaid, and both
 * give back what they took when it is cancelled after it was paid, each on
 * its own, so one failing never keeps the other's money.
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
    FakeWalletPaymentMethod::reset();
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
    app(CheckoutExtensions::class)->addPaymentMethod(FakeWalletPaymentMethod::class);
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('splits an order between a gift card, a wallet and a gateway, and commits both modules once the gateway is paid', function (): void {
    useFakeGateway();
    connectFakeGateway('live');
    $order = twoModulesOrder('gateway:'.FakePaymentGateway::KEY);

    // Each module held its part in the order chosen; the gateway is asked only for the rest.
    expect(paymentHolds())->toBe([FakeGiftCardPaymentMethod::KEY => ['held', 5000], FakeWalletPaymentMethod::KEY => ['held', 3000], 'gateway:'.FakePaymentGateway::KEY => ['held', 0]])
        ->and($this->store->run(static fn (): int => Payment::query()->sole()->amount->minorAmount()))->toBe($order->total->minorAmount() - 8000)
        ->and([FakeGiftCardPaymentMethod::$steps, FakeWalletPaymentMethod::$steps])->toBe([['hold:5000'], ['hold:3000']]);

    payAtFakeGateway();

    expect(refundState()['payment_status'])->toBe('paid')
        ->and(paymentHolds())->toBe([FakeGiftCardPaymentMethod::KEY => ['committed', 5000], FakeWalletPaymentMethod::KEY => ['committed', 3000], 'gateway:'.FakePaymentGateway::KEY => ['committed', 0]])
        ->and([FakeGiftCardPaymentMethod::$steps, FakeWalletPaymentMethod::$steps])->toBe([['hold:5000', 'commit:gc_'.$order->public_id], ['hold:3000', 'commit:wl_'.$order->public_id]]);
});

it('holds nothing for a module left with nothing to cover, and doesn\'t list it as chosen', function (int $giftCardBalance, int $walletBalance, ?string $paymentMethod, array $chosen): void {
    FakeGiftCardPaymentMethod::reset(balance: $giftCardBalance);
    FakeWalletPaymentMethod::reset(balance: $walletBalance);
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY, FakeWalletPaymentMethod::KEY], 'payment_method' => $paymentMethod]);

    expect(checkoutRequest('GET', token: $token)->json('data.module_payment_methods'))->toBe($chosen);

    placeCheckout($checkout['id'], $checkout['version'], token: $token)->assertCreated();

    expect(array_keys(paymentHolds()))->toBe([...$chosen, ...($paymentMethod === null ? [] : [$paymentMethod])])
        ->and(FakeWalletPaymentMethod::$steps)->toBe(in_array(FakeWalletPaymentMethod::KEY, $chosen, true) ? ['hold:'.$walletBalance] : []);
})->with([
    // The gift card covers it all: nothing is left for the wallet, nor for a core method.
    'covered before it' => [10_000_000, 3000, null, [FakeGiftCardPaymentMethod::KEY]],
    // An empty wallet has nothing to give.
    'nothing to give' => [5000, 0, 'bank_transfer', [FakeGiftCardPaymentMethod::KEY]],
    // Both still have something to cover.
    'both cover a part' => [5000, 3000, 'bank_transfer', [FakeGiftCardPaymentMethod::KEY, FakeWalletPaymentMethod::KEY]],
]);

it('starts no core payment when modules cover the whole order, whatever core method was chosen, and the order is paid at once', function (string $coreMethod): void {
    if (str_starts_with($coreMethod, 'gateway:')) {
        useFakeGateway();
        connectFakeGateway('live');
    }

    offerManualMethod(PaymentMethod::CashOnDelivery);
    FakeGiftCardPaymentMethod::reset(balance: 10_000_000);
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY], 'payment_method' => $coreMethod]);

    placeCheckout($checkout['id'], $checkout['version'], token: $token, returnUrl: 'https://shop.example.com/thanks')->assertCreated()
        ->assertJsonPath('data.order.payment_status', 'paid')
        ->assertJsonPath('data.payment_method', null)
        ->assertJsonPath('data.payment', null);

    $order = $this->store->run(static fn (): Order => Order::query()->sole());

    expect($this->store->run(static fn (): int => Payment::query()->count()))->toBe(0)
        ->and(paymentHolds())->toBe([FakeGiftCardPaymentMethod::KEY => ['committed', $order->total->minorAmount()]])
        ->and(FakeGiftCardPaymentMethod::$steps)->toBe(['hold:'.$order->total->minorAmount(), "commit:gc_{$order->public_id}"])
        // Nothing to collect in cash.
        ->and($order->pay_on_delivery)->toBeFalse()
        ->and($this->store->run(static fn (): ?Money => app(FindCashToCollect::class)->handle($order)))->toBeNull();

    // Placing it again under another key finds the same order, still with no core method.
    placeCheckout($checkout['id'], $checkout['version'], token: $token, key: 'place-order-0002', returnUrl: 'https://shop.example.com/thanks')->assertOk()
        ->assertJsonPath('data.order.id', $order->public_id)
        ->assertJsonPath('data.payment_method', null);

    // Days later, the hourly sweep finds no cash overdue and starts nothing.
    $this->travel(5)->days();
    $this->artisan('payments:check-pending')->assertSuccessful();
    expect($this->store->run(static fn (): int => Payment::query()->count()))->toBe(0);
})->with([
    'a card through a gateway' => ['gateway:'.FakePaymentGateway::KEY],
    'a bank transfer' => ['bank_transfer'],
    'cash on delivery' => ['cash_on_delivery'],
]);

it('releases both modules\' holds when the order expires unpaid', function (): void {
    $order = twoModulesOrder();

    $this->travel(30)->days();
    expect($this->store->run(static fn (): int => app(CancelExpiredOrders::class)->handle(10)))->toBe(1);

    expect(paymentHolds())->toBe([FakeGiftCardPaymentMethod::KEY => ['released', 5000], FakeWalletPaymentMethod::KEY => ['released', 3000], 'bank_transfer' => ['released', 0]])
        ->and([FakeGiftCardPaymentMethod::$steps, FakeWalletPaymentMethod::$steps])->toBe([['hold:5000', 'release:gc_'.$order->public_id], ['hold:3000', 'release:wl_'.$order->public_id]]);
});

it('releases both modules\' holds when the order is cancelled before it is paid', function (): void {
    $order = twoModulesOrder();

    cancelAsStaff();

    expect(paymentHolds())->toBe([FakeGiftCardPaymentMethod::KEY => ['released', 5000], FakeWalletPaymentMethod::KEY => ['released', 3000], 'bank_transfer' => ['released', 0]])
        ->and([FakeGiftCardPaymentMethod::$steps, FakeWalletPaymentMethod::$steps])->toBe([['hold:5000', 'release:gc_'.$order->public_id], ['hold:3000', 'release:wl_'.$order->public_id]]);
});

it('gives back both modules\' parts when a paid order is cancelled, and leaves the gateway\'s part owed for staff', function (): void {
    useFakeGateway();
    connectFakeGateway('live');
    $order = twoModulesOrder('gateway:'.FakePaymentGateway::KEY);
    payAtFakeGateway();

    cancelAsStaff();

    expect(refundState())->toBe(['status' => 'cancelled', 'payment_status' => 'partially_refunded', 'refund_due' => true, 'holds' => [
        FakeGiftCardPaymentMethod::KEY => ['status' => 'refunded', 'refund_failure' => null],
        FakeWalletPaymentMethod::KEY => ['status' => 'refunded', 'refund_failure' => null],
        'gateway:'.FakePaymentGateway::KEY => ['status' => 'committed', 'refund_failure' => null],
    ]])
        ->and(FakeGiftCardPaymentMethod::$steps[2])->toBe("refund:gc_{$order->public_id}:5000")
        ->and(FakeWalletPaymentMethod::$steps[2])->toBe("refund:wl_{$order->public_id}:3000");
});

it('gives back one module\'s part while the other can\'t, and the other\'s once the hourly sweep tries again', function (): void {
    useFakeGateway();
    connectFakeGateway('live');
    $order = twoModulesOrder('gateway:'.FakePaymentGateway::KEY);
    payAtFakeGateway();
    // The gift card is asked first, and can't give back.
    FakeGiftCardPaymentMethod::$refundFailure = new RuntimeException('The gift card service is down.');

    cancelAsStaff();

    expect(refundState()['holds'])->toBe([
        FakeGiftCardPaymentMethod::KEY => ['status' => 'committed', 'refund_failure' => 'payment_method_refund_failed'],
        FakeWalletPaymentMethod::KEY => ['status' => 'refunded', 'refund_failure' => null],
        'gateway:'.FakePaymentGateway::KEY => ['status' => 'committed', 'refund_failure' => null],
    ])->and(FakeWalletPaymentMethod::$steps[2])->toBe("refund:wl_{$order->public_id}:3000");

    FakeGiftCardPaymentMethod::$refundFailure = null;
    $this->artisan('payments:check-pending')->assertSuccessful();

    expect(refundState()['holds'][FakeGiftCardPaymentMethod::KEY])->toBe(['status' => 'refunded', 'refund_failure' => null])
        ->and(FakeGiftCardPaymentMethod::$steps[2])->toBe("refund:gc_{$order->public_id}:5000")
        // The wallet gave back once, and wasn't asked again.
        ->and(array_values(array_filter(FakeWalletPaymentMethod::$steps, static fn (string $step): bool => str_starts_with($step, 'refund:'))))->toHaveCount(1);
});
