<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Data;

/**
 * Where to send the browser to sign in with a provider (Step 10b): good for one sign-in, for minutes.
 */
final readonly class SocialAuthorization
{
    public function __construct(public string $authorizationUrl) {}
}
