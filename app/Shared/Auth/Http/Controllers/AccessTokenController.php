<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Controllers;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\AccessTokenResource;
use App\Shared\Auth\Http\SignedInAccount;
use App\Shared\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Refreshing and ending sessions, the same for every kind of account.
 */
final class AccessTokenController extends Controller
{
    public function __construct(
        private readonly AccessTokenIssuer $accessTokenIssuer,
        private readonly SignedInAccount $signedInAccount,
    ) {}

    /**
     * Refresh the session token.
     *
     * Returns a new token with a fresh expiry and immediately revokes the one used
     * for this request. Call it before the current token expires.
     */
    public function update(Request $request): AccessTokenResource
    {
        $account = $this->signedInAccount->from($request);

        return new AccessTokenResource($this->accessTokenIssuer->refresh($account, $this->signedInAccount->guard()));
    }

    /**
     * Sign out.
     *
     * Revokes the token used for this request. Other devices stay signed in.
     */
    public function destroy(Request $request): Response
    {
        $this->accessTokenIssuer->revokeCurrent($this->signedInAccount->from($request));

        return response()->noContent();
    }
}
