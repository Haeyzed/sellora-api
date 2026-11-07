<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Enums;

use App\Tenant\Delivery\Enums\DeliveryPermission;
use App\Tenant\Inventory\Enums\InventoryPermission;
use App\Tenant\Orders\Enums\OrderPermission;
use App\Tenant\Payments\Enums\PaymentPermission;
use App\Tenant\Returns\Enums\ReturnPermission;

/**
 * What a staff alert is about. Each kind goes to the active staff holding its permission (and the owner), repeats at most once per thing it is about (or once a day), and is either a money alert, always emailed, or one the hourly email cap applies to.
 */
enum StaffAlertKind: string
{
    case NewOrder = 'new_order';
    case PaymentNeedsReview = 'payment_needs_review';
    case PaymentUnresolved = 'payment_unresolved';
    case PaymentOverpaid = 'payment_overpaid';
    case PaymentDisputed = 'payment_disputed';
    case PaymentGatewayNeedsReconnecting = 'payment_gateway_needs_reconnecting';
    case OrderPaidAfterCancellation = 'order_paid_after_cancellation';
    case OrderRefundedBeyondPaid = 'order_refunded_beyond_paid';
    case RefundNeedsReview = 'refund_needs_review';
    case RefundFailed = 'refund_failed';
    case RefundStillPending = 'refund_still_pending';
    case RefundSucceededAfterFailing = 'refund_succeeded_after_failing';
    case PaymentHoldKeepsFailing = 'payment_hold_keeps_failing';
    case OrderPaymentsKeepFailing = 'order_payments_keep_failing';
    case CommittedPaymentRefundFailed = 'committed_payment_refund_failed';
    case ExchangeSettlementRefused = 'exchange_settlement_refused';
    case CashCollectionOverdue = 'cash_collection_overdue';
    case DeliveryFailed = 'delivery_failed';
    case StockRunningLow = 'stock_running_low';
    case VariantSoldOut = 'variant_sold_out';
    case ReturnRequested = 'return_requested';

    /**
     * The permission whose holders (and the owner) receive it and see it in their alert list.
     */
    public function permission(): string
    {
        return match ($this) {
            self::NewOrder => OrderPermission::OrdersFulfil->value,
            self::PaymentNeedsReview, self::PaymentUnresolved, self::PaymentHoldKeepsFailing, self::OrderPaymentsKeepFailing => PaymentPermission::PaymentsConfirm->value,
            self::PaymentOverpaid, self::PaymentDisputed, self::OrderPaidAfterCancellation, self::OrderRefundedBeyondPaid,
            self::RefundNeedsReview, self::RefundFailed, self::RefundStillPending, self::RefundSucceededAfterFailing,
            self::CommittedPaymentRefundFailed => PaymentPermission::PaymentsRefund->value,
            self::PaymentGatewayNeedsReconnecting => PaymentPermission::PaymentsManageGateways->value,
            self::ExchangeSettlementRefused => ReturnPermission::ReturnsExchange->value,
            self::CashCollectionOverdue => DeliveryPermission::DeliveryCashManage->value,
            self::DeliveryFailed => DeliveryPermission::DeliveryManage->value,
            self::StockRunningLow, self::VariantSoldOut => InventoryPermission::InventoryAdjust->value,
            self::ReturnRequested => ReturnPermission::ReturnsManage->value,
        };
    }

    /**
     * Whether it is about money (payments, refunds, holds, disputes, overpayments, cash collection, exchange settlement): always emailed, never held back by the hourly cap, and never to be muted.
     */
    public function isMoney(): bool
    {
        return ! in_array($this, [self::NewOrder, self::DeliveryFailed, self::StockRunningLow, self::VariantSoldOut, self::ReturnRequested], true);
    }

    /**
     * Whether it may come again the next day for the same thing (what is retried hourly or can happen again); otherwise it comes once per thing.
     */
    public function repeatsDaily(): bool
    {
        return in_array($this, [self::PaymentGatewayNeedsReconnecting, self::CommittedPaymentRefundFailed, self::StockRunningLow, self::VariantSoldOut], true);
    }

    /**
     * The kinds the hourly email cap applies to.
     *
     * @return list<self>
     */
    public static function cappedKinds(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $kind): bool => ! $kind->isMoney()));
    }
}
