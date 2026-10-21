<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Enums;

/**
 * What a started social sign-in is for.
 */
enum SocialSignInIntent: string
{
    /** Sign in, or create an account. */
    case SignIn = 'sign_in';

    /** Connect the provider to the signed-in customer's account. */
    case Link = 'link';

    /** Prove again, with a provider connected to the signed-in customer's account, that it is them: to confirm erasing an account without a password (section 3.3). */
    case Reauthenticate = 'reauthenticate';
}
