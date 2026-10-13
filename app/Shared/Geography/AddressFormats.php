<?php

declare(strict_types=1);

namespace App\Shared\Geography;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;

/**
 * Each country's address format: required fields and postcode pattern, from Google's address data through commerceguys/addressing.
 *
 * The only code that may use that package (an architecture rule enforces
 * it), and only for formats: countries and states come from Geography
 * (nnjeim/world). Where the two disagree about a country's states, the state
 * is optional for that country and the difference is listed by
 * stateListDifferences(), so nobody chooses between them silently.
 */
final class AddressFormats
{
    /**
     * @var array<string, AddressFormat>
     */
    private array $formats = [];

    private ?AddressFormatRepository $formatRepository = null;

    private ?SubdivisionRepository $subdivisionRepository = null;

    public function __construct(private readonly Geography $geography) {}

    /**
     * The address format of a country we support (any case), or null for one we don't.
     */
    public function for(string $countryCode): ?AddressFormat
    {
        $country = $this->geography->country($countryCode);

        if ($country === null) {
            return null;
        }

        return $this->formats[$country->code] ??= $this->build($country->code);
    }

    /**
     * Every country whose states list and address data disagree about its states, by country code.
     *
     * @return list<StateListDifference>
     */
    public function stateListDifferences(): array
    {
        $differences = [];

        foreach ($this->geography->countries() as $country) {
            $difference = $this->stateListDifference($country->code);

            if ($difference !== null) {
                $differences[] = $difference;
            }
        }

        return $differences;
    }

    private function build(string $countryCode): AddressFormat
    {
        $format = $this->formatRepository()->get($countryCode);
        $required = $format->getRequiredFields();
        $pattern = $format->getPostalCodePattern();

        return new AddressFormat(
            countryCode: $countryCode,
            requiresCity: in_array(AddressField::LOCALITY, $required, true),
            requiresState: in_array(AddressField::ADMINISTRATIVE_AREA, $required, true) && $this->stateListDifference($countryCode) === null,
            requiresPostalCode: in_array(AddressField::POSTAL_CODE, $required, true),
            postalCodePattern: $pattern === null || $pattern === '' ? null : $pattern,
        );
    }

    /**
     * How the two sources differ about a country's states, or null when they agree (or neither lists any and none is required).
     */
    private function stateListDifference(string $countryCode): ?StateListDifference
    {
        $statesList = array_map(static fn (State $state): string => $state->code, $this->geography->states($countryCode));
        $addressData = array_map(strval(...), array_keys($this->subdivisionRepository()->getList([$countryCode])));
        $requiresState = in_array(AddressField::ADMINISTRATIVE_AREA, $this->formatRepository()->get($countryCode)->getRequiredFields(), true);

        // The address data knows no states here and doesn't require one, so it has nothing to say about them.
        if ($addressData === [] && ! $requiresState) {
            return null;
        }

        $onlyInStatesList = array_values(array_diff($statesList, $addressData));
        $onlyInAddressData = array_values(array_diff($addressData, $statesList));

        if ($onlyInStatesList === [] && $onlyInAddressData === [] && $statesList !== []) {
            return null;
        }

        sort($onlyInStatesList);
        sort($onlyInAddressData);

        return new StateListDifference($countryCode, $onlyInStatesList, $onlyInAddressData, $requiresState);
    }

    private function formatRepository(): AddressFormatRepository
    {
        return $this->formatRepository ??= new AddressFormatRepository;
    }

    private function subdivisionRepository(): SubdivisionRepository
    {
        return $this->subdivisionRepository ??= new SubdivisionRepository($this->formatRepository());
    }
}
