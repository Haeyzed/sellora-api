<?php

declare(strict_types=1);

namespace App\Shared\Features;

use LogicException;

/**
 * The list of every module and integration installed in the codebase, filled in by their service providers.
 *
 * Every provider is always registered, whatever the current store's plan;
 * which store may use what is decided at runtime by Features.
 */
final class FeatureRegistry
{
    /**
     * @var array<string, FeatureDefinition>
     */
    private array $definitions = [];

    /**
     * Adds a module or integration.
     *
     * @throws LogicException When another feature already uses the key.
     */
    public function register(FeatureDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key])) {
            throw new LogicException("The feature key \"{$definition->key}\" is registered twice.");
        }

        $this->definitions[$definition->key] = $definition;
    }

    /**
     * Whether a module or integration with this key is installed.
     */
    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    /**
     * The definition of an installed module or integration.
     *
     * @throws LogicException When no feature uses the key, which means a route or job names a feature that doesn't exist.
     */
    public function get(string $key): FeatureDefinition
    {
        return $this->definitions[$key] ?? throw new LogicException("No module or integration is registered with the key \"{$key}\".");
    }

    /**
     * Every installed module and integration, by key.
     *
     * @return array<string, FeatureDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }
}
