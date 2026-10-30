<?php

declare(strict_types=1);

use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Checkout\Events\PaymentHoldKeepsFailing;
use App\Tenant\Checkout\Exceptions\OrderPaymentsNotRecordedException;
use App\Tenant\Checkout\PaymentMethods\GatewayPaymentMethods;
use App\Tenant\Checkout\PaymentMethods\ManualPaymentMethods;
use App\Tenant\Checkout\Services\PaymentHolds;
use App\Tenant\Checkout\UnsettledPaymentHoldSweep;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Actions\CancelExpiredOrders;
use App\Tenant\Orders\Events\OrderPaid;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\PaymentMethod;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Tests\Fixtures\Checkout\FakeGiftCardPaymentMethod;
use Tests\Fixtures\Checkout\FakeWalletPaymentMethod;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Committing and releasing what payment methods hold, each hold on its own:
 * a method that throws, or is no longer registered, never stops the others.
 * Its failure is recorded on its hold and logged, never swallowed; the hourly
 * payments sweep tries again until it settles (committed for a paid order,
 * released for an expired or cancelled one), alerting staff once when it has
 * failed checkout.hold_settle_alert_after tries in a row. A retry that
 * settles it clears the failure and records that it recovered.
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
});

afterEach(function (): void {
    deleteAllStores();
});

it('commits the other module when one throws, records why, and commits it when the hourly sweep tries again', function (): void {
    $order = twoModulesOrder();
    FakeGiftCardPaymentMethod::$settleFailure = new RuntimeException('The gift card service is down.');
    $warnings = [];
    Event::listen(MessageLogged::class, static function (MessageLogged $log) use (&$warnings): void {
        if ($log->level === 'warning') {
            $warnings[] = $log->context;
        }
    });

    receiveTheRest();

    // Paid all the same: the wallet committed, and the gift card's failure is on its hold, logged.
    expect(refundState()['payment_status'])->toBe('paid')
        ->and(holdSettling(FakeGiftCardPaymentMethod::KEY))->toBe(['status' => 'held', 'failure' => 'payment_method_settle_failed', 'failures' => 1, 'alerted' => false, 'recovered' => false])
        ->and(holdSettling(FakeWalletPaymentMethod::KEY)['status'])->toBe('committed')
        ->and(FakeWalletPaymentMethod::$steps)->toBe(['hold:3000', "commit:wl_{$order->public_id}"])
        ->and($warnings)->toBe([['order' => $order->public_id, 'method' => FakeGiftCardPaymentMethod::KEY, 'to' => 'committed', 'reason' => 'payment_method_settle_failed', 'tries_failed' => 1]]);

    FakeGiftCardPaymentMethod::$settleFailure = null;
    $this->artisan('payments:check-pending')->assertSuccessful();

    expect(holdSettling(FakeGiftCardPaymentMethod::KEY))->toBe(['status' => 'committed', 'failure' => null, 'failures' => 1, 'alerted' => false, 'recovered' => true])
        ->and(FakeGiftCardPaymentMethod::$steps)->toBe(['hold:5000', "commit:gc_{$order->public_id}"])
        ->and(FakeWalletPaymentMethod::$steps)->toBe(['hold:3000', "commit:wl_{$order->public_id}"]);

    // Settled: the sweep leaves it alone from now on.
    $this->artisan('payments:check-pending')->assertSuccessful();
    expect(FakeGiftCardPaymentMethod::$steps)->toHaveCount(2);
});

