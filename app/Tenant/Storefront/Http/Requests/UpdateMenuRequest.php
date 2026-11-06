<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Storefront\Data\MenuData;
use App\Tenant\Storefront\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Renaming a menu or changing its key, which needs the storefront.manage permission.
 */
final class UpdateMenuRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Menu::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $menu = $this->route('menu');

        return [
            /** Lower-case letters, numbers and single hyphens, unique in the store. A storefront still asking for the old key stops finding the menu. */
            'key' => ['sometimes', 'string', 'max:64', 'regex:'.Menu::KEY_PATTERN, Rule::unique(Menu::class, 'key')->ignore($menu instanceof Menu ? $menu->getKey() : null)],
            /** The menu's name for staff; shoppers never see it. */
            'name' => ['sometimes', 'string', 'max:120'],
        ];
    }

    public function menuData(): MenuData
    {
        $key = $this->validated('key');
        $name = $this->validated('name');

        return new MenuData(key: is_string($key) ? $key : null, name: is_string($name) ? trim($name) : null);
    }
}
