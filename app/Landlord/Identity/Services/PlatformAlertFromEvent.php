<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Services;

use App\Landlord\Identity\Data\PlatformAlertData;
use App\Landlord\Identity\Enums\PlatformAlertKind;
use App\Landlord\Tenancy\Events\InvoicesDoNotMatchSales;
use App\Landlord\Tenancy\Events\StockLevelsDoNotMatchLedger;
use App\Landlord\Tenancy\Events\StoreSetupFailed;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Money\ExchangeRates\Events\ExchangeRatesOverdue;

/**
 * Turns a platform event that needs Sellora's team into the alert to send about it.
 */
final readonly class PlatformAlertFromEvent
{
    /**
     * Every event that raises a platform alert.
     *
     * @var list<class-string>
     */
    public const array EVENTS = [
        ExchangeRatesOverdue::class,
        StoreSetupFailed::class,
        StockLevelsDoNotMatchLedger::class,
        InvoicesDoNotMatchSales::class,
    ];

    /**
     * The alert an event raises; null for an event that raises none.
     */
    public function for(object $event): ?PlatformAlertData
    {
        return match (true) {
            $event instanceof ExchangeRatesOverdue => new PlatformAlertData(PlatformAlertKind::ExchangeRatesOverdue, null, ['hours' => $event->alertAfterHours, 'stale_hours' => $event->staleAfterHours]),
            $event instanceof StoreSetupFailed => new PlatformAlertData(PlatformAlertKind::StoreSetupFailed, $event->store->public_id, $this->store($event->store)),
            $event instanceof StockLevelsDoNotMatchLedger => new PlatformAlertData(PlatformAlertKind::StockLevelsMismatch, $event->store->public_id, [...$this->store($event->store), 'count' => $event->mismatches]),
            $event instanceof InvoicesDoNotMatchSales => new PlatformAlertData(PlatformAlertKind::InvoiceBalancesMismatch, $event->store->public_id, [...$this->store($event->store), 'count' => $event->mismatches]),
            default => null,
        };
    }

    /**
     * @return array{store: string, store_id: string}
     */
    private function store(Tenant $store): array
    {
        return ['store' => $store->name, 'store_id' => $store->public_id];
    }
}