it('releases the other module when one throws as the order expires, and releases it when the hourly sweep tries again', function (): void {
    $order = twoModulesOrder();
    FakeGiftCardPaymentMethod::$settleFailure = new RuntimeException('The gift card service is down.');

    $this->travel(30)->days();
    expect($this->store->run(static fn (): int => app(CancelExpiredOrders::class)->handle(10)))->toBe(1);

    expect(holdSettling(FakeGiftCardPaymentMethod::KEY))->toMatchArray(['status' => 'held', 'failure' => 'payment_method_settle_failed', 'failures' => 1])
        ->and(holdSettling(FakeWalletPaymentMethod::KEY)['status'])->toBe('released');

    FakeGiftCardPaymentMethod::$settleFailure = null;
    $this->artisan('payments:check-pending')->assertSuccessful();

    expect(holdSettling(FakeGiftCardPaymentMethod::KEY))->toBe(['status' => 'released', 'failure' => null, 'failures' => 1, 'alerted' => false, 'recovered' => true])
        ->and(FakeGiftCardPaymentMethod::$steps)->toBe(['hold:5000', "release:gc_{$order->public_id}"]);
});

it('alerts staff once when a hold keeps failing, and keeps trying every hour after', function (): void {
    config(['checkout.hold_settle_alert_after' => 3]);
    Event::fake([PaymentHoldKeepsFailing::class]);
    twoModulesOrder();
    FakeGiftCardPaymentMethod::$settleFailure = new RuntimeException('The gift card service is down.');

    receiveTheRest();
    $this->artisan('payments:check-pending')->assertSuccessful();
    Event::assertNotDispatched(PaymentHoldKeepsFailing::class);

    // The third try in a row: staff are alerted.
    $this->artisan('payments:check-pending')->assertSuccessful();
    Event::assertDispatchedTimes(PaymentHoldKeepsFailing::class, 1);
    Event::assertDispatched(PaymentHoldKeepsFailing::class, static fn (PaymentHoldKeepsFailing $event): bool => $event->hold->method === FakeGiftCardPaymentMethod::KEY);

    // Still tried every hour, never alerted again.
    $this->artisan('payments:check-pending')->assertSuccessful();
    $this->artisan('payments:check-pending')->assertSuccessful();

    expect(holdSettling(FakeGiftCardPaymentMethod::KEY))->toBe(['status' => 'held', 'failure' => 'payment_method_settle_failed', 'failures' => 5, 'alerted' => true, 'recovered' => false]);
    Event::assertDispatchedTimes(PaymentHoldKeepsFailing::class, 1);

    FakeGiftCardPaymentMethod::$settleFailure = null;
    $this->artisan('payments:check-pending')->assertSuccessful();

    expect(holdSettling(FakeGiftCardPaymentMethod::KEY))->toBe(['status' => 'committed', 'failure' => null, 'failures' => 5, 'alerted' => true, 'recovered' => true]);
});

it('records a module that is no longer registered as a failure, and still settles the others', function (): void {
    twoModulesOrder();
    // The gift card module is gone; core's methods and the wallet remain.
    $extensions = new CheckoutExtensions(app());
    $extensions->addPaymentMethod(GatewayPaymentMethods::class);
    $extensions->addPaymentMethod(ManualPaymentMethods::class);
    $extensions->addPaymentMethod(FakeWalletPaymentMethod::class);
    app()->instance(CheckoutExtensions::class, $extensions);

    receiveTheRest();

    expect(refundState()['payment_status'])->toBe('paid')
        ->and(holdSettling(FakeGiftCardPaymentMethod::KEY))->toMatchArray(['status' => 'held', 'failure' => 'payment_method_not_registered', 'failures' => 1])
        ->and(holdSettling(FakeWalletPaymentMethod::KEY)['status'])->toBe('committed')
        ->and(holdSettling('bank_transfer')['status'])->toBe('committed');

    // Tried again hourly, recorded each time, never a crash.
    $this->artisan('payments:check-pending')->assertSuccessful();
    expect(holdSettling(FakeGiftCardPaymentMethod::KEY))->toMatchArray(['status' => 'held', 'failures' => 2]);
});

