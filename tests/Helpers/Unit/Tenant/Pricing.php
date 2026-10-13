<?php

declare(strict_types=1);

use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Tenant\Pricing\Data\TaxableLine;
use App\Tenant\Pricing\Data\TaxClassData;
use App\Tenant\Pricing\Data\TaxComponent;
use App\Tenant\Pricing\Enums\TaxLineKind;

/*
|--------------------------------------------------------------------------
| For LineTaxTest.php
|--------------------------------------------------------------------------
*/

function taxableLine(int $minorAmount, string $currency = 'NGN', string $key = 'line'): TaxableLine
{
    return new TaxableLine($key, TaxLineKind::Goods, Money::ofMinor($minorAmount, $currency), new TaxClassData('class', 'Standard', false));
}

function taxComponentAt(string $rate, string $name = 'VAT'): TaxComponent
{
    return new TaxComponent($name, 'NG', null, Decimal::of($rate));
}
