<?php

declare(strict_types=1);

namespace App\Shared\Retention;

use App\Shared\Retention\Contracts\RetentionPolicy;
use Illuminate\Contracts\Container\Container;

/**
 * The one list of every retention policy, so the daily purge covers every feature that stores personal data.
 *
 * Domains and modules register their policies from their service providers.
 */
final class RetentionRegistry
{
    /**
     * @var list<class-string<RetentionPolicy>>
     */
    private array $policies = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Adds a retention policy to the daily purge.
     *
     * @param  class-string<RetentionPolicy>  $policy
     */
    public function register(string $policy): void
    {
        if (! in_array($policy, $this->policies, true)) {
            $this->policies[] = $policy;
        }
    }

    /**
     * The policies that apply to the current database: a store's own, or the central one.
     *
     * @return list<RetentionPolicy>
     */
    public function policiesFor(bool $isTenantContext): array
    {
        $applicablePolicies = [];

        foreach ($this->policies as $policyClass) {
            $policy = $this->container->make($policyClass);

            if ($policy->scope()->appliesTo($isTenantContext)) {
                $applicablePolicies[] = $policy;
            }
        }

        return $applicablePolicies;
    }
}
