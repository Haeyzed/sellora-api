<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Requests;

use App\Shared\Geography\Rules\CountryCode;
use App\Shared\Geography\Rules\StateCode;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Data\PricingLine;
use App\Tenant\Pricing\Data\PricingRequest;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Pricing\Models\TaxClass;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Previewing what lines cost with tax, in a currency, to a destination. Needs the pricing.view permission.
 */
final class QuotePricesRequest extends FormRequest
{
    use ActsAsStaffMember;

    /** The most lines one preview may hold. */
    private const int MAX_LINES = 100;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', TaxClass::class);
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
            /** Where the order would go, ISO 3166-1 alpha-2; the store's own country when left out. */
            'country_code' => ['sometimes', 'string', new CountryCode],
            /** The destination's state code within the country, such as "LA". */
            'state_code' => ['sometimes', 'nullable', 'string', new StateCode(is_string($countryCode) ? $countryCode : null)],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            /** The variant's ID. */
            'lines.*.variant' => ['required', 'string', Rule::exists(ProductVariant::class, 'public_id')->whereNull('deleted_at')],
            'lines.*.quantity' => ['required', 'integer:strict', 'min:1', 'max:100000'],
            /** Off the whole line, in the currency's smallest unit; at most the line's price. */
            'lines.*.discount' => ['sometimes', 'integer:strict', 'min:0'],
            /** The delivery charge in the currency's smallest unit, taxed at the store's shipping tax class. */
            'shipping' => ['sometimes', 'integer:strict', 'min:0'],
        ];
    }

    public function pricingRequest(): PricingRequest
    {
        $currency = $this->filled('currency') ? $this->string('currency')->value() : app(FindStoreSettings::class)->handle()->currency_code;
        $lines = [];

        foreach ($this->array('lines') as $line) {
            if (is_array($line) && is_string($line['variant'] ?? null) && is_int($line['quantity'] ?? null)) {
                $lines[] = ['variant' => $line['variant'], 'quantity' => $line['quantity'], 'discount' => is_int($line['discount'] ?? null) ? $line['discount'] : null];
            }
        }

        $variants = ProductVariant::query()->whereIn('public_id', array_column($lines, 'variant'))->get()->keyBy('public_id');
        $pricingLines = [];

        foreach ($lines as $position => $line) {
            $pricingLines[] = new PricingLine(
                'line-'.($position + 1),
                $variants->get($line['variant']) ?? throw (new ModelNotFoundException)->setModel(ProductVariant::class, [$line['variant']]),
                $line['quantity'],
                $line['discount'] === null ? null : Money::ofMinor($line['discount'], $currency),
            );
        }

        return new PricingRequest(
            $currency,
            $pricingLines,
            $this->filled('country_code') ? new TaxAddress($this->string('country_code')->value(), $this->filled('state_code') ? $this->string('state_code')->value() : null) : null,
            $this->has('shipping') ? Money::ofMinor($this->integer('shipping'), $currency) : null,
        );
    }
}
