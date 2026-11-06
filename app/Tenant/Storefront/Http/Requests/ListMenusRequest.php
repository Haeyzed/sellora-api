<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Shared\Http\PageListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Storefront\Models\Menu;

/**
 * A page of the store's menus, which needs the storefront.view permission.
 */
final class ListMenusRequest extends PageListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Menu::class);
    }
}
