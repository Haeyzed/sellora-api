<?php

declare(strict_types=1);

namespace App\Landlord\Integrations;

use App\Landlord\Integrations\Models\WebhookEndpoint;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Shared\Integrations\Contracts\WebhookEndpoints;
use App\Shared\Integrations\WebhookEndpointTarget;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Stores' webhook endpoints, kept on the central database. Only a store whose domains serve requests (open or suspended) receives webhooks: a suspended store still finishes the payments already under way.
 */
final readonly class CentralWebhookEndpoints implements WebhookEndpoints
{
    /** Long enough that endpoint IDs can't be guessed. */
    private const int ID_LENGTH = 40;

    public function create(string $tenantId, string $integration): string
    {
        $endpoint = new WebhookEndpoint;
        $endpoint->forceFill(['public_id' => Str::lower(Str::random(self::ID_LENGTH)), 'tenant_id' => $tenantId, 'integration' => $integration])->save();

        return $endpoint->public_id;
    }

    public function find(string $endpointId): ?WebhookEndpointTarget
    {
        $servingStatuses = array_values(array_map(static fn (TenantStatus $status): string => $status->value, array_filter(TenantStatus::cases(), static fn (TenantStatus $status): bool => $status->servesRequests())));
        $endpoint = WebhookEndpoint::query()
            ->where('public_id', $endpointId)
            ->whereHas('tenant', static fn (Builder $stores) => $stores->whereIn('status', $servingStatuses))
            ->first();

        return $endpoint === null ? null : new WebhookEndpointTarget($endpoint->tenant_id, $endpoint->integration);
    }

    public function idsFor(string $tenantId, string $integration): array
    {
        return array_values(WebhookEndpoint::query()->where('tenant_id', $tenantId)->where('integration', $integration)->orderBy('id')->pluck('public_id')->all());
    }

    public function delete(string $endpointId): void
    {
        WebhookEndpoint::query()->where('public_id', $endpointId)->delete();
    }

    /**
     * On the first central domain, which the webhook routes are served on, with the scheme of the app's URL.
     */
    public function paymentWebhookUrl(string $endpointId): string
    {
        $scheme = parse_url(config()->string('app.url'), PHP_URL_SCHEME) ?: 'https';
        $centralDomain = config()->array('platform.central_domains')[0] ?? 'localhost';

        return $scheme.'://'.$centralDomain.route('webhooks.payments', ['endpoint' => $endpointId], absolute: false);
    }

    /**
     * Deletes a store's endpoints that none of its connections use, once older than the cutoff: younger ones may belong to a connection still being saved.
     *
     * @param  list<string>  $inUse
     * @return int How many were deleted.
     */
    public function pruneUnused(string $tenantId, array $inUse, CarbonImmutable $createdBefore): int
    {
        return WebhookEndpoint::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('public_id', $inUse)
            ->where('created_at', '<', $createdBefore)
            ->delete();
    }
}
