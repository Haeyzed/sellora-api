<?php

declare(strict_types=1);

namespace App\Tenant\Messaging;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Messaging\Contracts\MessageAddressee;
use App\Tenant\Messaging\Data\MessageRecipient;
use App\Tenant\Settings\Actions\FindStoreSettings;

/**
 * A staff member a message is for, looked up when it is sent: in the language they chose, or else the store's default language; nobody once they are deactivated or gone.
 */
final readonly class StaffAddressee implements MessageAddressee
{
    public function __construct(public int $staffMemberId) {}

    public function recipient(): ?MessageRecipient
    {
        $staffMember = StaffMember::query()->find($this->staffMemberId);

        if ($staffMember === null || ! $staffMember->is_active) {
            return null;
        }

        return new MessageRecipient(
            locale: $staffMember->locale ?? app(FindStoreSettings::class)->handle()->default_locale,
            email: $staffMember->email,
            name: $staffMember->name,
        );
    }
}
