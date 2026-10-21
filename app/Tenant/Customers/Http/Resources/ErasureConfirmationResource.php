<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Tenant\Customers\Data\ErasureConfirmation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The one-time token confirming erasure for a customer without a password, given after they signed in again with a connected provider.
 *
 * @property-read ErasureConfirmation $resource
 */
final class ErasureConfirmationResource extends JsonResource
{
    public function __construct(ErasureConfirmation $confirmation)
    {
        parent::__construct($confirmation);
    }

    /**
     * @return array{erasure_token: string, expires_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** Send it with "erase my account". Shown only here, and works once. */
            'erasure_token' => $this->resource->token,
            'expires_at' => $this->resource->expiresAt->toIso8601String(),
        ];
    }
}
