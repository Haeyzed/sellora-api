<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Configures Telescope, the local debugging dashboard.
 *
 * Only registered in the local environment (see AppServiceProvider), and even
 * there it never stores tokens, passwords or cookies.
 */
final class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Hides secrets from everything Telescope records.
     */
    public function register(): void
    {
        Telescope::hideRequestParameters(['password', 'password_confirmation', 'current_password', 'token']);

        Telescope::hideRequestHeaders(['authorization', 'cookie', 'x-csrf-token', 'x-xsrf-token']);

        Telescope::hideResponseParameters(['token', 'plain_text_token']);
    }

    /**
     * Denies Telescope access to everyone outside the local environment.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', static fn (): bool => false);
    }
}
