<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Services;

use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Keeps what the storefront shows shoppers in the store's cache, so busy storefronts don't rebuild every page on every visit.
 *
 * Every entry is filed under the store's current content version. Any
 * change to the content gives the store a new version, so the next visit
 * sees it at once and old entries simply expire. Every call goes through the
 * Cache facade, which the tenancy package files under the store's own tag,
 * so one store never reads another's entries.
 */
final readonly class StorefrontContentCache
{
    private const string VERSION_KEY = 'storefront.content_version';

    /**
     * What was cached under this key for the current content, or what the closure builds, cached until the time given or for config storefront.cache_seconds.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $build
     * @return TValue
     */
    public function remember(string $key, Closure $build, ?DateTimeInterface $until = null): mixed
    {
        return Cache::remember($this->versioned($key), $until ?? config()->integer('storefront.cache_seconds'), $build);
    }

    /**
     * Gives the store a new content version, so every cached entry is built again on its next read.
     */
    public function renew(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::ulid());
    }

    private function versioned(string $key): string
    {
        $version = Cache::get(self::VERSION_KEY);

        return 'storefront.'.(is_string($version) ? $version : 'initial').'.'.$key;
    }
}
