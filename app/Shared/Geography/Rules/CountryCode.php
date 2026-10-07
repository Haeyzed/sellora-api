<?php

declare(strict_types=1);

namespace App\Shared\Geography\Rules;

use App\Shared\Geography\Geography;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An ISO 3166-1 alpha-2 country code we support, in upper case, such as "NG".
 */
final readonly class CountryCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value !== mb_strtoupper($value) || app(Geography::class)->country($value) === null) {
            $fail('geography.unknown_country')->translate();
        }
    }
}
