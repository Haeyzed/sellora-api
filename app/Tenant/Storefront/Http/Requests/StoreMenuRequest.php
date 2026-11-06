<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Storefront\Data\MenuData;
use App\Tenant\Storefront\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a menu, which needs the storefront.manage permission.
 */
final class StoreMenuRequest extends FormRequest
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
        return [
            /** What the storefront asks for the menu by, such as "main" or "footer": lower-case letters, numbers and single hyphens, unique in the store. */
            'key' => ['required', 'string', 'max:64', 'regex:'.Menu::KEY_PATTERN, Rule::unique(Menu::class, 'key')],
            /** The menu's name for staff, such as "Footer"; shoppers never see it. */
            'name' => ['required', 'string', 'max:120'],
        ];
    }

    public function menuData(): MenuData
    {
        $key = $this->validated('key');
        $name = $this->validated('name');

        return new MenuData(key: is_string($key) ? $key : null, name: is_string($name) ? trim($name) : null);
    }
}
