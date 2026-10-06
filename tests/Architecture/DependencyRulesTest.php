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

arch('integrations never import modules')
    ->expect('Integrations')
    ->not->toUse('Modules');

arch('domains are final')
    ->expect(['App\Landlord', 'App\Tenant'])
    ->classes()
    ->toBeFinal();
