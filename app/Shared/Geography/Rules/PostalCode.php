<?php

declare(strict_types=1);

namespace App\Shared\Geography\Rules;

use App\Shared\Geography\AddressFormats;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A postcode in the given country's format, such as "SW1A 1AA" in the United Kingdom. Any text up to 20 characters for a country without a format.
 *
 * Pass the country only once it has been validated; with no country, only the length is checked.
 */
final readonly class PostalCode implements ValidationRule
{
    public function __construct(private ?string $countryCode) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen(trim($value)) > 20) {
            $fail('geography.invalid_postal_code')->translate();

            return;
        }

        $format = $this->countryCode === null ? null : app(AddressFormats::class)->for($this->countryCode);

        if ($format !== null && ! $format->acceptsPostalCode($value)) {
            $fail('geography.invalid_postal_code')->translate();
        }
    }
}
