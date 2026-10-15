<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Requests;

use App\Shared\Geography\Geography;
use App\Shared\Geography\Rules\CountryCode;
use App\Shared\Geography\Rules\StateCode;
use App\Shared\Money\Money;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Pricing\Models\StoreCurrency;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use App\Tenant\Shipping\Data\ShippingMethodDetails;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use App\Tenant\Shipping\Enums\ShippingPricing;
use App\Tenant\Shipping\Models\ShippingMethod;
use App\Tenant\Shipping\Models\ShippingZone;
use App\Tenant\Shipping\ShippingCarrierRegistry;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Adding a shipping method, or changing one (send only what changes). Needs the shipping.manage permission.
 *
 * How it reaches the shopper (fulfilment) and how it is priced are set once.
 * Amounts are whole minor units of the store's base currency; weights are
 * grams. Sending "rates" or "currency_amounts" replaces every one set before.
 */
final class SaveShippingMethodRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesStoreTranslations;

    public const int MAX_RATES = 50;

    public const int MAX_CURRENCY_AMOUNTS = 20;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', ShippingZone::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->shippingMethod() === null;
        $pickup = $this->fulfilment() === ShippingFulfilment::Pickup;
        $carrier = $this->pricing() === ShippingPricing::Carrier;
        $amount = ['integer:strict', 'min:0'];

        return [
            /** "ship" (a carrier the merchant books), "own_delivery" (the store's own drivers) or "pickup" (collected at one of the store's locations). Set once. */
            'fulfilment' => $creating ? ['required', Rule::enum(ShippingFulfilment::class)] : ['missing'],
            /** "flat" (one price), "weight" (brackets of the total weight in grams of the lines that ship), "order_value" (brackets of their value after discounts, as displayed, in the base currency's smallest unit) or "carrier" (a carrier's live rate, for "ship" only, quoted once the full address is known). A pickup is flat. Set once. */
            'pricing' => $creating ? ['required', Rule::enum(ShippingPricing::class), ...($pickup ? [Rule::in([ShippingPricing::Flat->value])] : []), ...($this->fulfilment() === ShippingFulfilment::OwnDelivery ? [Rule::notIn([ShippingPricing::Carrier->value])] : [])] : ['missing'],
            /** A carrier-priced method's carrier, by its key; one whose integration is enabled for the store. Set once. */
            'carrier' => [...($creating && $carrier ? ['required'] : ['missing']), 'string', $this->usableCarrier(...)],
            /** One of the carrier's service codes. */
            'carrier_service' => [...($carrier ? [$creating ? 'required' : 'sometimes'] : ['missing']), 'string', $this->serviceOfCarrier(...)],
            /** A carrier-priced method's price when the carrier can't quote (it is down or too slow), in the base currency's smallest unit; null to stop offering it then. */
            'backup' => [...($carrier ? ['sometimes'] : ['missing']), 'nullable', 'integer:strict', 'min:0'],
            /** The zone it ships within, by ID. Not for a pickup, which is offered to shoppers in its location's country. */
            'zone' => $pickup ? ['missing'] : [$creating ? 'required' : 'sometimes', 'string', Rule::exists(ShippingZone::class, 'public_id')],
            /** What shoppers see, in the store's languages, such as {"en": "Standard delivery"}. */
            'name' => [$creating ? 'required' : 'sometimes', 'array', $this->storeTranslatedText(100, requiresDefault: true)],
            /** More for shoppers, such as {"en": "2 to 4 working days"}; null to remove. */
            'description' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(500)],
            /** Free once the lines that ship are worth this much, after discounts, as displayed, in the base currency's smallest unit; null for never. Not for a pickup. */
            'free_over' => $pickup ? ['missing'] : ['sometimes', 'nullable', 'integer:strict', 'min:1'],
            /** Exact "free over" thresholds (and, for a carrier, backup prices) in other currencies the store offers, used instead of converting. Replaces every one set before. Not for a pickup. */
            'currency_amounts' => $pickup ? ['missing'] : ['sometimes', 'array', 'max:'.self::MAX_CURRENCY_AMOUNTS],
            /** ISO 4217: one of the store's other currencies. */
            'currency_amounts.*.currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/', 'distinct', $this->offeredCurrency(...)],
            'currency_amounts.*.free_over' => ['sometimes', 'nullable', 'integer:strict', 'min:1'],
            'currency_amounts.*.backup' => [...($carrier ? ['sometimes'] : ['missing']), 'nullable', 'integer:strict', 'min:0'],
            /** Its price brackets, replacing every one set before. Flat: one bracket from 0 with no end. Weight and order value: brackets that never overlap; a gap means it isn't offered there (the response warns about gaps). */
            'rates' => $carrier ? ['missing'] : [$creating ? 'required' : 'sometimes', 'array', 'min:1', 'max:'.self::MAX_RATES],
            /** Where the bracket starts, included: grams, or the base currency's smallest unit. */
            'rates.*.from' => ['required', ...$amount],
            /** Where it ends, not included; null for "and up". */
            'rates.*.to' => ['present', 'nullable', 'integer:strict', 'min:1'],
            /** The price in the base currency's smallest unit; 0 for free. */
            'rates.*.amount' => ['required', ...$amount],
            /** Exact prices in other currencies the store offers, used instead of converting, like fixed prices. */
            'rates.*.currency_amounts' => ['sometimes', 'array', 'max:'.self::MAX_CURRENCY_AMOUNTS],
            'rates.*.currency_amounts.*.currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/', $this->offeredCurrency(...)],
            'rates.*.currency_amounts.*.amount' => ['required', ...$amount],
            /** A pickup's location and address, replaced whole when sent. Only for a pickup. */
            'pickup' => $pickup ? [$creating ? 'required' : 'sometimes', 'array'] : ['missing'],
            /** The stock location orders are collected from, by ID. */
            'pickup.stock_location' => ['required_with:pickup', 'string', Rule::exists(StockLocation::class, 'public_id')],
            /** ISO 3166-1 alpha-2; it is offered to shoppers in this country. */
            'pickup.country_code' => ['required_with:pickup', 'string', new CountryCode],
            /** The state, from the country's states list where it has one, else free text. */
            'pickup.state_code' => ['sometimes', 'nullable', 'string', StateCode::forAddress($this->pickupCountryCode())],
            'pickup.address_line1' => ['required_with:pickup', 'string', 'max:255'],
            'pickup.city' => ['required_with:pickup', 'string', 'max:100'],
            'pickup.postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            /** For shoppers, such as {"en": "Open 9 to 5, Monday to Friday"}. */
            'pickup.instructions' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(500)],
            /** Lower first, among the methods offered together. */
            'position' => ['sometimes', 'integer:strict', 'min:0', 'max:10000'],
            /** Whether shoppers are offered it. Active when left out. */
            'is_active' => ['sometimes', 'boolean:strict'],
        ];
    }

    /**
     * Brackets that make sense for the pricing and never overlap; each exact price once per currency.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('currency_amounts', []) as $index => $amounts) {
                    if (is_array($amounts) && ($amounts['free_over'] ?? null) === null && ($amounts['backup'] ?? null) === null) {
                        $validator->errors()->add("currency_amounts.{$index}", __('validation.required', ['attribute' => 'an amount']));
                    }
                }
            },
            function (Validator $validator): void {
                if (! $this->has('rates') || $validator->errors()->hasAny(['rates', 'rates.*'])) {
                    return;
                }

                $rates = $this->rates();

                foreach ($rates as $index => $rate) {
                    if ($rate['to'] !== null && $rate['to'] <= $rate['from']) {
                        $validator->errors()->add("rates.{$index}.to", __('validation.gt.numeric', ['attribute' => 'to', 'value' => 'from']));
                    }

                    if (count($rate['currency_amounts']) !== count((array) $this->input("rates.{$index}.currency_amounts", []))) {
                        $validator->errors()->add("rates.{$index}.currency_amounts", __('validation.distinct', ['attribute' => 'currency']));
                    }
                }

                if ($this->pricing() === ShippingPricing::Flat && (count($rates) !== 1 || $rates[0]['from'] !== 0 || $rates[0]['to'] !== null)) {
                    $validator->errors()->add('rates', __('validation.custom.rates.flat'));
                }

                if ($validator->errors()->has('rates.*') || $validator->errors()->has('rates')) {
                    return;
                }

                $sorted = $rates;
                usort($sorted, static fn (array $one, array $other): int => $one['from'] <=> $other['from']);

                for ($index = 1, $count = count($sorted); $index < $count; $index++) {
                    $previousEnd = $sorted[$index - 1]['to'];

                    if ($previousEnd === null || $sorted[$index]['from'] < $previousEnd) {
                        $validator->errors()->add('rates', __('errors.shipping_rates_overlap'));

                        return;
                    }
                }
            },
        ];
    }

    public function details(): ShippingMethodDetails
    {
        $fields = [];
        $baseCurrency = app(FindStoreSettings::class)->handle()->currency_code;

        if ($this->shippingMethod() === null) {
            $fields['fulfilment'] = $this->fulfilment();
            $fields['pricing'] = $this->pricing();
            $fields['currency'] = $baseCurrency;
        }

        if ($this->has('zone')) {
            $fields['shipping_zone_id'] = (int) ShippingZone::query()->where('public_id', $this->string('zone')->value())->value('id');
        }

        if ($this->shippingMethod() === null && $this->filled('carrier')) {
            $fields['carrier'] = $this->string('carrier')->value();
        }

        if ($this->has('carrier_service')) {
            $fields['carrier_service'] = $this->string('carrier_service')->value();
        }

        if ($this->has('backup')) {
            $fields['backup'] = $this->filled('backup') ? Money::ofMinor($this->integer('backup'), $this->shippingMethod()->currency ?? $baseCurrency) : null;
        }

        if ($this->has('free_over')) {
            $fields['free_over'] = $this->filled('free_over') ? Money::ofMinor($this->integer('free_over'), $this->shippingMethod()->currency ?? $baseCurrency) : null;
        }

        foreach (['position', 'is_active'] as $key) {
            if ($this->has($key)) {
                $fields[$key] = $this->input($key);
            }
        }

        $translations = [];

        foreach (['name' => 'name', 'description' => 'description', 'pickup.instructions' => 'pickup_instructions'] as $input => $key) {
            if ($this->has($input)) {
                /** @var array<string, string|null>|null $texts */
                $texts = $this->input($input);
                $translations[$key] = $texts ?? array_fill_keys(array_keys($this->shippingMethod()?->getTranslations($key) ?? []), null);
            }
        }

        if ($this->has('pickup')) {
            $countryCode = (string) $this->pickupCountryCode();
            $state = $this->input('pickup.state_code');
            $fields['stock_location_id'] = (int) StockLocation::query()->where('public_id', $this->string('pickup.stock_location')->value())->value('id');
            $fields['pickup_country_code'] = $countryCode;
            $fields['pickup_state_code'] = is_string($state) && $state !== '' ? app(Geography::class)->stateCode($countryCode, $state) : null;
            $fields['pickup_address_line1'] = trim($this->string('pickup.address_line1')->value());
            $fields['pickup_city'] = trim($this->string('pickup.city')->value());
            $fields['pickup_postal_code'] = $this->filled('pickup.postal_code') ? trim($this->string('pickup.postal_code')->value()) : null;
        }

        $currencyAmounts = null;

        if ($this->has('currency_amounts')) {
            $currencyAmounts = [];

            foreach ($this->array('currency_amounts') as $row) {
                $currencyAmounts[(string) $row['currency']] = ['free_over' => isset($row['free_over']) ? (int) $row['free_over'] : null, 'backup' => isset($row['backup']) ? (int) $row['backup'] : null];
            }
        }

        return new ShippingMethodDetails($fields, $translations, $this->has('rates') ? $this->rates() : null, $currencyAmounts);
    }

    /**
     * The brackets sent, with exact prices by currency (a currency sent twice counts once).
     *
     * @return list<array{from: int, to: int|null, amount: int, currency_amounts: array<string, int>}>
     */
    private function rates(): array
    {
        return array_values(array_map(static function (mixed $rate): array {
            $rate = (array) $rate;
            $currencyAmounts = [];

            foreach ((array) ($rate['currency_amounts'] ?? []) as $exact) {
                $exact = (array) $exact;
                $currencyAmounts[(string) $exact['currency']] = (int) $exact['amount'];
            }

            return ['from' => (int) $rate['from'], 'to' => isset($rate['to']) ? (int) $rate['to'] : null, 'amount' => (int) $rate['amount'], 'currency_amounts' => $currencyAmounts];
        }, $this->array('rates')));
    }

    /**
     * A carrier an integration offers, usable in the store now.
     */
    private function usableCarrier(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || app(ShippingCarrierRegistry::class)->enabled($value) === null) {
            $fail(__('validation.custom.carrier.not_usable'));
        }
    }

    /**
     * One of the method's carrier's services (the one sent, or the method's own).
     */
    private function serviceOfCarrier(string $attribute, mixed $value, Closure $fail): void
    {
        $carrierKey = $this->shippingMethod()->carrier ?? $this->input('carrier');
        $carrier = is_string($carrierKey) ? app(ShippingCarrierRegistry::class)->enabled($carrierKey) : null;

        if ($carrier !== null && (! is_string($value) || ! array_key_exists($value, $carrier->services()))) {
            $fail(__('validation.custom.carrier.unknown_service'));
        }
    }

    private function fulfilment(): ?ShippingFulfilment
    {
        return $this->shippingMethod()->fulfilment ?? (is_string($this->input('fulfilment')) ? ShippingFulfilment::tryFrom($this->string('fulfilment')->value()) : null);
    }

    private function pricing(): ?ShippingPricing
    {
        return $this->shippingMethod()->pricing ?? (is_string($this->input('pricing')) ? ShippingPricing::tryFrom($this->string('pricing')->value()) : null);
    }

    private function pickupCountryCode(): ?string
    {
        $countryCode = $this->input('pickup.country_code');

        return is_string($countryCode) && app(Geography::class)->country($countryCode) !== null ? $countryCode : null;
    }

    /**
     * One of the store's currencies other than its base currency, which never has a store currency row.
     */
    private function offeredCurrency(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! StoreCurrency::query()->where('currency_code', $value)->exists()) {
            $fail(__('validation.custom.currency_amounts.not_offered'));
        }
    }

    private function shippingMethod(): ?ShippingMethod
    {
        $method = $this->route('method');

        return $method instanceof ShippingMethod ? $method : null;
    }
}
