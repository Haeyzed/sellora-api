<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use App\Landlord\Subscriptions\Data\LimitOverrideValue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A store's own value for one usage limit, why, and until when.
 *
 * Either a number in "value", or "unlimited": true on purpose. A missing or
 * null value is refused, never read as unlimited (section 8).
 */
final class SetLimitOverrideRequest extends FormRequest
{
    use StoreAbilityRequest;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** How many the store may have. Required unless unlimited is true; not allowed with it. */
            'value' => ['required_unless:unlimited,true', 'prohibited_if:unlimited,true', 'integer', 'min:0', 'max:2147483647'],
            /** True to remove the limit for this store. The only way to make it unlimited. */
            'unlimited' => ['sometimes', 'boolean'],
            /** For the platform team, such as "Agreed for their seasonal peak". A business reason only: never personal data such as health, family or contact details, because the platform keeps it in its activity and audit logs until their retention period ends. */
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            /** When the plan's limit applies again, ISO 8601. Left out for no end date. */
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }

    public function overrideValue(): LimitOverrideValue
    {
        return $this->boolean('unlimited') ? LimitOverrideValue::unlimited() : LimitOverrideValue::of($this->integer('value'));
    }

    public function reason(): string
    {
        return trim($this->string('reason')->value());
    }

    public function expiresAt(): ?CarbonImmutable
    {
        return $this->filled('expires_at') ? CarbonImmutable::parse($this->string('expires_at')->value()) : null;
    }

    protected function ability(): string
    {
        return 'grant';
    }
}
