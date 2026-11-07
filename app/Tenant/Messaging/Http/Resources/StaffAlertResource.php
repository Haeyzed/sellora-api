<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Http\Resources;

use App\Tenant\Messaging\Models\StaffAlert;
use App\Tenant\Messaging\Services\StaffAlertTexts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A staff alert as the dashboard shows it, written in the request's language.
 *
 * @property StaffAlert $resource
 */
final class StaffAlertResource extends JsonResource
{
    public function __construct(StaffAlert $alert)
    {
        parent::__construct($alert);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $alert = $this->resource;

        return [
            'id' => $alert->public_id,
            /** What it is about: one of new_order, payment_needs_review, payment_unresolved, payment_overpaid, payment_disputed, payment_gateway_needs_reconnecting, order_paid_after_cancellation, order_refunded_beyond_paid, refund_needs_review, refund_failed, refund_still_pending, refund_succeeded_after_failing, payment_hold_keeps_failing, order_payments_keep_failing, committed_payment_refund_failed, exchange_settlement_refused, cash_collection_overdue, delivery_failed, stock_running_low, variant_sold_out, return_requested. */
            'kind' => $alert->kind->value,
            /** Whether it is about money: always emailed. */
            'is_money' => $alert->kind->isMoney(),
            'title' => $this->title($alert),
            'message' => $this->message($alert),
            /** What it is about, to open it: its type (order, payment, payment_refund, checkout_payment_hold, return, return_exchange, delivery, product_variant, payment_gateway_connection, or gateway) and its ID (a gateway's key for "gateway"). */
            'subject' => ['type' => $alert->subject_type, 'id' => $alert->subject_id],
            /** What its text shows: order_number, amount (minor units) and currency, gateway, method, reason, product, sku, available, is_test, as the kind has them. */
            'details' => $alert->details,
            /** When it was emailed; null when it was not (new-order emails switched off, or held back by the hourly cap). */
            'emailed_at' => $alert->emailed_at?->toIso8601String(),
            'created_at' => $alert->created_at->toIso8601String(),
        ];
    }

    /**
     * Its title in the request's language.
     */
    private function title(StaffAlert $alert): string
    {
        return app(StaffAlertTexts::class)->title($alert);
    }

    /**
     * What happened, in the request's language.
     */
    private function message(StaffAlert $alert): string
    {
        return app(StaffAlertTexts::class)->message($alert);
    }
}
