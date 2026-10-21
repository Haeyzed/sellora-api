<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Tenant\Settings\Actions\FindStoreSettings;
use Carbon\CarbonImmutable;

/**
 * How long the store must keep its invoices and credit notes, buyer's details included: its country's tax-record retention (config/geography.php), counted from the end of the year each was issued in, as most tax laws count it.
 */
final readonly class TaxRecordRetention
{
    public function __construct(private FindStoreSettings $findStoreSettings) {}

    /**
     * The last day a document issued on this date must be kept: 31 December, the retention's number of years after the year it was issued.
     */
    public function keptUntil(CarbonImmutable $issuedOn): CarbonImmutable
    {
        return CarbonImmutable::parse(sprintf('%04d-12-31', $issuedOn->year + $this->findStoreSettings->handle()->taxRecordRetentionYears()));
    }

    /**
     * Documents issued before this date are past their retention today, in the store's timezone.
     */
    public function endedForIssuedBefore(): CarbonImmutable
    {
        $settings = $this->findStoreSettings->handle();

        return CarbonImmutable::parse(sprintf('%04d-01-01', CarbonImmutable::now($settings->timezone)->year - $settings->taxRecordRetentionYears()));
    }
}
