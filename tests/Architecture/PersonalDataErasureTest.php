<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Only erasure may delete consent events (CLAUDE.md sections 10 and 15)
|--------------------------------------------------------------------------
|
| The marketing_consents trigger lets a delete through only once the
| transaction has set app.erasing_personal_data. So no code but the erasure
| handler may name that setting or set any setting at all, and the trigger's
| migration is the only other place the name appears.
|
*/

it('names the erasure setting only in the erasure handler and the trigger\'s migration', function (): void {
    expect(productionFilesContaining('app.erasing_personal_data'))->toBe([
        'app/Tenant/Customers/Privacy/MarketingConsentData.php',
        'database/migrations/tenant/2026_10_14_130000_create_marketing_consents_table.php',
    ]);
});

it('sets database settings only in the erasure handler', function (): void {
    expect(productionFilesContaining('set_config'))->toBe([
        'app/Tenant/Customers/Privacy/MarketingConsentData.php',
        'database/migrations/tenant/2026_10_14_130000_create_marketing_consents_table.php',
    ]);
});
