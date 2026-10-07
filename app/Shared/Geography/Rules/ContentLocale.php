<?php

declare(strict_types=1);

namespace App\Shared\Geography\Rules;

use App\Shared\Geography\Geography;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A language stores can publish their content in, from config/geography.php, such as "fr".
 */
final readonly class ContentLocale implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(Geography::class)->isContentLocale($value)) {
            $fail('geography.unknown_locale')->translate();
        }
    }
}
