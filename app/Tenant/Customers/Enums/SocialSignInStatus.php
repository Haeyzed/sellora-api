<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Enums;

/**
 * How a finished social sign-in ended.
 */
enum SocialSignInStatus: string
{
    /** Signed in, to an account the provider was already connected to or a new one. */
    case SignedIn = 'signed_in';

    /** An account already has the provider's email: its owner proves it is theirs (password, or an emailed link where allowed) before the provider is connected. */
    case LinkAccount = 'link_account';

    /** The provider gave no email: the customer gives one to finish creating their account. */
    case EmailRequired = 'email_required';
}
