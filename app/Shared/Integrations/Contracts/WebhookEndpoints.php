<?php

declare(strict_types=1);

namespace App\Shared\Integrations\Contracts;

use App\Shared\Integrations\WebhookEndpointTarget;

/**
 * Random webhook endpoint IDs for stores' connections to providers that sign webhooks with the store's own key, kept centrally so a webhook finds its store without the URL naming it. Implemented by Landlord\Integrations.
 *
 * An endpoint is created before the store saves its connection, and deleted
 * after the store removes it: an endpoint left behind leads to no connection,
 * so a webhook to it is an ordinary 404, and it is pruned.
 */
interface WebhookEndpoints
{
    /**
     * A new endpoint ID for a store's integration.
     */
    public function create(string $tenantId, string $integration): string;

    /**
     * The store and integration an endpoint belongs to; null when there is none, or the store no longer serves requests.
     */
    public function find(string $endpointId): ?WebhookEndpointTarget;

    /**
     * Every endpoint ID a store has for an integration, to prune those its connection no longer uses.
     *
     * @return list<string>
     */
    public function idsFor(string $tenantId, string $integration): array;

    public function delete(string $endpointId): void;

    /**
     * The URL a payment gateway sends this endpoint's webhooks to, on the central domain, for the merchant to paste into the gateway's dashboard.
     */
    public function paymentWebhookUrl(string $endpointId): string;
}
