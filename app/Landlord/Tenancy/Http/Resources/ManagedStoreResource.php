<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Resources;

use App\Landlord\Tenancy\Models\Domain;
use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A store as Sellora's team sees it.
 *
 * @property Tenant $resource
 */
final class ManagedStoreResource extends JsonResource
{
    public function __construct(Tenant $store)
    {
        parent::__construct($store);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            'name' => $this->resource->name,
            /** "provisioning", "provisioning_failed", "active" or "suspended". */
            'status' => $this->resource->status->value,
            'domains' => $this->whenLoaded('domains', fn (): array => array_values($this->resource->domains->map(static fn (Domain $domain): string => $domain->domain)->all())),
            'hosting_region' => $this->resource->hosting_region,
            'owner' => [
                'name' => $this->resource->owner_name,
                'email' => $this->resource->owner_email,
            ],
            'country_code' => $this->resource->country_code,
            'currency_code' => $this->resource->currency_code,
            'timezone' => $this->resource->timezone,
            'plan' => $this->whenLoaded('subscription', fn (): ?array => $this->resource->subscription === null ? null : [
                'code' => $this->resource->subscription->plan->code,
                'name' => $this->resource->subscription->plan->name,
                /** The subscription's own status, such as "active" or "past_due". */
                'subscription_status' => $this->resource->subscription->status->value,
            ]),
            'database_server' => $this->whenLoaded('databaseServer', fn (): ?string => $this->resource->databaseServer?->name),
            'suspended_at' => $this->resource->suspended_at?->toIso8601String(),
            /** Why it was suspended, for the platform team only. */
            'suspension_reason' => $this->resource->suspension_reason,
            'provisioned_at' => $this->resource->provisioned_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
