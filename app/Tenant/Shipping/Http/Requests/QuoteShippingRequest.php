<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Requests;

use App\Shared\Geography\Geography;
use App\Shared\Geography\Rules\CountryCode;
use App\Shared\Geography\Rules\StateCode;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Shipping\Data\ShippingRegion;
use App\Tenant\Shipping\Models\ShippingZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * Previewing what shipping would cost for some lines, in a currency, to a destination. Needs the shipping.view permission.
 */
final class QuoteShippingRequest extends FormRequest
{
    use ActsAsStaffMember;

    /** The most lines one preview may hold. */
    private const int MAX_LINES = 100;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', ShippingZone::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $countryCode = $this->input('country_code');

        return [
            /** ISO 4217, one the store offers now; the store's own currency when left out. */
            'currency' => ['sometimes', 'string', 'regex:/^[A-Z]{3}$/'],
            /** Where it would go, ISO 3166-1 alpha-2. */
            'country_code' => ['required', 'string', new CountryCode],
            /** The state's code from the country's states list, such as "LA", in any case; free text where the country has none. */
            'state_code' => ['sometimes', 'nullable', 'string', StateCode::forAddress(is_string($countryCode) ? $countryCode : null)],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            /** The variant's ID. */
            'lines.*.variant' => ['required', 'string', Rule::exists(ProductVariant::class, 'public_id')->whereNull('deleted_at')],
            'lines.*.quantity' => ['required', 'integer:strict', 'min:1', 'max:100000'],
        ];
    }

    public function currency(): string
    {
        return $this->filled('currency') ? $this->string('currency')->value() : app(FindStoreSettings::class)->handle()->currency_code;
    }

    public function destination(): ShippingRegion
    {
        $countryCode = $this->string('country_code')->value();

        return new ShippingRegion($countryCode, $this->filled('state_code') ? app(Geography::class)->stateCode($countryCode, $this->string('state_code')->value()) : null);
    }

    /**
     * @return list<array{variant: ProductVariant, quantity: int}>
     */
    public function lines(): array
    {
        $lines = array_values(array_filter($this->array('lines'), static fn (mixed $line): bool => is_array($line)));
        $variants = ProductVariant::query()->whereIn('public_id', array_column($lines, 'variant'))->get()->keyBy('public_id');

        return array_map(static fn (array $line): array => ['variant' => $variants->get((string) $line['variant']) ?? throw new LogicException('Validated above.'), 'quantity' => (int) $line['quantity']], $lines);
    }
}
