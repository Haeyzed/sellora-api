<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Enums;

use App\Shared\Auth\Contracts\GrantablePermission;

/**
 * What staff can be allowed to do with the storefront's content: its pages, menus, banners and settings.
 */
enum StorefrontPermission: string implements GrantablePermission
{
    /** See the store's pages, menus, banners and storefront settings, drafts included. */
    case StorefrontView = 'storefront.view';

    /** Add, change, publish and remove pages, menus and banners, and change the storefront settings. */
    case StorefrontManage = 'storefront.manage';

    public function group(): string
    {
        return 'storefront';
    }

    /**
     * Storefront content moves no money and shows no personal data.
     */
    public function isSensitive(): bool
    {
        return false;
    }
}
