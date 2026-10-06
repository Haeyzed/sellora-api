<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Resources;

use App\Shared\Auth\TwoFactor\PendingTwoFactorChallenge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Returned by sign-in instead of a token when the account uses two-factor authentication.
 *
 * @property PendingTwoFactorChallenge $resource
 */
final class TwoFactorChallengeResource extends JsonResource
{
    public function __construct(PendingTwoFactorChallenge $pendingTwoFactorChallenge)
    {
        parent::__construct($pendingTwoFactorChallenge);
    }

    /**
     * @return array{challenge_token: string, expires_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** Send it back with the authenticator code or a recovery code to get the access token. */
            'challenge_token' => $this->resource->challengeToken,
            /** After this, sign in with the password again. */
            'expires_at' => $this->resource->expiresAt->toIso8601String(),
        ];
    }
}
