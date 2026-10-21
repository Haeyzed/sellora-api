<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Enums;

/**
 * What a finished social sign-in still needs from the customer before it can go on.
 */
enum SocialSignInTicketKind: string
{
    /** An account already has the provider's email: its owner must prove it is theirs before the provider is connected. */
    case LinkAccount = 'link_account';

    /** The provider gave no email: the customer must give one. */
    case EmailRequired = 'email_required';
}
