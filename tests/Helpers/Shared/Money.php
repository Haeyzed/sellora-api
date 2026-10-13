<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| For ExchangeRatesTest.php
|--------------------------------------------------------------------------
*/

/**
 * @return list<string>
 */
function messagesLoggedAt(string $level): array
{
    return array_values(array_map(static fn (array $entry): string => $entry[1], array_filter(test()->logged, static fn (array $entry): bool => $entry[0] === $level)));
}
