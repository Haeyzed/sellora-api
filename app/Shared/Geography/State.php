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
     * @param  string  $code  The subdivision's code within its country, such as "LA" for Lagos (ISO 3166-2 "NG-LA").
     * @param  string|null  $type  Such as "state", "province" or "capital territory".
     */
    public function __construct(
        public string $countryCode,
        public string $code,
        public string $name,
        public ?string $type,
    ) {}
}
