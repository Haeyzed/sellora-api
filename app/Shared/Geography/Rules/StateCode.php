<?php

declare(strict_types=1);

namespace App\Shared\Geography\Rules;

use App\Shared\Geography\Geography;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A state, province or region of the given country, such as "LA" for Lagos in Nigeria, in any case (section 3.3).
 *
 * Where the address data lists the country's states, it must be one of
 * them; store it as Geography::stateCode() gives it. Where it lists none, an
 * address's state is free text (forAddress), but a tax rate's is refused,
 * since only country-wide rates apply there (forTaxRate). Pass the country
 * only once it has been validated; with no country, every state is refused.
 */
final readonly class StateCode implements ValidationRule
{
    public const int MAX_FREE_TEXT_LENGTH = 100;

    private function __construct(
        private ?string $countryCode,
        private bool $acceptsFreeText,
    ) {}

    public static function forAddress(?string $countryCode): self
    {
        return new self($countryCode, acceptsFreeText: true);
    }

    public static function forTaxRate(?string $countryCode): self
    {
        return new self($countryCode, acceptsFreeText: false);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $this->countryCode === null) {
            $fail('geography.unknown_state')->translate();

            return;
        }

        $geography = app(Geography::class);

        if ($geography->listsStates($this->countryCode)) {
            if ($geography->state($this->countryCode, $value) === null) {
                $fail('geography.unknown_state')->translate();
            }

            return;
        }

        if (! $this->acceptsFreeText) {
            $fail('geography.no_states')->translate();
        } elseif (mb_strlen(trim($value)) > self::MAX_FREE_TEXT_LENGTH) {
            $fail('geography.state_too_long')->translate(['max' => self::MAX_FREE_TEXT_LENGTH]);
        }
    }
}
