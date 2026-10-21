<?php

declare(strict_types=1);

namespace Tests\Fixtures\Customers;

use SocialiteProviders\Apple\Provider;

/**
 * Sign in with Apple as the store uses it, checking identity tokens against the test's Apple keys (AppleIdentityTokens) instead of fetching Apple's.
 */
final class TestAppleProvider extends Provider
{
    /**
     * @return array<string, mixed>
     */
    protected function getJwkSet()
    {
        return AppleIdentityTokens::keySet();
    }
}
