<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Services;

use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Checkout\Events\CommittedPaymentRefundFailed;
use App\Tenant\Checkout\Events\OrderPaymentsKeepFailing;
use App\Tenant\Checkout\Events\PaymentHoldKeepsFailing;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Delivery\Events\DeliveryFailed;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Events\StockRunningLow;
use App\Tenant\Inventory\Events\VariantSoldOut;
use App\Tenant\Messaging\Data\StaffAlertData;
use App\Tenant\Messaging\Enums\StaffAlertKind;
use App\Tenant\Orders\Events\OrderPaidAfterCancellation;
use App\Tenant\Orders\Events\OrderPlaced;
use App\Tenant\Orders\Events\OrderRefundedBeyondPaid;
use App\Tenant\Orders\Models\Order;
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
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Returns\Events\ExchangeSettlementRefused;
use App\Tenant\Returns\Events\ReturnRequested;
use App\Tenant\Settings\Actions\FindStoreSettings;

/**
 * Turns a domain event into the staff alert it raises: its kind, what it is about, and what its text shows (public IDs, order numbers, amounts, SKUs; never personal data).
 */
final readonly class StaffAlertFromEvent
{
    /**
     * Every event that raises a staff alert. AlertStaffWhenAttentionIsNeeded listens to each.
     *
     * @var list<class-string>
     */
    public const array EVENTS = [
        OrderPlaced::class,
        PaymentNeedsReview::class,
        PaymentUnresolved::class,
        PaymentOverpaid::class,
        PaymentDisputed::class,
        PaymentGatewayNeedsReconnecting::class,
        OrderPaidAfterCancellation::class,
        OrderRefundedBeyondPaid::class,
        RefundNeedsReview::class,
        RefundFailed::class,
        RefundStillPending::class,
        RefundSucceededAfterFailing::class,
        PaymentHoldKeepsFailing::class,
        OrderPaymentsKeepFailing::class,
        CommittedPaymentRefundFailed::class,
        ExchangeSettlementRefused::class,
        CashCollectionOverdue::class,
        DeliveryFailed::class,
        StockRunningLow::class,
        VariantSoldOut::class,
        ReturnRequested::class,
    ];

    public function __construct(private FindStoreSettings $findStoreSettings) {}

    /**
     * The alert the event raises; null when it raises none (an event this doesn't know, or a return staff started themselves).
     */
    public function for(object $event): ?StaffAlertData
    {
        return match (true) {
            $event instanceof OrderPlaced => $this->aboutOrder(StaffAlertKind::NewOrder, $event->order, ['amount' => $event->order->total->minorAmount(), 'currency' => $event->order->total->currency(), 'is_test' => $event->order->is_test]),
            $event instanceof PaymentNeedsReview => $this->aboutPayment(StaffAlertKind::PaymentNeedsReview, $event->payment),
            $event instanceof PaymentUnresolved => $this->aboutPayment(StaffAlertKind::PaymentUnresolved, $event->payment),
            $event instanceof PaymentOverpaid => $this->aboutPayment(StaffAlertKind::PaymentOverpaid, $event->payment, $event->excess),
            $event instanceof PaymentDisputed => $this->aboutDispute($event),
            $event instanceof PaymentGatewayNeedsReconnecting => new StaffAlertData(StaffAlertKind::PaymentGatewayNeedsReconnecting, 'payment_gateway_connection', $event->connection->public_id, ['gateway' => $event->connection->gateway]),
            $event instanceof OrderPaidAfterCancellation => $this->aboutOrder(StaffAlertKind::OrderPaidAfterCancellation, $event->order),
            $event instanceof OrderRefundedBeyondPaid => $this->aboutOrder(StaffAlertKind::OrderRefundedBeyondPaid, $event->order),
            $event instanceof OrderPaymentsKeepFailing => $this->aboutOrder(StaffAlertKind::OrderPaymentsKeepFailing, $event->order),
            $event instanceof RefundNeedsReview => $this->aboutRefund(StaffAlertKind::RefundNeedsReview, $event->paymentRefund),
            $event instanceof RefundFailed => $this->aboutRefund(StaffAlertKind::RefundFailed, $event->paymentRefund),
            $event instanceof RefundStillPending => $this->aboutRefund(StaffAlertKind::RefundStillPending, $event->paymentRefund),
            $event instanceof RefundSucceededAfterFailing => $this->aboutRefund(StaffAlertKind::RefundSucceededAfterFailing, $event->paymentRefund),
            $event instanceof PaymentHoldKeepsFailing => $this->aboutHold(StaffAlertKind::PaymentHoldKeepsFailing, $event->hold),
            $event instanceof CommittedPaymentRefundFailed => $this->aboutHold(StaffAlertKind::CommittedPaymentRefundFailed, $event->hold),
            $event instanceof ExchangeSettlementRefused => new StaffAlertData(StaffAlertKind::ExchangeSettlementRefused, 'return_exchange', $event->exchange->public_id, ['order_number' => $event->exchange->order->number, 'reason' => $event->reason]),
            $event instanceof CashCollectionOverdue => $this->aboutPayment(StaffAlertKind::CashCollectionOverdue, $event->payment),
            $event instanceof DeliveryFailed => new StaffAlertData(StaffAlertKind::DeliveryFailed, 'delivery', $event->delivery->public_id, ['order_number' => $event->delivery->shipment->order->number]),
            $event instanceof StockRunningLow => $this->aboutVariant(StaffAlertKind::StockRunningLow, $event->variantId, $event->availableAfter),
            $event instanceof VariantSoldOut => $this->aboutVariant(StaffAlertKind::VariantSoldOut, $event->variantId, $event->availableAfter),
            // Staff who start a return know of it; only one a customer asked for is news.
            $event instanceof ReturnRequested => $event->return->requested_by_type === (new StaffMember)->getMorphClass()
                ? null
                : new StaffAlertData(StaffAlertKind::ReturnRequested, 'return', $event->return->public_id, ['order_number' => $event->return->order->number]),
            default => null,
        };
    }

    /**
     * @param  array<string, int|string|bool|null>  $details
     */
    private function aboutOrder(StaffAlertKind $kind, Order $order, array $details = []): StaffAlertData
    {
        return new StaffAlertData($kind, 'order', $order->public_id, ['order_number' => $order->number, ...$details]);
    }

    private function aboutPayment(StaffAlertKind $kind, Payment $payment, ?Money $amount = null): StaffAlertData
    {
        $amount ??= $payment->amount;

        return new StaffAlertData($kind, 'payment', $payment->public_id, ['order_number' => $payment->order->number, 'amount' => $amount->minorAmount(), 'currency' => $amount->currency()]);
    }

    /**
     * A dispute about a payment the store knows, or (when the gateway named none it recognises) about the gateway, once a day.
     */
    private function aboutDispute(PaymentDisputed $event): StaffAlertData
    {
        if ($event->payment === null) {
            return new StaffAlertData(StaffAlertKind::PaymentDisputed, 'gateway', $event->gateway, ['gateway' => $event->gateway]);
        }

        return new StaffAlertData(StaffAlertKind::PaymentDisputed, 'payment', $event->payment->public_id, [
            'order_number' => $event->payment->order->number,
            'amount' => $event->payment->amount->minorAmount(),
            'currency' => $event->payment->amount->currency(),
            'gateway' => $event->gateway,
        ]);
    }

    private function aboutRefund(StaffAlertKind $kind, PaymentRefund $paymentRefund): StaffAlertData
    {
        return new StaffAlertData($kind, 'payment_refund', $paymentRefund->public_id, [
            'order_number' => $paymentRefund->refund->order->number,
            'amount' => $paymentRefund->amount->minorAmount(),
            'currency' => $paymentRefund->amount->currency(),
        ]);
    }

    private function aboutHold(StaffAlertKind $kind, CheckoutPaymentHold $hold): StaffAlertData
    {
        return new StaffAlertData($kind, 'checkout_payment_hold', $hold->public_id, [
            'order_number' => $hold->order->number,
            'method' => $hold->method,
            'amount' => $hold->amount->minorAmount(),
            'currency' => $hold->amount->currency(),
        ]);
    }

    /**
     * Staff see the units still available (it is their own stock); the product's name is in the store's default language.
     */
    private function aboutVariant(StaffAlertKind $kind, int $variantId, int $available): ?StaffAlertData
    {
        $variant = ProductVariant::withTrashed()->with(['product' => static fn ($product) => $product->withTrashed()])->find($variantId);

        if ($variant === null) {
            return null;
        }

        return new StaffAlertData($kind, 'product_variant', $variant->public_id, [
            'product' => (string) $variant->product->getTranslation('name', $this->findStoreSettings->handle()->default_locale),
            'sku' => $variant->sku,
            'available' => max(0, $available),
        ]);
    }
}
