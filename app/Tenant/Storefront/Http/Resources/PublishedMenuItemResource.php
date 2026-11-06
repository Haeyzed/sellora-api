<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Resources;

use App\Tenant\Storefront\Enums\MenuLinkType;
use App\Tenant\Storefront\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A menu link as shoppers see it: its label in their language and where it goes, so the storefront can build its address.
 *
 * Only links whose destination shoppers may see reach this resource
 * (ShownMenuItems), with their shown children loaded.
 *
 * @property MenuItem $resource
 */
final class PublishedMenuItemResource extends JsonResource
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
        $destination = match ($linkType) {
            MenuLinkType::Page => $item->shownPage,
            MenuLinkType::Category => $item->shownCategory,
            MenuLinkType::Brand => $item->shownBrand,
            MenuLinkType::Product => $item->shownProduct,
            MenuLinkType::Url => null,
        };

        return [
            /** In the language asked for, or the store's default. */
            'label' => $item->getTranslation('label', app()->getLocale()),
            /** "page", "category", "brand", "product" or "url". */
            'type' => $linkType->value,
            /** The public ID of the page, category, brand or product; null for a url. */
            'target' => $destination?->public_id,
            /** The slug of the page, category, brand or product, for building its storefront address; null for a url. */
            'slug' => $destination?->slug,
            /** The address, for a url; null otherwise. */
            'url' => $item->url,
            'opens_in_new_tab' => $item->opens_in_new_tab,
            /** The links under this one that shoppers may see, in order. */
            'children' => $item->relationLoaded('children') ? array_values($item->children->map(static fn (MenuItem $child): self => new self($child))->all()) : [],
        ];
    }
}
