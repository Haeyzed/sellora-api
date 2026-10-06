<?php

declare(strict_types=1);

namespace App\Shared\Features;

use Carbon\CarbonImmutable;

/**
 * Everything the platform knows about one store's plan at a moment in time: which features it has, has had, and its usage limits.
 *
 * Produced by the FeatureSource (the platform's subscriptions) and cached per
 * store, so checking a feature never needs a database query.
 */
final readonly class FeatureSnapshot
{
    /**
     * @param  list<string>  $entitledFeatureKeys  Features the store's current plan includes.
     * @param  list<string>  $removedFeatureKeys  Features a plan change took away from the store; while not on the plan they are locked.
     * @param  list<string>  $merchantDisabledFeatureKeys  Features the merchant switched off.
     * @param  array<string, int|null>  $limits  Usage limits by key; null means unlimited.
     * @param  CarbonImmutable|null  $changesAt  When the snapshot stops being accurate on its own, for example when a temporary limit override expires.
     */
    public function __construct(
        public bool $isStoreSuspended,
        public array $entitledFeatureKeys,
        public array $removedFeatureKeys,
        public array $merchantDisabledFeatureKeys,
        public array $limits,
        public ?CarbonImmutable $changesAt = null,
    ) {}

    /**
     * Rebuilds a snapshot from its cached form.
     *
     * @param  array{suspended: bool, entitled: list<string>, removed: list<string>, merchant_disabled: list<string>, limits: array<string, int|null>, changes_at: int|null}  $cached
     */
    public static function fromCache(array $cached): self
    {
        return new self(
            isStoreSuspended: $cached['suspended'],
            entitledFeatureKeys: $cached['entitled'],
            removedFeatureKeys: $cached['removed'],
            merchantDisabledFeatureKeys: $cached['merchant_disabled'],
            limits: $cached['limits'],
            changesAt: $cached['changes_at'] === null ? null : CarbonImmutable::createFromTimestamp($cached['changes_at']),
        );
    }

    /**
     * The snapshot as plain values, because the cache refuses to store PHP objects (a defence against tampered cache data).
     *
     * @return array{suspended: bool, entitled: list<string>, removed: list<string>, merchant_disabled: list<string>, limits: array<string, int|null>, changes_at: int|null}
     */
    public function toCache(): array
    {
        return [
            'suspended' => $this->isStoreSuspended,
            'entitled' => $this->entitledFeatureKeys,
            'removed' => $this->removedFeatureKeys,
            'merchant_disabled' => $this->merchantDisabledFeatureKeys,
            'limits' => $this->limits,
            'changes_at' => $this->changesAt?->getTimestamp(),
        ];
    }

    /**
     * A snapshot for a store with no plan at all: every feature is unavailable and every limit is zero.
     */
    public static function withoutPlan(): self
    {
        return new self(
            isStoreSuspended: false,
            entitledFeatureKeys: [],
            removedFeatureKeys: [],
            merchantDisabledFeatureKeys: [],
            limits: [],
        );
    }
}
