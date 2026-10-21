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
}
