<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Money\Money;
use App\Tenant\Checkout\Events\CommittedPaymentRefundFailed;
use App\Tenant\Checkout\Events\OrderPaymentsKeepFailing;
use App\Tenant\Checkout\Events\PaymentHoldKeepsFailing;
use App\Tenant\Delivery\Events\DeliveryFailed;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Inventory\Events\StockRunningLow;
use App\Tenant\Inventory\Events\VariantSoldOut;
use App\Tenant\Messaging\Enums\StaffAlertKind;
use App\Tenant\Messaging\Listeners\AlertStaffWhenAttentionIsNeeded;
use App\Tenant\Messaging\Services\StaffAlertFromEvent;
use App\Tenant\Orders\Events\OrderPaidAfterCancellation;
use App\Tenant\Orders\Events\OrderPlaced;
use App\Tenant\Orders\Events\OrderRefundedBeyondPaid;
use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Payments\Events\CashCollectionOverdue;
use App\Tenant\Payments\Events\PaymentDisputed;
use App\Tenant\Payments\Events\PaymentGatewayNeedsReconnecting;
use App\Tenant\Payments\Events\PaymentNeedsReview;
use App\Tenant\Payments\Events\PaymentOverpaid;
use App\Tenant\Payments\Events\PaymentUnresolved;
use App\Tenant\Payments\Events\RefundFailed;
use App\Tenant\Payments\Events\RefundNeedsReview;
use App\Tenant\Payments\Events\RefundStillPending;
use App\Tenant\Payments\Events\RefundSucceededAfterFailing;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Returns\Events\ExchangeSettlementRefused;
use App\Tenant\Returns\Events\ReturnRequested;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnExchange;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
 * Step 20a: which events raise a staff alert, of which kind, about what,
 * and for whom. Every event on the handover's Step 20 list raises one, plus
 * a new order, a return a customer asked for, and an exchange whose
 * settlement was refused. An alert holds public IDs, order numbers, amounts
 * and SKUs, never personal data.
 */

beforeEach(function (): void {
    seedWorld();
    app()->instance(FeatureSource::class, new FakeFeatureSource);
    messagingStore();
});

afterEach(function (): void {
    deleteAllStores();
});

it('listens for every event that raises a staff alert', function (): void {
    $listened = array_values(array_filter(StaffAlertFromEvent::EVENTS, static fn (string $event): bool => in_array(AlertStaffWhenAttentionIsNeeded::class, array_map(
        static fn (mixed $listener): string => is_string($listener) ? $listener : '',
        app('events')->getRawListeners()[$event] ?? [],
    ), true)));

    expect($listened)->toBe(StaffAlertFromEvent::EVENTS)
        ->and(count(StaffAlertFromEvent::EVENTS))->toBe(count(StaffAlertKind::cases()));
});

