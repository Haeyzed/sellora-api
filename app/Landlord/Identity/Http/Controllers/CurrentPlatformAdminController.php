<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Controllers;

use App\Landlord\Identity\Http\Resources\PlatformAdminResource;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Http\Controller;
use Illuminate\Http\Request;
use LogicException;

/**
 * The signed-in platform admin's own account.
 */
final class CurrentPlatformAdminController extends Controller
{
    /**
     * Get the signed-in platform admin.
     *
     * Includes their roles and every permission they hold, so the admin app can
     * show only what they may use.
     */
    public function __invoke(Request $request): PlatformAdminResource
    {
        $platformAdmin = $request->user();

        if (! $platformAdmin instanceof PlatformAdmin) {
            throw new LogicException('This route must be protected by auth:platform.');
        }

        return new PlatformAdminResource($platformAdmin);
    }
}
