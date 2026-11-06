<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use App\Tenant\Storefront\Data\MenuItemData;
use App\Tenant\Storefront\Enums\MenuLinkType;
use App\Tenant\Storefront\Models\Menu;
use App\Tenant\Storefront\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Replacing all of a menu's links, two levels deep, which needs the storefront.manage permission.
 */
final class ReplaceMenuItemsRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesStoreTranslations;

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
            /** The menu's top-level links in order; an empty list empties the menu. At most 100 links at both levels together. */
            'items' => ['present', 'list', 'max:'.Menu::MAX_ITEMS],
            ...$this->itemRules('items.*'),
            /** The links under this one, in order; links under them are refused (a menu is two levels deep). */
            'items.*.children' => ['sometimes', 'list'],
            ...$this->itemRules('items.*.children.*'),
        ];
    }

    /**
     * Refuses links under a second-level link (a menu is two levels deep), and a menu with more than MAX_ITEMS links at both levels together.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->thirdLevelLinkFields() as $field) {
                    $validator->errors()->add($field, __('validation.prohibited', ['attribute' => $field]));
                }

                if ($this->countSentItems() > Menu::MAX_ITEMS) {
                    $validator->errors()->add('items', __('validation.max.array', ['attribute' => 'items', 'max' => Menu::MAX_ITEMS]));
                }
            },
        ];
    }

    /**
     * The links as sent, in order, each with the links under it.
     *
     * @return list<MenuItemData>
     */
    public function menuItems(): array
    {
        return $this->validatedItemsAt('items');
    }

    /**
     * @return array<string, mixed>
     */
    private function itemRules(string $prefix): array
    {
        return [
            /**
             * By language, such as {"en": "Shop"}; up to 100 characters each. Must include the store's default language.
             *
             * @var array<string, string>
             */
            $prefix.'.label' => ['required', 'array', $this->storeTranslatedText(100, requiresDefault: true)],
            /** Where the link goes: "page", "category", "brand", "product" or "url". */
            $prefix.'.type' => ['required', 'string', Rule::enum(MenuLinkType::class)],
            /** The public ID of the page, category, brand or product; not sent for a url. A draft page or a product shoppers can't see yet is accepted and shown once it can be. */
            $prefix.'.target' => ['required_unless:'.$prefix.'.type,url', 'prohibited_if:'.$prefix.'.type,url', 'string', 'ulid'],
            /** For a url only: an https address, a mailto: or tel: link, or a path on the storefront such as "/sale". */
            $prefix.'.url' => ['required_if:'.$prefix.'.type,url', 'prohibited_unless:'.$prefix.'.type,url', 'string', 'max:2048', 'regex:'.MenuItem::URL_PATTERN],
            /** Whether the storefront opens it in a new tab; false when left out. */
            $prefix.'.opens_in_new_tab' => ['sometimes', 'boolean'],
        ];
    }

    private function countSentItems(): int
    {
        $count = 0;

        foreach ($this->sentChildrenByItem() as $children) {
            $count += 1 + count($children);
        }

        return $count;
    }

    /**
     * Where a second-level link was sent with links of its own, such as "items.0.children.1.children".
     *
     * @return list<string>
     */
    private function thirdLevelLinkFields(): array
    {
        $fields = [];

        foreach ($this->sentChildrenByItem() as $index => $children) {
            foreach ($children as $childIndex => $child) {
                if (is_array($child) && array_key_exists('children', $child)) {
                    $fields[] = "items.{$index}.children.{$childIndex}.children";
                }
            }
        }

        return $fields;
    }

    /**
     * The links sent under each top-level link, as sent (an empty list when none or not a list), keyed by the top-level link's index.
     *
     * @return array<int|string, array<mixed>>
     */
    private function sentChildrenByItem(): array
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return [];
        }

        $childrenByItem = [];

        foreach ($items as $index => $item) {
            $children = is_array($item) ? ($item['children'] ?? []) : [];
            $childrenByItem[$index] = is_array($children) ? $children : [];
        }

        return $childrenByItem;
    }

    /**
     * @return list<MenuItemData>
     */
    private function validatedItemsAt(string $path): array
    {
        $items = $this->validated($path);

        if (! is_array($items)) {
            return [];
        }

        $menuItems = [];

        foreach (array_keys($items) as $index) {
            $itemPath = $path.'.'.$index;

            $menuItems[] = new MenuItemData(
                label: array_filter($this->validatedTranslations($itemPath.'.label') ?? [], static fn (?string $text): bool => $text !== null),
                linkType: MenuLinkType::from($this->validatedStringOrNull($itemPath.'.type') ?? ''),
                target: $this->validatedStringOrNull($itemPath.'.target'),
                url: $this->validatedStringOrNull($itemPath.'.url'),
                opensInNewTab: in_array($this->validated($itemPath.'.opens_in_new_tab'), [true, 1, '1', 'true'], true),
                path: $itemPath,
                children: $this->validatedItemsAt($itemPath.'.children'),
            );
        }

        return $menuItems;
    }

    private function validatedStringOrNull(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }
}
