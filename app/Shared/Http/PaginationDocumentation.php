<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use ReflectionNamedType;

/**
 * Says in each list endpoint's API documentation how it is paged, read from its request class (section 11).
 *
 * Scramble already shows the parameters and the response's shape; this adds
 * the sentence a reader looks for, so the two kinds of list can't be confused.
 */
final class PaginationDocumentation extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $sentence = match ($this->listRequestOf($routeInfo)) {
            PageListRequest::class => 'Paged by page number: pass `page` (from 1); `meta` has `total` and `last_page`.',
            CursorListRequest::class => 'Paged by cursor: pass the `next_cursor` or `prev_cursor` from `meta` as `cursor`; there is no total.',
            default => null,
        };

        if ($sentence === null) {
            return;
        }

        $sizes = sprintf(' Up to %d records per page (`per_page`, default %d).', config()->integer('api.pagination.max_per_page'), config()->integer('api.pagination.default_per_page'));

        $operation->description(trim($operation->description."\n\n".$sentence.$sizes));
    }

    /**
     * @return class-string<PaginatedListRequest>|null
     */
    private function listRequestOf(RouteInfo $routeInfo): ?string
    {
        foreach ($routeInfo->reflectionMethod()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            foreach ([PageListRequest::class, CursorListRequest::class] as $listRequest) {
                if (is_a($type->getName(), $listRequest, true)) {
                    return $listRequest;
                }
            }
        }

        return null;
    }
}
