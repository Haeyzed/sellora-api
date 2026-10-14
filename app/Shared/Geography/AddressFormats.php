<?php

declare(strict_types=1);

namespace App\Shared\Geography;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;

/**
 * Each country's address format: required fields and postcode pattern, from Google's address data through commerceguys/addressing.
 *
 * With Geography, which reads the same data's states, the only code that
 * may use that package (an architecture rule enforces it).
 */
final class AddressFormats
{
    /**
     * @var array<string, AddressFormat>
     */
    private array $formats = [];

    private ?AddressFormatRepository $formatRepository = null;

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

    private function build(string $countryCode): AddressFormat
    {
        $format = $this->formatRepository()->get($countryCode);
        $required = $format->getRequiredFields();
        $pattern = $format->getPostalCodePattern();

        return new AddressFormat(
            countryCode: $countryCode,
            requiresCity: in_array(AddressField::LOCALITY, $required, true),
            requiresState: in_array(AddressField::ADMINISTRATIVE_AREA, $required, true),
            requiresPostalCode: in_array(AddressField::POSTAL_CODE, $required, true),
            postalCodePattern: $pattern === null || $pattern === '' ? null : $pattern,
        );
    }

    private function formatRepository(): AddressFormatRepository
    {
        return $this->formatRepository ??= new AddressFormatRepository;
    }
}