it('raises the right alert, about the right thing, for the holders of the right permission', function (Closure $event, StaffAlertKind $kind, string $permission, string $subjectType, string $subjectId, array $details): void {
    $alert = $this->store->run(static fn () => app(StaffAlertFromEvent::class)->for($event()));

    expect([$alert?->kind, $alert?->kind->permission(), $alert?->subjectType, $alert?->subjectId, $alert?->details])
        ->toBe([$kind, $permission, $subjectType, $subjectId, $details]);
})->with([
    'a new order' => [fn () => new OrderPlaced(alertOrder()), StaffAlertKind::NewOrder, 'orders.fulfil', 'order', 'order-1', ['order_number' => 1042, 'amount' => 250000, 'currency' => 'NGN', 'is_test' => false]],
    'a payment to review' => [fn () => new PaymentNeedsReview(alertPayment()), StaffAlertKind::PaymentNeedsReview, 'payments.confirm', 'payment', 'payment-1', ['order_number' => 1042, 'amount' => 250000, 'currency' => 'NGN']],
    'an unresolved payment' => [fn () => new PaymentUnresolved(alertPayment()), StaffAlertKind::PaymentUnresolved, 'payments.confirm', 'payment', 'payment-1', ['order_number' => 1042, 'amount' => 250000, 'currency' => 'NGN']],
    'an overpayment, with the excess' => [fn () => new PaymentOverpaid(alertPayment(), Money::ofMinor(5000, 'NGN')), StaffAlertKind::PaymentOverpaid, 'payments.refund', 'payment', 'payment-1', ['order_number' => 1042, 'amount' => 5000, 'currency' => 'NGN']],
    'a dispute about a payment' => [fn () => new PaymentDisputed('paystack', alertPayment()), StaffAlertKind::PaymentDisputed, 'payments.refund', 'payment', 'payment-1', ['order_number' => 1042, 'amount' => 250000, 'currency' => 'NGN', 'gateway' => 'paystack']],
    'a dispute about a payment the store doesn\'t know' => [fn () => new PaymentDisputed('paystack', null), StaffAlertKind::PaymentDisputed, 'payments.refund', 'gateway', 'paystack', ['gateway' => 'paystack']],
    'a gateway to reconnect' => [fn () => new PaymentGatewayNeedsReconnecting((new PaymentGatewayConnection)->forceFill(['public_id' => 'connection-1', 'gateway' => 'paystack'])), StaffAlertKind::PaymentGatewayNeedsReconnecting, 'payments.manage_gateways', 'payment_gateway_connection', 'connection-1', ['gateway' => 'paystack']],
    'a cancelled order paid' => [fn () => new OrderPaidAfterCancellation(alertOrder()), StaffAlertKind::OrderPaidAfterCancellation, 'payments.refund', 'order', 'order-1', ['order_number' => 1042]],
    'an order refunded beyond what was paid' => [fn () => new OrderRefundedBeyondPaid(alertOrder()), StaffAlertKind::OrderRefundedBeyondPaid, 'payments.refund', 'order', 'order-1', ['order_number' => 1042]],
    'a refund to review' => [fn () => new RefundNeedsReview(alertPaymentRefund()), StaffAlertKind::RefundNeedsReview, 'payments.refund', 'payment_refund', 'payment-refund-1', ['order_number' => 1042, 'amount' => 3000, 'currency' => 'NGN']],
    'a failed refund' => [fn () => new RefundFailed(alertPaymentRefund()), StaffAlertKind::RefundFailed, 'payments.refund', 'payment_refund', 'payment-refund-1', ['order_number' => 1042, 'amount' => 3000, 'currency' => 'NGN']],
    'a refund still pending' => [fn () => new RefundStillPending(alertPaymentRefund()), StaffAlertKind::RefundStillPending, 'payments.refund', 'payment_refund', 'payment-refund-1', ['order_number' => 1042, 'amount' => 3000, 'currency' => 'NGN']],
    'a failed refund that went through' => [fn () => new RefundSucceededAfterFailing(alertPaymentRefund()), StaffAlertKind::RefundSucceededAfterFailing, 'payments.refund', 'payment_refund', 'payment-refund-1', ['order_number' => 1042, 'amount' => 3000, 'currency' => 'NGN']],
    'a hold that keeps failing' => [fn () => new PaymentHoldKeepsFailing(alertHold()), StaffAlertKind::PaymentHoldKeepsFailing, 'payments.confirm', 'checkout_payment_hold', 'hold-1', ['order_number' => 1042, 'method' => 'gift_card', 'amount' => 1000, 'currency' => 'NGN']],
    'payments that keep failing to be recorded' => [fn () => new OrderPaymentsKeepFailing(alertOrder()), StaffAlertKind::OrderPaymentsKeepFailing, 'payments.confirm', 'order', 'order-1', ['order_number' => 1042]],
    'a committed payment that can\'t be given back' => [fn () => new CommittedPaymentRefundFailed(alertHold()), StaffAlertKind::CommittedPaymentRefundFailed, 'payments.refund', 'checkout_payment_hold', 'hold-1', ['order_number' => 1042, 'method' => 'gift_card', 'amount' => 1000, 'currency' => 'NGN']],
    'an exchange settlement refused' => [fn () => new ExchangeSettlementRefused((new ReturnExchange)->forceFill(['public_id' => 'exchange-1'])->setRelation('order', alertOrder()), 'payment_method_cannot_refund'), StaffAlertKind::ExchangeSettlementRefused, 'returns.exchange', 'return_exchange', 'exchange-1', ['order_number' => 1042, 'reason' => 'payment_method_cannot_refund']],
    'cash not recorded' => [fn () => new CashCollectionOverdue(alertPayment()), StaffAlertKind::CashCollectionOverdue, 'delivery.cash.manage', 'payment', 'payment-1', ['order_number' => 1042, 'amount' => 250000, 'currency' => 'NGN']],
    'a failed delivery' => [fn () => new DeliveryFailed((new Delivery)->forceFill(['public_id' => 'delivery-1'])->setRelation('shipment', (new Shipment)->setRelation('order', alertOrder()))), StaffAlertKind::DeliveryFailed, 'delivery.manage', 'delivery', 'delivery-1', ['order_number' => 1042]],
    'a return a customer asked for' => [fn () => new ReturnRequested((new OrderReturn)->forceFill(['public_id' => 'return-1', 'requested_by_type' => 'customer'])->setRelation('order', alertOrder())), StaffAlertKind::ReturnRequested, 'returns.manage', 'return', 'return-1', ['order_number' => 1042]],
]);

