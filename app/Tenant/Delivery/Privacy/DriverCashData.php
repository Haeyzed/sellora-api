<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Delivery\Models\DriverCashEntry;

/**
 * The cash the driver collected and handed over. A business record: erasing the driver keeps it as it is, still pointing at the erased driver, since it holds no personal data beyond that link.
 */
final readonly class DriverCashData implements PersonalDataHandler
{
    /**
     * @return list<array<string, mixed>>
     */
    public function export(DataSubject $subject): array
    {
        $driver = DriverDataSubject::driver($subject);

        if ($driver === null) {
            return [];
        }

        return array_values(DriverCashEntry::query()->with('delivery')->where('driver_id', $driver->id)->orderBy('id')->get()->map(static fn (DriverCashEntry $entry): array => [
            'id' => $entry->public_id,
            'type' => $entry->type->value,
            'amount' => $entry->cash_amount,
            'currency' => $entry->cash_currency,
            'delivery' => $entry->delivery?->public_id,
            'recorded_at' => $entry->created_at->toIso8601String(),
        ])->all());
    }

    /**
     * Keeps the ledger: it is the store's record of cash in its drivers' hands.
     */
    public function erase(DataSubject $subject): void {}
}
