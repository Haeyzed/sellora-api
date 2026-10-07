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
            'status' => $this->resource->status,
            /** @var list<string> The store's domains, such as "ada-fabrics.sellora.test". */
            'domains' => $this->whenLoaded('domains', fn (): array => array_values($this->resource->domains->map(static fn (Domain $domain): string => $domain->domain)->all())),
            'hosting_region' => $this->resource->hosting_region,
            /** Null once the store is purged: the owner's personal data is cleared. */
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
            /** Set while the store is closed; null otherwise. */
            'closure' => $this->resource->closed_at === null ? null : [
                'closed_at' => $this->resource->closed_at->toIso8601String(),
                'reason' => $this->resource->closure_reason,
                /** Who closed it: "platform_admin" or "staff_member" (the owner), and that account's ID. */
                'closed_by' => ['type' => $this->resource->closed_by_type, 'id' => $this->resource->closed_by_id],
                /** The status a restore returns it to. */
                'status_before_closing' => $this->resource->status_before_closing,
                /** After this date its data may be deleted for good. */
                'purge_after' => $this->resource->purge_after?->toIso8601String(),
            ],
            'provisioned_at' => $this->resource->provisioned_at?->toIso8601String(),
            /** When its data was deleted for good. */
            'purged_at' => $this->resource->purged_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
