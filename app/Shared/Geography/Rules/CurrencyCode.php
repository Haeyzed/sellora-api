<?php

declare(strict_types=1);

namespace App\Shared\Geography\Rules;

use App\Shared\Geography\Geography;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An ISO 4217 currency in use today, in upper case, such as "NGN".
 */
final readonly class CurrencyCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(Geography::class)->isCurrency($value)) {
            $fail('geography.unknown_currency')->translate();
        }
    }
}
