<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Controllers;

use App\Landlord\Identity\Actions\InvitePlatformAdmin;
use App\Landlord\Identity\Actions\ResendPlatformAdminInvitation;
use App\Landlord\Identity\Actions\RevokePlatformAdminInvitation;
use App\Landlord\Identity\Exceptions\PlatformAdminAlreadyExistsException;
use App\Landlord\Identity\Exceptions\PlatformAdminInvitationAlreadyPendingException;
use App\Landlord\Identity\Exceptions\PlatformAdminInvitationNotPendingException;
use App\Landlord\Identity\Http\Requests\InvitePlatformAdminRequest;
use App\Landlord\Identity\Http\Requests\ListPlatformTeamRequest;
use App\Landlord\Identity\Http\Requests\ManagePlatformTeamRequest;
use App\Landlord\Identity\Http\Resources\PlatformAdminInvitationResource;
use App\Landlord\Identity\Models\PlatformAdminInvitation;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Invitations to join Sellora's team. Super admins only.
 */
final class PlatformAdminInvitationController extends Controller
{
    /**
     * List open invitations.
     *
     * Super admins only. Invitations not yet accepted or cancelled, newest
     * first; expired ones can be resent.
     */
    public function index(ListPlatformTeamRequest $request): AnonymousResourceCollection
    {
        $platformAdminInvitations = PlatformAdminInvitation::query()
            ->open()
            ->with(['roles', 'invitedBy'])
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage());

        return PlatformAdminInvitationResource::collection($platformAdminInvitations);
    }

    /**
     * Invite someone to the platform team.
     *
     * Super admins only. Emails a one-time link, valid for 3 days; the account
     * is created when they accept and choose their password, and they must
     * set up two-factor authentication before doing anything else.
     *
     * @throws PlatformAdminAlreadyExistsException
     * @throws PlatformAdminInvitationAlreadyPendingException
     */
    public function store(InvitePlatformAdminRequest $request, InvitePlatformAdmin $invitePlatformAdmin): JsonResponse
    {
        $platformAdminInvitation = $invitePlatformAdmin->handle($request->actor(), $request->normalisedEmail(), $request->suggestedName(), $request->roles());

        return (new PlatformAdminInvitationResource($platformAdminInvitation->load('invitedBy')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Resend an invitation.
     *
     * Super admins only. Emails a new link valid for 3 days; the previous link
     * stops working.
     *
     * @throws PlatformAdminInvitationNotPendingException
     * @throws PlatformAdminAlreadyExistsException
     */
    public function resend(ManagePlatformTeamRequest $request, PlatformAdminInvitation $platformAdminInvitation, ResendPlatformAdminInvitation $resendPlatformAdminInvitation): PlatformAdminInvitationResource
    {
        return new PlatformAdminInvitationResource($resendPlatformAdminInvitation->handle($request->actor(), $platformAdminInvitation)->load('invitedBy'));
    }

    /**
     * Cancel an invitation.
     *
     * Super admins only. Its link stops working.
     *
     * @throws PlatformAdminInvitationNotPendingException
     */
    public function destroy(ManagePlatformTeamRequest $request, PlatformAdminInvitation $platformAdminInvitation, RevokePlatformAdminInvitation $revokePlatformAdminInvitation): Response
    {
        $revokePlatformAdminInvitation->handle($request->actor(), $platformAdminInvitation);

        return response()->noContent();
    }
}
