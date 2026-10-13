<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Data;

use App\Tenant\Pricing\Data\TaxAddress;

/**
 * A copy of a postal address at one moment, for records that must never change when the saved address does, such as an order (section 3.3).
 */
final readonly class AddressSnapshot
{
    /**
     * @param  string|null  $stateCode  The state's code within the country, such as "LA".
     * @param  string  $countryCode  ISO 3166-1 alpha-2.
     * @param  string|null  $phone  In E.164 format.
     */
    public function __construct(
        public string $recipientName,
        public ?string $company,
        public string $line1,
        public ?string $line2,
        public ?string $city,
        public ?string $stateCode,
        public ?string $postalCode,
        public string $countryCode,
        public ?string $phone,
    ) {}

    /**
     * The parts of the address tax depends on.
     */
    public function toTaxAddress(): TaxAddress
    {
        return new TaxAddress($this->countryCode, $this->stateCode, $this->postalCode);
    }

    /**
     * @return array{recipient_name: string, company: string|null, line1: string, line2: string|null, city: string|null, state_code: string|null, postal_code: string|null, country_code: string, phone: string|null}
     */
    public function toArray(): array
    {
        return [
            'recipient_name' => $this->recipientName,
            'company' => $this->company,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'state_code' => $this->stateCode,
            'postal_code' => $this->postalCode,
            'country_code' => $this->countryCode,
            'phone' => $this->phone,
        ];
    }
}
