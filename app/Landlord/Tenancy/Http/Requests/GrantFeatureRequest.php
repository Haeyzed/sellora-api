<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use App\Shared\Features\FeatureRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * Which module or integration to give a store, why, and until when.
 */
final class GrantFeatureRequest extends FormRequest
{
    use StoreAbilityRequest;

    /**
     * @return array<string, list<string|In>>
     */
    public function rules(): array
    {
        return [
            /** The key of an installed module or integration, such as "loyalty". */
            'feature' => ['required', 'string', Rule::in(array_keys(app(FeatureRegistry::class)->all()))],
            /** For the platform team, such as "Free for the launch month". A business reason only: never personal data such as health, family or contact details, because the platform keeps it in its activity and audit logs until their retention period ends. */
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            /** When the grant ends, ISO 8601. Left out for no end date. */
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }

    public function featureKey(): string
    {
        return $this->string('feature')->value();
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
