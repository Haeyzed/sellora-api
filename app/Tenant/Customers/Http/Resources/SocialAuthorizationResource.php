<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Where to send the browser to sign in with a provider.
 */
final class SocialAuthorizationResource extends JsonResource
{
    public function __construct(private readonly string $authorizationUrl)
    {
        parent::__construct($authorizationUrl);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** The provider's address: send the browser here. Good for one sign-in, for 10 minutes. */
            'authorization_url' => $this->authorizationUrl,
        ];
    }
}
