<?php

declare(strict_types=1);

namespace App\Shared\Features;

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Exceptions\FeatureNotEnabledException;
use App\Shared\Features\Exceptions\UsageLimitReachedException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use LogicException;

/**
 * The one place that answers "may this store use this module or integration, and how many of these may it create?".
 *
 * Routes, policies, Actions, jobs and listeners all ask here, never the plan
 * or subscription tables directly. Each store's plan is cached per store and
 * cleared whenever its subscription changes.
 */
final readonly class Features
{
    public function __construct(
        private FeatureSource $featureSource,
        private FeatureRegistry $featureRegistry,
        private CacheFactory $cacheFactory,
        private ConfigRepository $config,
    ) {}

    /**
     * The state of a module or integration for the current store. Outside a store, nothing is available.
     *
     * @throws LogicException When no module or integration uses the key.
     */
    public function state(string $featureKey): FeatureState
    {
        $tenantId = $this->currentTenantId();

        if ($tenantId === null) {
            $this->featureRegistry->get($featureKey);

            return FeatureState::Unavailable;
        }

        return $this->stateFor($tenantId, $featureKey);
    }

    /**
     * The state of a module or integration for a given store, for example when a platform admin reviews a store.
     *
     * A feature is never more open than the features it depends on: payroll is
     * locked whenever HR is locked.
     *
     * @throws LogicException When no module or integration uses the key, or dependencies form a loop.
     */
    public function stateFor(string $tenantId, string $featureKey): FeatureState
    {
        return $this->resolveState($this->snapshotFor($tenantId), $featureKey, []);
    }

    /**
     * Whether the current store has full use of a module or integration.
     */
    public function isEnabled(string $featureKey): bool
    {
        return $this->state($featureKey) === FeatureState::Enabled;
    }

    /**
     * Stops a business operation when the current store may not use the feature, for example inside a module's Action or job.
     *
     * @throws FeatureNotEnabledException When the feature is not enabled.
     */
    public function ensureEnabled(string $featureKey): void
    {
        $state = $this->state($featureKey);

        if ($state !== FeatureState::Enabled) {
            throw new FeatureNotEnabledException($featureKey, $state);
        }
    }

    /**
     * How many of something the current store's plan allows; null means unlimited.
     *
     * A limit the plan doesn't set allows none, so a forgotten limit never means
     * unlimited use. Outside a store, the limit is zero.
     *
     * @throws LogicException When the limit key is not listed in config/features.php.
     */
    public function limit(string $limitKey): ?int
    {
        $tenantId = $this->currentTenantId();

        if ($tenantId === null) {
            $this->ensureKnownLimit($limitKey);

            return 0;
        }

        return $this->limitFor($tenantId, $limitKey);
    }

    /**
     * How many of something a given store's plan allows, for example when a platform admin reviews a store; null means unlimited.
     *
     * @throws LogicException When the limit key is not listed in config/features.php.
     */
    public function limitFor(string $tenantId, string $limitKey): ?int
    {
        $this->ensureKnownLimit($limitKey);

        $limits = $this->snapshotFor($tenantId)->limits;

        return array_key_exists($limitKey, $limits) ? $limits[$limitKey] : 0;
    }

    /**
     * Stops the creation of one more of something when the store already uses all its plan allows.
     *
     * Call it in the Action that creates the thing, with the current count.
     * Being over a limit after a downgrade never deletes anything; it only
     * blocks creating more.
     *
     * @throws UsageLimitReachedException When the store is at or over its limit.
     * @throws LogicException When the limit key is not listed in config/features.php.
     */
    public function ensureWithinLimit(string $limitKey, int $currentUsage): void
    {
        $limit = $this->limit($limitKey);

        if ($limit !== null && $currentUsage >= $limit) {
            throw new UsageLimitReachedException($limitKey, $limit);
        }
    }

    /**
     * Clears a store's cached plan, so the next check reads it fresh. Called whenever its subscription, plan or overrides change.
     */
    public function forget(string $tenantId): void
    {
        $this->cache()->forget($this->cacheKey($tenantId));
    }

    private function snapshotFor(string $tenantId): FeatureSnapshot
    {
        $cached = $this->cache()->get($this->cacheKey($tenantId));

        if (is_array($cached)) {
            /** @var array{suspended: bool, entitled: list<string>, removed: list<string>, merchant_disabled: list<string>, limits: array<string, int|null>, changes_at: int|null} $cached */
            return FeatureSnapshot::fromCache($cached);
        }

        $snapshot = $this->featureSource->snapshotFor($tenantId);
        $this->cache()->put($this->cacheKey($tenantId), $snapshot->toCache(), $this->cacheLifetimeInSeconds($snapshot));

        return $snapshot;
    }

    /**
     * @param  list<string>  $dependentFeatureKeys  The features that led here, to detect dependency loops.
     */
    private function resolveState(FeatureSnapshot $snapshot, string $featureKey, array $dependentFeatureKeys): FeatureState
    {
        if (in_array($featureKey, $dependentFeatureKeys, true)) {
            throw new LogicException("The feature \"{$featureKey}\" depends on itself through: ".implode(' → ', $dependentFeatureKeys).'.');
        }

        $state = $this->ownState($snapshot, $featureKey);

        foreach ($this->featureRegistry->get($featureKey)->requiredFeatureKeys as $requiredFeatureKey) {
            $requiredState = $this->resolveState($snapshot, $requiredFeatureKey, [...$dependentFeatureKeys, $featureKey]);

            if ($requiredState->restrictiveness() > $state->restrictiveness()) {
                $state = $requiredState;
            }
        }

        return $state;
    }

    private function ownState(FeatureSnapshot $snapshot, string $featureKey): FeatureState
    {
        if ($snapshot->isStoreSuspended) {
            return FeatureState::Suspended;
        }

        if (in_array($featureKey, $snapshot->entitledFeatureKeys, true)) {
            return in_array($featureKey, $snapshot->merchantDisabledFeatureKeys, true)
                ? FeatureState::Disabled
                : FeatureState::Enabled;
        }

        return in_array($featureKey, $snapshot->removedFeatureKeys, true)
            ? FeatureState::Locked
            : FeatureState::Unavailable;
    }

    private function ensureKnownLimit(string $limitKey): void
    {
        $knownLimitKeys = (array) $this->config->get('features.limit_keys', []);

        if (! in_array($limitKey, $knownLimitKeys, true)) {
            throw new LogicException("The usage limit \"{$limitKey}\" is not listed in config/features.php.");
        }
    }

    private function cacheLifetimeInSeconds(FeatureSnapshot $snapshot): int
    {
        $maximumLifetimeInSeconds = (int) $this->config->get('features.cache_ttl_in_seconds', 600);

        if ($snapshot->changesAt === null) {
            return $maximumLifetimeInSeconds;
        }

        $secondsUntilChange = (int) CarbonImmutable::now()->diffInSeconds($snapshot->changesAt, absolute: false);

        return max(1, min($maximumLifetimeInSeconds, $secondsUntilChange));
    }

    /**
     * The untagged central cache store, deliberately: the platform side must be
     * able to clear a store's entry, and inside a store the default cache calls
     * are tagged with that store. Keys always include the store ID.
     */
    private function cache(): CacheRepository
    {
        return $this->cacheFactory->store();
    }

    private function cacheKey(string $tenantId): string
    {
        return 'features:snapshot:'.$tenantId;
    }

    private function currentTenantId(): ?string
    {
        $tenant = tenant();

        return $tenant === null ? null : (string) $tenant->getTenantKey();
    }
}
