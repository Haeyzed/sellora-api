<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Tenant\Customers\Data\SocialAuthorization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Where to send the browser to sign in with a provider.
 *
 * @property-read SocialAuthorization $resource
 */
final class SocialAuthorizationResource extends JsonResource
{
    public function __construct(string $authorizationUrl)
    {
        parent::__construct(new SocialAuthorization($authorizationUrl));
    }

    /**
     * @return array{authorization_url: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** The provider's address: send the browser here. Good for one sign-in, for 10 minutes. */
            'authorization_url' => $this->resource->authorizationUrl,
        ];
    }
}
