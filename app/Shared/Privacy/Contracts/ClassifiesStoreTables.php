<?php

declare(strict_types=1);

namespace App\Shared\Privacy\Contracts;

use App\Shared\Privacy\StoreExportRegistry;

/**
 * A domain's or module's classification of its own store tables for exports: every column included, included without secret values, or excluded with a reason.
 */
interface ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void;
}
