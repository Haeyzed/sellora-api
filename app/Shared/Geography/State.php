<?php

declare(strict_types=1);

namespace App\Shared\Geography;

/**
 * A country's first-level subdivision (a state, province, region or territory), identified by its country and code.
 */
final readonly class State
{
    /**
     * @param  string  $countryCode  ISO 3166-1 alpha-2, such as "NG".
     * @param  string  $code  The address data's key for it within its country: usually the ISO 3166-2 subdivision part, such as "LA" for Lagos ("NG-LA").
     * @param  string|null  $type  What the country calls them, such as "state", "province" or "prefecture".
     */
    public function __construct(
        public string $countryCode,
        public string $code,
        public string $name,
        public ?string $type,
    ) {}
}
