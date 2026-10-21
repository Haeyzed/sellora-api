<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Http\Controller;
use App\Tenant\Customers\Exceptions\InvalidSocialSignInException;
use App\Tenant\Customers\Exceptions\SocialIdentityInUseException;
use App\Tenant\Customers\Exceptions\SocialLinkEmailNotAllowedException;
use App\Tenant\Customers\Exceptions\SocialProviderAlreadyLinkedException;
use App\Tenant\Customers\Http\Requests\ConfirmSocialLinkEmailRequest;
use App\Tenant\Customers\Http\Requests\ConfirmSocialLinkRequest;
use App\Tenant\Customers\Http\Requests\SocialTicketRequest;
use App\Tenant\Customers\Http\Resources\SocialSignInResultResource;
use App\Tenant\Customers\Services\SocialAccountLinks;
use Illuminate\Http\Response;

/**
 * Proving an existing account is yours before a sign-in provider is connected to it (Step 10b): after a social sign-in answered "link_account". The provider is never connected automatically.
 */
final class SocialAccountLinkController extends Controller
{
    /**
     * Prove the account is yours with its password.
     *
     * Connects the provider and signs you in. Wrong passwords are counted:
     * after 5, it pauses for 15 minutes.
     *
     * @unauthenticated
     *
     * @throws InvalidSocialSignInException
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     * @throws AccountDeactivatedException
     * @throws SocialIdentityInUseException
     * @throws SocialProviderAlreadyLinkedException
     */
    public function confirm(ConfirmSocialLinkRequest $request, SocialAccountLinks $socialAccountLinks): SocialSignInResultResource
    {
        return new SocialSignInResultResource($socialAccountLinks->confirmWithPassword($request->ticket(), $request->password(), $request->deviceName()));
    }

    /**
     * Prove the account is yours by email.
     *
     * Emails the account a link to confirm connecting the provider. Only when
     * the sign-in's proof_methods include "email_link": the provider is
     * trusted and says the email is verified.
     *
     * @unauthenticated
     *
     * @throws InvalidSocialSignInException
     * @throws SocialLinkEmailNotAllowedException
     */
    public function sendEmail(SocialTicketRequest $request, SocialAccountLinks $socialAccountLinks): Response
    {
        $socialAccountLinks->sendEmailLink($request->ticket());

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    /**
     * Confirm from the emailed link.
     *
     * Connects the provider and signs you in on this device. The link works
     * once, and only while the account still has the email it was sent to.
     *
     * @unauthenticated
     *
     * @throws InvalidSocialSignInException
     * @throws AccountDeactivatedException
     * @throws SocialIdentityInUseException
     * @throws SocialProviderAlreadyLinkedException
     */
    public function confirmEmail(ConfirmSocialLinkEmailRequest $request, SocialAccountLinks $socialAccountLinks): SocialSignInResultResource
    {
        return new SocialSignInResultResource($socialAccountLinks->confirmWithEmailLink($request->token(), $request->deviceName()));
    }
}
