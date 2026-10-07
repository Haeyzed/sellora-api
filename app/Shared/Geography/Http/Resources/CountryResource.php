<?php

declare(strict_types=1);

namespace App\Shared\Geography\Http\Resources;

use App\Shared\Geography\Country;
use App\Shared\Geography\CountryDefaults;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A country, with what a new store there starts with.
 *
 * @property Country $resource
 */
final class CountryResource extends JsonResource
{
    public function __construct(Country $country, private readonly ?CountryDefaults $defaults)
    {
        parent::__construct($country);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** ISO 3166-1 alpha-2, such as "NG". */
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            /** ISO 3166-1 alpha-3, such as "NGA". */
            'iso3' => $this->resource->iso3,
            /** The international dialling code, such as "234". */
            'phone_code' => $this->resource->phoneCode,
            'emoji' => $this->resource->emoji,
            /** ISO 4217, such as "NGN"; null when it has no currency in use today. */
            'currency' => $this->resource->currencyCode,
            /** IANA timezones, such as ["Africa/Lagos"]. */
            'timezones' => $this->resource->timezones,
            /** What a new store there starts with; null when stores can't be registered there. The merchant can change each later. */
            'store_defaults' => $this->defaults === null ? null : [
                'currency' => $this->defaults->currencyCode,
                /** The store's first content language, such as "en". */
                'locale' => $this->defaults->locale,
                'tax_mode' => $this->defaults->taxMode,
            ],
        ];
    }
}
