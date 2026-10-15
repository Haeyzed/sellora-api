<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Services;

use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Shipping\Data\DeliveryEstimate;
use App\Tenant\Shipping\Models\ShippingMethod;
use Carbon\CarbonImmutable;

/**
 * Turns a method's business days into dates: counted from today in the store's timezone, on the store's working days only. No holidays yet.
 *
 * Zero days is today when the store works today, else its next working day;
 * N days is the Nth working day after today.
 */
final class DeliveryEstimates
{
    /**
     * @var array{today: CarbonImmutable, workingDays: list<int>}|null
     */
    private ?array $calendar = null;

    public function __construct(
        private readonly FindStoreSettings $findStoreSettings,
    ) {}

    /**
     * The method's estimate from today, or null when it gives none.
     */
    public function for(ShippingMethod $method): ?DeliveryEstimate
    {
        if ($method->min_days === null || $method->max_days === null) {
            return null;
        }

        return new DeliveryEstimate($method->min_days, $method->max_days, $this->workingDayAfter($method->min_days), $this->workingDayAfter($method->max_days));
    }

    private function workingDayAfter(int $days): CarbonImmutable
    {
        ['today' => $date, 'workingDays' => $workingDays] = $this->calendar();

        while (! in_array($date->dayOfWeekIso, $workingDays, true)) {
            $date = $date->addDay();
        }

        for ($counted = 0; $counted < $days;) {
            $date = $date->addDay();

            if (in_array($date->dayOfWeekIso, $workingDays, true)) {
                $counted++;
            }
        }

        return $date;
    }

    /**
     * Today in the store's timezone and its working days, read once per quote.
     *
     * @return array{today: CarbonImmutable, workingDays: list<int>}
     */
    private function calendar(): array
    {
        if ($this->calendar === null) {
            $settings = $this->findStoreSettings->handle();
            $this->calendar = ['today' => CarbonImmutable::now($settings->timezone)->startOfDay(), 'workingDays' => $settings->working_days];
        }

        return $this->calendar;
    }
}
