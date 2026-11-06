<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Resources;

use App\Tenant\Storefront\Enums\MenuLinkType;
use App\Tenant\Storefront\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A menu link as staff see it, with its label in every language and where it goes, whether or not shoppers can see that yet.
 *
 * @property MenuItem $resource
 */
final class MenuItemResource extends JsonResource
{
    public function __construct(MenuItem $menuItem)
    {
        parent::__construct($menuItem);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $linkType = $item->linkType();

        return [
            /**
             * By language, such as {"en": "Shop"}.
             *
             * @var array<string, string>
             */
            'label' => $item->getTranslations('label'),
            /** "page", "category", "brand", "product" or "url". */
            'type' => $linkType->value,
            /** The public ID of the page, category, brand or product it goes to; null for a url. */
            'target' => $this->targetId($item, $linkType),
            /** The address, for a url; null otherwise. */
            'url' => $item->url,
            'opens_in_new_tab' => $item->opens_in_new_tab,
            /** The links under this one, in order; always empty under a second-level link. */
            'children' => $item->relationLoaded('children') ? array_values($item->children->map(static fn (MenuItem $child): self => new self($child))->all()) : [],
        ];
    }

    private function targetId(MenuItem $item, MenuLinkType $linkType): ?string
    {
        return match ($linkType) {
            MenuLinkType::Page => $item->page?->public_id,
            MenuLinkType::Category => $item->category?->public_id,
            MenuLinkType::Brand => $item->brand?->public_id,
            MenuLinkType::Product => $item->product?->public_id,
            MenuLinkType::Url => null,
        };
    }
}
