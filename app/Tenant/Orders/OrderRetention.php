<?php

declare(strict_types=1);

namespace App\Tenant\Orders;

use App\Shared\Privacy\PersonalDataErasure;
use App\Shared\Retention\Contracts\RetentionPolicy;
use App\Shared\Retention\RetentionScope;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Services\OrderAnonymisation;
use App\Tenant\Orders\Services\OrderRetentionPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Anonymises orders' personal data in every store's daily purge (section 3.3): orders whose buyer's data was erased while they were being fulfilled, once settled; and every settled order once the store's retention period for orders has passed since it settled.
 *
 * Orders are financial records, so they are never deleted: their amounts,
 * tax and country stay. The period is the store's own setting, or its
 * country's default (config/geography.php); config/retention.php only sets
 * a floor. Each order is anonymised in its own PersonalDataErasure run.
 */
final readonly class OrderRetention implements RetentionPolicy
{
    public function __construct(
        private PersonalDataErasure $personalDataErasure,
        private OrderAnonymisation $orderAnonymisation,
        private OrderRetentionPeriod $orderRetentionPeriod,
    ) {}

    public function periodKey(): string
    {
        return 'settled_orders';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    public function purgeOlderThan(CarbonImmutable $cutoff): int
    {
        $storeCutoff = CarbonImmutable::now()->subYears($this->orderRetentionPeriod->years());
        $cutoff = $storeCutoff->isBefore($cutoff) ? $storeCutoff : $cutoff;
        $anonymised = 0;

        $due = Order::query()
            ->whereNull('anonymised_at')
            ->whereIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
            ->where(static fn (Builder $orders) => $orders
                ->where('anonymise_when_settled', true)
                ->orWhereRaw('coalesce(completed_at, cancelled_at) < ?', [$cutoff]));

        foreach ($due->lazyById(100) as $order) {
            if ($this->orderAnonymisation->isSettled($order)) {
                $this->personalDataErasure->run(fn () => $this->orderAnonymisation->anonymise($order));
                $anonymised++;
            }
        }

        return $anonymised;
    }
}
