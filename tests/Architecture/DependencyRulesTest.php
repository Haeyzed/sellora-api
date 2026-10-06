<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dependency rules (CLAUDE.md section 7)
|--------------------------------------------------------------------------
|
| If a change needs to break one of these rules, stop and explain why
| instead of working around the test.
|
*/

arch('core does not depend on modules or integrations')
    ->expect('App')
    ->not->toUse(['Modules', 'Integrations']);

arch('tenant domains never import landlord code')
    ->expect('App\Tenant')
    ->not->toUse('App\Landlord');

arch('shared contains no business logic')
    ->expect('App\Shared')
    ->not->toUse(['App\Landlord', 'App\Tenant', 'Modules', 'Integrations']);

/*
 * One expectation per target: with several targets, not->toUse() silently
 * passes for vendor namespaces (checked against a deliberate violation).
 */
arch('only the Money wrapper uses the money library, so it stays replaceable')
    ->expect('App')
    ->not->toUse(['Brick\Money', 'Brick\Math'])
    ->ignoring('App\Shared\Money');

arch('modules use Money, never the money library')
    ->expect('Modules')
    ->not->toUse(['Brick\Money', 'Brick\Math']);

arch('integrations use Money, never the money library')
    ->expect('Integrations')
    ->not->toUse(['Brick\Money', 'Brick\Math']);

arch('only the two-factor authenticator uses the one-time password library, so it stays replaceable')
    ->expect('App')
    ->not->toUse('PragmaRX\Google2FA')
    ->ignoring('App\Shared\Auth\TwoFactor\TwoFactorAuthenticator');

arch('modules never use the one-time password library')
    ->expect('Modules')
    ->not->toUse('PragmaRX\Google2FA');

arch('integrations never use the one-time password library')
    ->expect('Integrations')
    ->not->toUse('PragmaRX\Google2FA');

arch('integrations never import modules')
    ->expect('Integrations')
    ->not->toUse('Modules');

arch('domains are final')
    ->expect(['App\Landlord', 'App\Tenant'])
    ->classes()
    ->toBeFinal();
