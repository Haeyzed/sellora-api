<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Data;

use App\Tenant\Storefront\Enums\BannerKind;
use Carbon\CarbonImmutable;

/**
 * A banner's details as staff send them: only the fields sent change (or, for a new banner, are set).
 */
final readonly class BannerData
{
    /**
     * @param  BannerKind|null  $kind  Only for a new banner; it never changes.
     * @param  array<string, string|int|bool|CarbonImmutable|null>  $attributes  The plain fields sent, by column; null clears one. Times are in UTC.
     * @param  array<string, array<string, string|null>>  $translations  The translated fields sent, by column and language; a language set to null loses its text.
     * @param  list<string>  $removes  The translated fields sent as null, removed in every language.
     */
    public function __construct(
        public ?BannerKind $kind = null,
        public array $attributes = [],
        public array $translations = [],
        public array $removes = [],
    ) {}
}
