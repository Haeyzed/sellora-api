<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Tenant\Customers\Models\CustomerIdentity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A provider the customer signs in with. The provider's own ID for them is never shown.
 *
 * @property CustomerIdentity $resource
 */
final class CustomerIdentityResource extends JsonResource
{
    public function __construct(CustomerIdentity $identity)
    {
        parent::__construct($identity);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $identity = $this->resource;

        return [
            'id' => $identity->public_id,
            /** "google" or "apple". */
            'provider' => $identity->provider->value,
            /** The email the provider gave, which may differ from the account's; an Apple private relay address when the customer hid theirs. */
            'email' => $identity->email,
            'is_private_relay' => $identity->is_private_relay,
            'connected_at' => $identity->created_at->toIso8601String(),
            'last_used_at' => $identity->last_used_at?->toIso8601String(),
        ];
    }
}