it('settles a hold once when the hourly sweep and the order\'s own commit race on it', function (): void {
    $order = twoModulesOrder();
    // Paid, but its holds not committed yet: the order's own commit is about to run.
    Event::fakeFor(static fn () => receiveTheRest(), [OrderPaid::class]);

    $this->store->run(function () use ($order): void {
        // The order's own commit, on a connection of its own, not yet committed.
        $commit = SecondConnection::open();
        $commit->act(static fn (): int => app(PaymentHolds::class)->commit(Order::query()->findOrFail($order->id)));

        // The sweep waits on the hold's row lock instead of committing it again.
        expect($commit->blockedStatement(static fn (): int => app(UnsettledPaymentHoldSweep::class)->settleUnsettledHolds()))->toContain('"checkout_payment_holds"')->toContain('for update');

        $commit->commit();

        expect(app(UnsettledPaymentHoldSweep::class)->settleUnsettledHolds())->toBe(0);
    });

    expect([FakeGiftCardPaymentMethod::$steps, FakeWalletPaymentMethod::$steps])->toBe([['hold:5000', "commit:gc_{$order->public_id}"], ['hold:3000', "commit:wl_{$order->public_id}"]])
        ->and(holdSettling(FakeGiftCardPaymentMethod::KEY))->toBe(['status' => 'committed', 'failure' => null, 'failures' => 0, 'alerted' => false, 'recovered' => false]);
});

it('makes a cancelled order unpaid again once the sweep releases the module hold that was all it had, owing nothing, its history kept', function (): void {
    // Paid by the gift card alone at placement, but its commit failed; staff cancel it while the gift card still can't be reached.
    $order = modulesOnlyOrderWhoseCommitFailed();
    cancelAsStaff($order);

    // Paid in full while open, so it was a sale, and invoiced.
    $invoiced = orderTaxDocuments($order);
    expect(orderAfterHolds($order))->toBe(['status' => 'cancelled', 'payment_status' => 'paid', 'paid' => true, 'refund_due' => true, 'holds' => [FakeGiftCardPaymentMethod::KEY => 'held']])
        ->and($invoiced['invoice'])->toMatchArray(['total' => $order->total->minorAmount()])
        ->and($invoiced['credit_notes'])->toBe([]);

    // Back again: the hourly sweep releases the hold. Nothing was ever taken, so nothing is owed.
    FakeGiftCardPaymentMethod::$settleFailure = null;
    $this->artisan('payments:check-pending')->assertSuccessful();

    expect(orderAfterHolds($order))->toBe(['status' => 'cancelled', 'payment_status' => 'unpaid', 'paid' => false, 'refund_due' => false, 'holds' => [FakeGiftCardPaymentMethod::KEY => 'released']])
        ->and(orderHistory($order))->toContain('paid', 'invoice_issued', 'cancelled', 'payment_status_changed:paid>unpaid', 'credit_note_issued')
        ->and(FakeGiftCardPaymentMethod::$steps)->toBe(['hold:'.$order->total->minorAmount(), "release:gc_{$order->public_id}"])
        // The sale is undone: the whole invoice is credited, line for line, with the store's first credit note number, and no refund behind it.
        ->and(orderTaxDocuments($order))->toBe(['invoice' => $invoiced['invoice'], 'credit_notes' => [[
            'number' => 'CN-'.now()->year.'-000001',
            'sequence_number' => 1,
            'reference' => $order->public_id,
            'refund' => null,
            'total' => $invoiced['invoice']['total'],
            'tax' => $invoiced['invoice']['tax'],
            'lines' => $invoiced['invoice']['lines'],
        ]]]);

    // Swept again: nothing more to do, and still one credit note.
    $this->artisan('payments:check-pending')->assertSuccessful();
    expect(orderTaxDocuments($order)['credit_notes'])->toHaveCount(1);
});

