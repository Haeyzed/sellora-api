<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Tenant\Customers\Enums\SocialProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A provider the store offers for signing in.
 *
 * @property SocialProvider $resource
 */
final class SocialProviderResource extends JsonResource
{
    public function __construct(SocialProvider $provider)
    {
        parent::__construct($provider);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** "google" or "apple". */
            'provider' => $this->resource->value,
        ];
    }
}
