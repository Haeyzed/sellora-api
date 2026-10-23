<?php

declare(strict_types=1);

namespace App\Shared\Idempotency;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Which routes prepare their replayed responses, and with what, as each domain registers them. This knows none of them.
 */
final class ReplayPreparers
{
    /** @var array<string, class-string<ReplayPreparer>> */
    private array $preparers = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<ReplayPreparer>  $preparer
     */
    public function register(string $routeName, string $preparer): void
    {
        $this->preparers[$routeName] = $preparer;
    }

    /**
     * Lets the route's preparer, if it has one, add to a replayed response.
     */
    public function prepare(Request $request, Response $replayed): void
    {
        $routeName = $request->route()?->getName();
        $class = $routeName === null ? null : ($this->preparers[$routeName] ?? null);

        if ($class === null) {
            return;
        }

        $preparer = $this->container->make($class);
        $preparer instanceof ReplayPreparer ? $preparer->prepare($request, $replayed) : throw new LogicException("{$class} is not a replay preparer.");
    }
}
