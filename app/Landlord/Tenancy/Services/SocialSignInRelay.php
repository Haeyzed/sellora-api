<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Services;

use App\Landlord\Tenancy\Models\SocialSignInState;
use App\Landlord\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * The central relay page's decision (Step 10b): which store a provider's answer goes back to, from the one-time state alone.
 *
 * A state is relayed once, before it expires, for the provider it was made
 * for, to a store that serves requests; the answer goes only to that store's
 * own domain on the platform, read from the database. Nothing in the request
 * other than the state chooses where it goes.
 */
final readonly class SocialSignInRelay
{
    /**
     * Claims the state for relaying, once, even when two requests bring it at the same moment.
     *
     * @return string|null The store's own domain, or null when the state isn't one to relay.
     */
    public function claim(string $provider, string $state): ?string
    {
        if ($state === '' || strlen($state) > 128) {
            return null;
        }

        return SocialSignInState::query()->getConnection()->transaction(static function () use ($provider, $state): ?string {
            $route = SocialSignInState::query()->where('state_hash', SocialSignInState::hash($state))->lockForUpdate()->first();

            if ($route === null || $route->provider !== $provider || $route->used_at !== null || ! $route->expires_at->isFuture()) {
                return null;
            }

            $route->forceFill(['used_at' => CarbonImmutable::now()])->save();
            $store = Tenant::query()->find($route->tenant_id);

            return $store !== null && $store->status->servesRequests() ? $store->platformDomain() : null;
        });
    }

    /**
     * The storefront's sign-in callback page on the store's own domain, with what the provider sent in the URL fragment, which browsers never send to servers.
     *
     * @param  array<string, string>  $fragment
     */
    public function storefrontUrl(string $storeDomain, array $fragment): string
    {
        return strtr(config()->string('customers.social.storefront_callback_url'), ['{domain}' => $storeDomain]).'#'.http_build_query($fragment, '', '&', PHP_QUERY_RFC3986);
    }
}
