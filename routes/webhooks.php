<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
|
| Shared entry points for third-party webhooks, served on central domains
| only under /webhooks. Every webhook verifies its signature, looks up the
| store in Landlord\Integrations, then initializes tenancy before handing
| off to the integration.
|
*/
