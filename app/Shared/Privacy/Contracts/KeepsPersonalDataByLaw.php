<?php

declare(strict_types=1);

namespace App\Shared\Privacy\Contracts;

use App\Shared\Privacy\DataSubject;
use Carbon\CarbonImmutable;

/**
 * A personal data handler whose records the law makes the store keep, with the person's details on them, for a while after an erasure request, such as tax invoices.
 *
 * Erasure clears them once that period ends; until then the person is told
 * what is kept and until when.
 */
interface KeepsPersonalDataByLaw
{
    /**
     * The last day the person's details must still be kept on these records, or null when nothing is kept.
     */
    public function keptUntil(DataSubject $subject): ?CarbonImmutable;
}
