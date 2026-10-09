<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Middleware;

use App\Landlord\Identity\Models\PlatformAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows the API docs to anyone on a developer's machine, and everywhere else only to signed-in platform admins.
 *
 * The docs list every endpoint and its rules, which helps an attacker map the
 * API, so outside local development they need a platform admin's bearer token
 * from an admin with two-factor authentication on, as every platform route
 * does. Done here rather than with a Gate ability, because full-access roles
 * pass every ability and would skip the two-factor check.
 */
final class RestrictApiDocsToPlatformAdmins
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        $admin = $request->user(PlatformAdmin::GUARD);

        abort_unless($admin instanceof PlatformAdmin && $admin->hasTwoFactorEnabled(), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