it('keeps sweeping when recording one order\'s payments fails after its hold is released, records it on that hold, and records them the next hour', function (): void {
    // Another order in the store, placed first so the sweep comes to it first, in the same state.
    $other = modulesOnlyOrderWhoseCommitFailed();
    cancelAsStaff($other);
    $order = modulesOnlyOrderWhoseCommitFailed();
    cancelAsStaff($order);
    // Recording the other order's payments hits a real database constraint while it fails.
    $breaks = true;
    Order::updating(static function (Order $saving) use (&$breaks, $other): void {
        if ($breaks && $saving->id === $other->id) {
            DB::table('orders')->where('id', $other->id)->update(['payment_status' => 'no_such_status']);
        }
    });
    Exceptions::fake();
    $warnings = [];
    Event::listen(MessageLogged::class, static function (MessageLogged $log) use (&$warnings): void {
        if ($log->level === 'warning') {
            $warnings[] = $log->message.' '.json_encode($log->context);
        }
    });

    FakeGiftCardPaymentMethod::$settleFailure = null;
    $this->artisan('payments:check-pending')->assertSuccessful();

    // This order finished all the same.
    expect(orderAfterHolds($order))->toBe(['status' => 'cancelled', 'payment_status' => 'unpaid', 'paid' => false, 'refund_due' => false, 'holds' => [FakeGiftCardPaymentMethod::KEY => 'released']])
        ->and(orderTaxDocuments($order)['credit_notes'])->toHaveCount(1)
        // The other's hold is released for good (the gift card isn't asked twice), and why its order couldn't be recorded is on it.
        ->and(orderAfterHolds($other))->toBe(['status' => 'cancelled', 'payment_status' => 'paid', 'paid' => true, 'refund_due' => true, 'holds' => [FakeGiftCardPaymentMethod::KEY => 'released']])
        ->and(orderUpdateAfterHolds($other))->toBe([FakeGiftCardPaymentMethod::KEY => ['failure' => 'order_payments_not_recorded', 'failures' => 1]])
        ->and(orderTaxDocuments($other)['credit_notes'])->toBe([])
        // Logged with public IDs only.
        ->and($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain($other->public_id)->toContain('"tries_failed":1')->not->toContain('ada@example.com');

    // The error tracker is told what broke, without the row, the SQL or the buyer's details.
    Exceptions::assertReported(static fn (OrderPaymentsNotRecordedException $exception): bool => $exception->order === $other->public_id
        && str_contains($exception->getMessage(), 'SQLSTATE[23514] on constraint orders_payment_status_check')
        && ! str_contains($exception->getMessage(), 'ada@example.com')
        && ! str_contains($exception->getMessage(), 'update "orders"')
        && $exception->getPrevious() === null);

    // An hour later it works: the other order is recorded, its invoice credited, the failure cleared, its count kept.
    $breaks = false;
    $this->artisan('payments:check-pending')->assertSuccessful();

    expect(orderAfterHolds($other))->toMatchArray(['payment_status' => 'unpaid', 'paid' => false, 'refund_due' => false])
        ->and(orderUpdateAfterHolds($other))->toBe([FakeGiftCardPaymentMethod::KEY => ['failure' => null, 'failures' => 1]])
        ->and(orderTaxDocuments($other)['credit_notes'])->toHaveCount(1)
        ->and(FakeGiftCardPaymentMethod::$steps)->toHaveCount(4);
});

it('keeps a cancelled order owing what a module really took, once the hold that was never taken is released', function (): void {
    // The wallet's ₦30 was taken and can't be given back yet; the gift card's part was never taken.
    $order = modulesOnlyOrderWhoseCommitFailed(withWallet: true);
    FakeWalletPaymentMethod::$refundFailure = new RuntimeException('The wallet service is down.');
    cancelAsStaff($order);

    FakeGiftCardPaymentMethod::$settleFailure = null;
    $this->artisan('payments:check-pending')->assertSuccessful();

    expect(orderAfterHolds($order))->toBe(['status' => 'cancelled', 'payment_status' => 'partially_paid', 'paid' => false, 'refund_due' => true, 'holds' => [FakeWalletPaymentMethod::KEY => 'committed', FakeGiftCardPaymentMethod::KEY => 'released']]);
});
