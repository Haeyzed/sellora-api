<?php

declare(strict_types=1);

namespace App\Shared\Geography\Rules;

use App\Shared\Geography\Geography;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A current IANA timezone, such as "Africa/Lagos". Deprecated aliases such as "US/Eastern" are refused.
 */
final readonly class Timezone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(Geography::class)->isTimezone($value)) {
            $fail('geography.unknown_timezone')->translate();
        }
    }
}
