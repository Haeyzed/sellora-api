<?php

declare(strict_types=1);

namespace App\Shared\Geography;

/**
 * A country stores can be registered in or deliver to, identified by its ISO 3166-1 alpha-2 code.
 */
final readonly class Country
{
    /**
     * @param  string  $code  ISO 3166-1 alpha-2, such as "NG".
     * @param  string|null  $currencyCode  Its ISO 4217 currency, or null when it has none in use today.
     * @param  list<string>  $timezones  Its IANA timezones, such as ["Africa/Lagos"].
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $iso3,
        public ?string $phoneCode,
        public ?string $emoji,
        public ?string $currencyCode,
        public array $timezones,
    ) {}
}
