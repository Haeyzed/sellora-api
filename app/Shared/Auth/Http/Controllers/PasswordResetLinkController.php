<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Controllers;

use App\Shared\Auth\Actions\SendPasswordResetLink;
use App\Shared\Auth\Http\Requests\SendPasswordResetLinkRequest;
use App\Shared\Http\Controller;
use Illuminate\Http\Response;

/**
 * Asking for a password reset email. The route says which kind of account through its "broker" default.
 */
final class PasswordResetLinkController extends Controller
{
    /**
     * Request a password reset link.
     *
     * Always answers 202 Accepted, whether or not an account uses the email, so
     * the endpoint can't be used to find out who has an account. If one does, a
     * link valid for 60 minutes is emailed.
     */
    public function store(SendPasswordResetLinkRequest $request, SendPasswordResetLink $sendPasswordResetLink): Response
    {
        $sendPasswordResetLink->handle((string) $request->route('broker'), $request->normalisedEmail());

        return response()->noContent(Response::HTTP_ACCEPTED);
    }
}
