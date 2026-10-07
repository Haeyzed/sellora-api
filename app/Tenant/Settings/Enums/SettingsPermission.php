<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Enums;

/**
 * What staff can be allowed to do with the store's settings. Requiring two-factor authentication for staff is the owner's alone.
 */
enum SettingsPermission: string
{
    /** See the store's settings: name, country, currency, languages, tax mode, units and contact details. */
    case SettingsView = 'settings.view';

    /** Change the store's settings. */
    case SettingsManage = 'settings.manage';
}