it('raises stock alerts with the units left, the SKU and the product\'s name in the store\'s default language', function (): void {
    [$variant, $low, $soldOut] = $this->store->run(static function (): array {
        useLanguages(test()->store, 'fr', ['fr', 'en']);
        $variant = cartVariant(5000, stock: 3);
        $variant->product->setTranslations('name', ['en' => 'Blue shirt', 'fr' => 'Chemise bleue'])->save();
        $variant->forceFill(['sku' => 'SHIRT-BLUE'])->save();

        return [$variant, app(StaffAlertFromEvent::class)->for(new StockRunningLow($variant->id, 1, 5, 2)), app(StaffAlertFromEvent::class)->for(new VariantSoldOut($variant->id, 1, 2, -1))];
    });

    expect([$low?->kind, $low?->subjectType, $low?->subjectId, $low?->details])->toBe([StaffAlertKind::StockRunningLow, 'product_variant', $variant->public_id, ['product' => 'Chemise bleue', 'sku' => 'SHIRT-BLUE', 'available' => 2]])
        ->and([$soldOut?->kind, $soldOut?->details])->toBe([StaffAlertKind::VariantSoldOut, ['product' => 'Chemise bleue', 'sku' => 'SHIRT-BLUE', 'available' => 0]]);
});

it('raises nothing for a return staff started themselves, or a variant that no longer exists', function (): void {
    $alerts = $this->store->run(static fn (): array => [
        app(StaffAlertFromEvent::class)->for(new ReturnRequested((new OrderReturn)->forceFill(['public_id' => 'return-1', 'requested_by_type' => 'staff_member'])->setRelation('order', alertOrder()))),
        app(StaffAlertFromEvent::class)->for(new StockRunningLow(999999, 1, 5, 2)),
    ]);

    expect($alerts)->toBe([null, null]);
});

it('keeps alerts that may come again apart by day, and the rest once per thing', function (): void {
    expect(array_map(static fn (StaffAlertKind $kind): string => $kind->value, array_values(array_filter(StaffAlertKind::cases(), static fn (StaffAlertKind $kind): bool => $kind->repeatsDaily()))))
        ->toBe(['payment_gateway_needs_reconnecting', 'committed_payment_refund_failed', 'stock_running_low', 'variant_sold_out'])
        ->and(array_map(static fn (StaffAlertKind $kind): string => $kind->value, StaffAlertKind::cappedKinds()))
        ->toBe(['new_order', 'delivery_failed', 'stock_running_low', 'variant_sold_out', 'return_requested']);
});
