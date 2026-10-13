<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| For GeographyTest.php
|--------------------------------------------------------------------------
*/

/**
 * @return array<string, mixed>
 */
function geographyCountry(string $code, array $countries): array
{
    return collect($countries)->firstWhere('code', $code) ?? [];
}
