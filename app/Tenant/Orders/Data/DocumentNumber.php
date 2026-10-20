<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Data;

/**
 * A number given to an invoice or credit note: as printed, and where it sits in its series.
 */
final readonly class DocumentNumber
{
    /**
     * @param  string  $number  As printed, such as "INV-2026-000042".
     * @param  string  $series  A year, such as "2026", or "all" when numbering continuously.
     * @param  int  $sequenceNumber  Its place in the series, from 1.
     */
    public function __construct(
        public string $number,
        public string $series,
        public int $sequenceNumber,
    ) {}
}
