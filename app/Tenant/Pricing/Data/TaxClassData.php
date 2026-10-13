<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Tenant\Pricing\Models\TaxClass;

/**
 * The tax class a line is in, as a calculator sees it.
 */
final readonly class TaxClassData
{
    /**
     * @param  string  $id  The class's public ID.
     * @param  bool  $isExempt  Outside tax: never taxed and no rate is looked up.
     */
    public function __construct(
        public string $id,
        public string $name,
        public bool $isExempt,
    ) {}

    public static function of(TaxClass $taxClass): self
    {
        return new self($taxClass->public_id, $taxClass->name, $taxClass->is_exempt);
    }
}
