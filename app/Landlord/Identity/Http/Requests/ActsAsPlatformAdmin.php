<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use App\Landlord\Identity\Models\PlatformAdmin;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * The signed-in platform admin making a request, for requests on routes behind auth:platform.
 *
 * @mixin FormRequest
 */
trait ActsAsPlatformAdmin
{
    /**
     * @throws LogicException When the route isn't behind auth:platform, which is a routing mistake.
     */
    public function actor(): PlatformAdmin
    {
        $actor = $this->user();

        if (! $actor instanceof PlatformAdmin) {
            throw new LogicException('This route must be protected by the auth:platform middleware.');
        }

        return $actor;
    }
}
