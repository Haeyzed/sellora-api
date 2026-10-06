<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Listeners;

use Sentry\State\Scope;
use Stancl\Tenancy\Events\RevertedToCentralContext;

use function Sentry\configureScope;

/**
 * Stops labelling error reports with a store once that store's work is finished.
 *
 * Prevents a queue worker's next job from being reported under the wrong store.
 */
final class RemoveTenantTagFromErrorReportsWhenTenancyEnds
{
    /**
     * Removes the store tag from all error reports sent from now on.
     */
    public function handle(RevertedToCentralContext $event): void
    {
        configureScope(static function (Scope $scope): void {
            $scope->removeTag('tenant_id');
        });
    }
}
