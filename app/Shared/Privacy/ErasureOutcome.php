<?php

declare(strict_types=1);

namespace App\Shared\Privacy;

use Carbon\CarbonImmutable;

/**
 * What an erasure request left behind: nothing, or records the law makes the store keep, with the person's details, until a date (such as tax invoices).
 */
final readonly class ErasureOutcome
{
    public function __construct(public ?CarbonImmutable $keptByLawUntil) {}
}
