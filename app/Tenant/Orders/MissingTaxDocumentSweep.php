<?php

declare(strict_types=1);

namespace App\Tenant\Orders;

use App\Shared\Tenancy\Contracts\StoreTaxDocuments;
use App\Tenant\Orders\Actions\IssueInvoice;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\OrderPaymentStatus;
use App\Tenant\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The current store's part of the hourly sweep: invoices for sales paid in full that the queue didn't issue.
 */
final readonly class MissingTaxDocumentSweep implements StoreTaxDocuments
{
    public function __construct(private IssueInvoice $issueInvoice) {}

    public function issueMissing(): int
    {
        $dueBefore = CarbonImmutable::now()->subMinutes(config()->integer('orders.missing_documents_grace_minutes'));
        $issued = 0;
        $orders = Order::query()
            ->where('payment_status', OrderPaymentStatus::Paid->value)
            ->where('paid_at', '<=', $dueBefore)
            ->whereHas('events', static fn (Builder $events): Builder => $events->where('type', OrderEventType::Paid->value))
            ->whereDoesntHave('invoice')
            ->orderBy('id')
            ->limit(config()->integer('orders.missing_documents_per_run'))
            ->get();

        foreach ($orders as $order) {
            if ($this->issueInvoice->handle($order) !== null) {
                $issued++;
            }
        }

        return $issued;
    }
}
