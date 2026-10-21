<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

/**
 * Adds Sign in with Apple (socialiteproviders/apple) to Socialite for customer social sign-in (Step 10b), so the provider package is used only here.
 */
final class RegisterAppleSignIn
{
    public static function listenOn(Dispatcher $events): void
    {
        $events->listen(SocialiteWasCalled::class, [self::class, 'handle']);
    }

    public function handle(SocialiteWasCalled $event): void
    {
        $event->extendSocialite('apple', AppleProvider::class);
    }
}
