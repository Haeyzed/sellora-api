<?php

declare(strict_types=1);

namespace App\Shared\Geography;

/**
 * A country whose states list (nnjeim/world) and address data (commerceguys/addressing) don't list the same states.
 *
 * While they differ, a state is never required in that country's addresses.
 */
final readonly class StateListDifference
{
    /**
     * @param  list<string>  $onlyInStatesList  Codes the states list has and the address data doesn't.
     * @param  list<string>  $onlyInAddressData  Codes (or, for some countries, names) the address data has and the states list doesn't.
     * @param  bool  $addressDataRequiresState  Whether the address data would otherwise make the state required.
     */
    public function __construct(
        public string $countryCode,
        public array $onlyInStatesList,
        public array $onlyInAddressData,
        public bool $addressDataRequiresState,
    ) {}
}
