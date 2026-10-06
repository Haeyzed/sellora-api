<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The sign-in token returned when someone signs in or refreshes their session, in the same shape for every kind of account.
 *
 * @property IssuedAccessToken $resource
 */
final class AccessTokenResource extends JsonResource
{
    public function __construct(IssuedAccessToken $issuedAccessToken)
    {
        parent::__construct($issuedAccessToken);
    }

    /**
     * @return array{token: string, token_type: string, expires_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** Send it as "Authorization: Bearer <token>". Shown only once; store it securely. */
            'token' => $this->resource->plainTextToken,
            /** Always "Bearer". */
            'token_type' => 'Bearer',
            /** When the token stops working; refresh it before then. */
            'expires_at' => $this->resource->expiresAt->toIso8601String(),
        ];
    }
}
