<?php

declare(strict_types=1);

use App\Shared\Money\Money;

/*
|--------------------------------------------------------------------------
| For MoneyTest.php
|--------------------------------------------------------------------------
*/

/**
 * @param  array<array-key, Money>  $amounts
 * @return array<array-key, int>
 */
function minorAmountsOf(array $amounts): array
{
    return array_map(static fn (Money $amount): int => $amount->minorAmount(), $amounts);
}
