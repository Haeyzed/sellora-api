<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Services;

use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\StaffInvitationNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Gives an invitation a fresh one-time link and emails it. Any earlier link for the same invitation stops working.
 */
final readonly class StaffInvitationLinks
{
    public function __construct(private ConfigRepository $config) {}

    /**
     * Saves the invitation with a new token hash and expiry, and queues the email for after the transaction commits.
     */
    public function sendNew(StaffInvitation $staffInvitation, StaffMember $inviter): void
    {
        $token = Str::random(64);

        $staffInvitation->forceFill([
            'token_hash' => self::hash($token),
            'expires_at' => CarbonImmutable::now()->addDays($this->config->integer('auth.staff_invitations.expire_days')),
        ])->save();

        Notification::route('mail', $staffInvitation->email)->notify(StaffInvitationNotification::forToken($token, $inviter->name));
    }

    /**
     * The value stored for a token, so a leaked database can't be used to accept invitations.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
