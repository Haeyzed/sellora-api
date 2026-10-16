<?php

declare(strict_types=1);

namespace App\Shared\Integrations;

/**
 * The store and integration a webhook endpoint leads to.
 */
final readonly class WebhookEndpointTarget
{
    public function __construct(
        public string $tenantId,
        public string $integration,
    ) {}
}
