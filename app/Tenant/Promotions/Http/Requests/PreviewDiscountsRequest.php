<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Requests;

use App\Shared\Geography\Geography;
use App\Shared\Geography\Rules\CountryCode;
use App\Shared\Geography\Rules\StateCode;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A basket staff want to see priced with its discounts, as a customer or a guest would get them. Needs promotions.view.
 */
final class PreviewDiscountsRequest extends FormRequest
{
    use ActsAsStaffMember;

    public const int MAX_LINES = 100;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Promotion::class);
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
            /** The destination's state code from the country's states list, such as "LA", in any case. Needs country_code. */
            'state_code' => ['sometimes', 'nullable', 'string', StateCode::forAddress(is_string($countryCode) ? $countryCode : null)],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            /** The variant's ID. */
            'lines.*.variant' => ['required', 'string', Rule::exists(ProductVariant::class, 'public_id')->whereNull('deleted_at')],
            'lines.*.quantity' => ['required', 'integer:strict', 'min:1', 'max:100000'],
            /** The delivery charge before discounts, in the currency's smallest unit; leave out before it is known. */
            'shipping' => ['sometimes', 'integer:strict', 'min:0'],
            /** A code to try, as a customer would enter it. */
            'code' => ['sometimes', 'string', 'max:32'],
            /** The customer to price for, by ID: their groups and first-order status count. */
            'customer' => ['sometimes', 'string', Rule::exists(Customer::class, 'public_id')->whereNull('deleted_at')->whereNull('erased_at')],
            /** For a guest instead: the email they would check out with. */
            'email' => ['sometimes', 'string', 'email:rfc', 'max:254', 'prohibits:customer'],
        ];
    }

    public function currency(): string
    {
        return $this->filled('currency') ? $this->string('currency')->value() : app(FindStoreSettings::class)->handle()->currency_code;
    }

    /**
     * @return list<array{key: string, variant: ProductVariant, quantity: int}>
     */
    public function lines(): array
    {
        $rows = array_values(array_filter($this->array('lines'), is_array(...)));
        $variants = ProductVariant::query()->whereIn('public_id', array_column($rows, 'variant'))->get()->keyBy('public_id');
        $lines = [];

        foreach ($rows as $position => $row) {
            $lines[] = [
                'key' => 'line-'.($position + 1),
                'variant' => $variants->get($row['variant']) ?? throw (new ModelNotFoundException)->setModel(ProductVariant::class, [$row['variant']]),
                'quantity' => (int) $row['quantity'],
            ];
        }

        return $lines;
    }

    public function destination(): ?TaxAddress
    {
        if (! $this->filled('country_code')) {
            return null;
        }

        $country = $this->string('country_code')->value();

        return new TaxAddress($country, $this->filled('state_code') ? app(Geography::class)->stateCode($country, $this->string('state_code')->value()) : null);
    }

    public function shipping(string $currency): ?Money
    {
        return $this->has('shipping') ? Money::ofMinor($this->integer('shipping'), $currency) : null;
    }

    public function buyer(): DiscountBuyer
    {
        if ($this->filled('customer')) {
            return DiscountBuyer::customer(Customer::query()->where('public_id', $this->string('customer')->value())->sole());
        }

        return DiscountBuyer::guest($this->filled('email') ? $this->string('email')->value() : null);
    }

    public function code(): ?string
    {
        return $this->filled('code') ? trim($this->string('code')->value()) : null;
    }
}
