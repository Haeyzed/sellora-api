<?php

declare(strict_types=1);

namespace App\Shared\Geography\Rules;

use App\Shared\Geography\Geography;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A state, province or region code of the given country, in upper case, such as "LA" for Lagos in Nigeria.
 *
 * Pass the country only once it has been validated; with no country, every state is refused.
 */
final readonly class StateCode implements ValidationRule
{
    public function __construct(private ?string $countryCode) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)
            || $this->countryCode === null
            || $value !== mb_strtoupper($value)
            || app(Geography::class)->state($this->countryCode, $value) === null) {
            $fail('geography.unknown_state')->translate();
        }
    }
}
