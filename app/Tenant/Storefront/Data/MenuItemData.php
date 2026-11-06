<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Data;

use App\Tenant\Storefront\Enums\MenuLinkType;

/**
 * One menu link as staff send it: its label, where it goes and the links under it.
 */
final readonly class MenuItemData
{
    /**
     * @param  array<string, string>  $label  By language.
     * @param  string|null  $target  The public ID of the page, category, brand or product; null for an address.
     * @param  string|null  $url  The address, for a link of type url.
     * @param  string  $path  Where the link was sent in the request, such as "items.0.children.1", for pointing at it in an error.
     * @param  list<MenuItemData>  $children
     */
    public function __construct(
        public array $label,
        public MenuLinkType $linkType,
        public ?string $target,
        public ?string $url,
        public bool $opensInNewTab,
        public string $path,
        public array $children = [],
    ) {}
}
