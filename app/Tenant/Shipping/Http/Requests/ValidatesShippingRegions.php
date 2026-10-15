<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Requests;

use App\Shared\Geography\Geography;
use App\Shared\Geography\Rules\CountryCode;
use App\Shared\Geography\Rules\StateCode;
use App\Tenant\Shipping\Data\ShippingRegion;
use Closure;
use Illuminate\Validation\Validator;

/**
 * The "regions" a zone covers: countries, or states of them from Geography's states list (the same source tax uses), each once.
 */
trait ValidatesShippingRegions
{
    public const int MAX_REGIONS = 300;

    /**
     * @return array<string, mixed>
     */
    protected function regionRules(bool $required): array
    {
        return [
            /** The countries and states it covers, replacing the list it had. Each region can be in only one zone; a state's zone beats its country's. */
            'regions' => [$required ? 'present' : 'sometimes', 'array', 'max:'.self::MAX_REGIONS],
            /** ISO 3166-1 alpha-2, such as "NG". */
            'regions.*.country_code' => ['required', 'string', new CountryCode],
            /** A state's code from the country's states list (GET geography/countries/{country}/states), such as "LA", in any case; leave out or null for the whole country. Refused for a country with no states list. */
            'regions.*.state_code' => ['sometimes', 'nullable', 'string', $this->stateOfItsCountry(...)],
        ];
    }

    /**
     * @return Closure(Validator): void
     */
    protected function regionsListedOnce(): Closure
    {
        return function (Validator $validator): void {
            if ($validator->errors()->hasAny(['regions', 'regions.*'])) {
                return;
            }

            $seen = [];

            foreach ($this->regions() as $index => $region) {
                if (in_array($region->label(), $seen, true)) {
                    $validator->errors()->add("regions.{$index}", __('validation.distinct', ['attribute' => 'region']));
                }

                $seen[] = $region->label();
            }
        };
    }

    /**
     * @return list<ShippingRegion>
     */
    public function regions(): array
    {
        $geography = app(Geography::class);

        return array_values(array_map(static function (mixed $region) use ($geography): ShippingRegion {
            $region = (array) $region;
            $countryCode = (string) $region['country_code'];
            $stateCode = isset($region['state_code']) && is_string($region['state_code']) && $region['state_code'] !== '' ? $geography->stateCode($countryCode, $region['state_code']) : null;

            return new ShippingRegion($countryCode, $stateCode);
        }, (array) $this->input('regions', [])));
    }

    private function stateOfItsCountry(string $attribute, mixed $value, Closure $fail): void
    {
        $index = explode('.', $attribute)[1] ?? '';
        $countryCode = $this->input("regions.{$index}.country_code");
        $countryCode = is_string($countryCode) && app(Geography::class)->country($countryCode) !== null ? $countryCode : null;

        StateCode::forTaxRate($countryCode)->validate($attribute, $value, $fail);
    }
}
