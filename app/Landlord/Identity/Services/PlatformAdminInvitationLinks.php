<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Services;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Models\PlatformAdminInvitation;
use App\Landlord\Identity\PlatformAdminInvitationNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Gives a platform admin invitation a fresh one-time link and emails it. Any earlier link for the same invitation stops working.
 */
final readonly class PlatformAdminInvitationLinks
{
    public function __construct(private ConfigRepository $config) {}

    /**
     * Saves the invitation with a new token hash and expiry, and queues the email for after the transaction commits.
     */
    public function sendNew(PlatformAdminInvitation $platformAdminInvitation, PlatformAdmin $inviter): void
    {
        $token = Str::random(64);

        $platformAdminInvitation->forceFill([
            'token_hash' => self::hash($token),
            'expires_at' => CarbonImmutable::now()->addDays($this->config->integer('auth.platform_admin_invitations.expire_days')),
        ])->save();

        Notification::route('mail', $platformAdminInvitation->email)->notify(PlatformAdminInvitationNotification::forToken($token, $inviter->name));
    }

    /**
     * The value stored for a token, so a leaked database can't be used to accept invitations.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
