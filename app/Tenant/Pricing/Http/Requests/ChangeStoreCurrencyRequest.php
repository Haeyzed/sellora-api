<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Requests;

use App\Shared\Money\Decimal;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changing one of the store's currencies. Needs the pricing.manage permission. Send only what changes.
 */
final class ChangeStoreCurrencyRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', TaxClass::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** False stops customers buying in it; nothing is deleted. Reactivating counts towards the plan's limit. */
            'is_active' => ['sometimes', 'boolean:strict'],
            /** The margin added to the market rate, a percentage string from "0" to "25" with up to 4 decimal places. */
            'margin_percent' => ['sometimes', 'string', 'regex:'.AddStoreCurrencyRequest::MARGIN_FORMAT, 'numeric', 'max:25'],
        ];
    }

    public function isActive(): ?bool
    {
        return $this->has('is_active') ? $this->boolean('is_active') : null;
    }

    public function marginPercent(): ?Decimal
    {
        return $this->has('margin_percent') ? Decimal::of($this->string('margin_percent')->value()) : null;
    }
}
