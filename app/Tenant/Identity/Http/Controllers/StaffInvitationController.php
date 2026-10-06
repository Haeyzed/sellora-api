<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Identity\Actions\InviteStaffMember;
use App\Tenant\Identity\Actions\ResendStaffInvitation;
use App\Tenant\Identity\Actions\RevokeStaffInvitation;
use App\Tenant\Identity\Exceptions\PermissionsExceedYourOwnException;
use App\Tenant\Identity\Exceptions\RoleNotAssignableException;
use App\Tenant\Identity\Exceptions\StaffInvitationAlreadyPendingException;
use App\Tenant\Identity\Exceptions\StaffInvitationNotPendingException;
use App\Tenant\Identity\Exceptions\StaffMemberAlreadyExistsException;
use App\Tenant\Identity\Http\Requests\InviteStaffMemberRequest;
use App\Tenant\Identity\Http\Requests\ListStaffInvitationsRequest;
use App\Tenant\Identity\Http\Requests\ManageStaffInvitationRequest;
use App\Tenant\Identity\Http\Resources\StaffInvitationResource;
use App\Tenant\Identity\Models\StaffInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Invitations to join the store's team.
 */
final class StaffInvitationController extends Controller
{
    /**
     * List open invitations.
     *
     * Needs the staff.view permission. Lists invitations not yet accepted or
     * cancelled, newest first; expired ones can be resent.
     */
    public function index(ListStaffInvitationsRequest $request): AnonymousResourceCollection
    {
        $staffInvitations = StaffInvitation::query()
            ->open()
            ->with(['roles', 'invitedBy'])
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage());

        return StaffInvitationResource::collection($staffInvitations);
    }

    /**
     * Invite someone to the team.
     *
     * Needs the staff.invite permission. Emails a one-time link, valid for 7
     * days, to join with the given roles; the account is created when they
     * accept. A pending invitation counts towards the plan's staff limit. You
     * can't give the Owner role or roles with permissions you don't have.
     *
     * @throws StaffMemberAlreadyExistsException
     * @throws StaffInvitationAlreadyPendingException
     * @throws RoleNotAssignableException
     * @throws PermissionsExceedYourOwnException
     * @throws UsageLimitReachedException
     */
    public function store(InviteStaffMemberRequest $request, InviteStaffMember $inviteStaffMember): JsonResponse
    {
        $staffInvitation = $inviteStaffMember->handle($request->actor(), $request->normalisedEmail(), $request->suggestedName(), $request->roles());

        return (new StaffInvitationResource($staffInvitation->load('invitedBy')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Resend an invitation.
     *
     * Needs the staff.invite permission. Emails a new link valid for 7 days;
     * the previous link stops working.
     *
     * @throws StaffInvitationNotPendingException
     * @throws RoleNotAssignableException
     * @throws PermissionsExceedYourOwnException
     * @throws UsageLimitReachedException
     */
    public function resend(ManageStaffInvitationRequest $request, StaffInvitation $staffInvitation, ResendStaffInvitation $resendStaffInvitation): StaffInvitationResource
    {
        $staffInvitation = $resendStaffInvitation->handle($request->actor(), $staffInvitation);

        return new StaffInvitationResource($staffInvitation->load(['roles', 'invitedBy']));
    }

    /**
     * Cancel an invitation.
     *
     * Needs the staff.invite permission. Its link stops working.
     *
     * @throws StaffInvitationNotPendingException
     */
    public function destroy(ManageStaffInvitationRequest $request, StaffInvitation $staffInvitation, RevokeStaffInvitation $revokeStaffInvitation): Response
    {
        $revokeStaffInvitation->handle($request->actor(), $staffInvitation);

        return response()->noContent();
    }
}
