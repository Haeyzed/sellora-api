<?php

declare(strict_types=1);

namespace App\Tenant\Identity;

use App\Shared\Auth\AccountReference;
use App\Shared\Tenancy\Contracts\StoreAccountNotifications;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Notifications\Notification;

/**
 * Sends the platform's notifications to the current store's staff members, found by public ID.
 *
 * Only staff for now; customers and drivers are added when the platform has something to tell them.
 */
final readonly class StaffAccountNotifications implements StoreAccountNotifications
{
    public function send(AccountReference $account, Notification $notification): bool
    {
        if ($account->type !== (new StaffMember)->getMorphClass()) {
            return false;
        }

        $staffMember = StaffMember::query()->where('public_id', $account->publicId)->where('is_active', true)->first();

        if ($staffMember === null) {
            return false;
        }

        $staffMember->notify($notification);

        return true;
    }
}
