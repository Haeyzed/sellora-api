<?php

declare(strict_types=1);

namespace App\Shared\Features\Http\Middleware;

use App\Shared\Features\Exceptions\FeatureNotEnabledException;
use App\Shared\Features\FeatureKind;
use App\Shared\Features\FeatureRegistry;
use App\Shared\Features\Features;
use App\Shared\Features\FeatureState;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request into a module's or integration's routes only when the store's plan and status allow it.
 *
 * - Enabled: everything is allowed.
 * - Locked (removed by a downgrade): existing data can still be read, and the
 *   wind-down routes the feature declares stay open.
 * - Suspended: only the wind-down routes stay open, so customers can finish
 *   what they started.
 * - Disabled or Unavailable: nothing is allowed.
 */
abstract class EnsureFeatureIsUsable
{
    private const array READ_ONLY_METHODS = ['GET', 'HEAD'];

    public function __construct(
        private readonly Features $features,
        private readonly FeatureRegistry $featureRegistry,
    ) {}

    /**
     * Whether this middleware guards modules or integrations.
     */
    abstract protected function guardedKind(): FeatureKind;

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws FeatureNotEnabledException When the store may not use the feature for this request.
     * @throws LogicException When the route names an unknown feature, or a module through the integration middleware (or the reverse).
     */
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $definition = $this->featureRegistry->get($featureKey);

        if ($definition->kind !== $this->guardedKind()) {
            throw new LogicException("\"{$featureKey}\" is a {$definition->kind->value}; guard it with the {$definition->kind->value} middleware.");
        }

        $state = $this->features->state($featureKey);

        if ($this->allowsRequest($state, $request, $definition->isWindDownRoute($request->route()?->getName()))) {
            return $next($request);
        }

        throw new FeatureNotEnabledException($featureKey, $state);
    }

    private function allowsRequest(FeatureState $state, Request $request, bool $isWindDownRoute): bool
    {
        if ($state === FeatureState::Enabled) {
            return true;
        }

        if ($state->allowsWindDown() && $isWindDownRoute) {
            return true;
        }

        return $state->allowsReading() && in_array($request->getMethod(), self::READ_ONLY_METHODS, true);
    }
}
