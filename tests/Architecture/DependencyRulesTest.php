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
| One namespace per rule, on both sides: with several namespaces in one
| expectation, not->toUse() can pass silently. Each rule here was checked
| against a deliberate violation.
|
*/

arch('core does not depend on modules')
    ->expect('App')
    ->not->toUse('Modules');

arch('core does not depend on integrations')
    ->expect('App')
    ->not->toUse('Integrations');

arch('tenant domains never import landlord code')
    ->expect('App\Tenant')
    ->not->toUse('App\Landlord');

arch('shared does not depend on the landlord side')
    ->expect('App\Shared')
    ->not->toUse('App\Landlord');

arch('shared does not depend on stores')
    ->expect('App\Shared')
    ->not->toUse('App\Tenant');

arch('shared does not depend on modules')
    ->expect('App\Shared')
    ->not->toUse('Modules');

arch('shared does not depend on integrations')
    ->expect('App\Shared')
    ->not->toUse('Integrations');

arch('only the Money wrapper uses the money library, so it stays replaceable')
    ->expect('App')
    ->not->toUse('Brick\Money')
    ->ignoring('App\Shared\Money');

arch('only the Money wrapper uses the maths library behind it')
    ->expect('App')
    ->not->toUse('Brick\Math')
    ->ignoring('App\Shared\Money');

arch('modules use Money, never the money library')
    ->expect('Modules')
    ->not->toUse('Brick\Money');

arch('modules use Money, never the maths library behind it')
    ->expect('Modules')
    ->not->toUse('Brick\Math');

arch('integrations use Money, never the money library')
    ->expect('Integrations')
    ->not->toUse('Brick\Money');

arch('integrations use Money, never the maths library behind it')
    ->expect('Integrations')
    ->not->toUse('Brick\Math');

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

arch('landlord domains are final')
    ->expect('App\Landlord')
    ->classes()
    ->toBeFinal();

arch('tenant domains are final')
    ->expect('App\Tenant')
    ->classes()
    ->toBeFinal();
