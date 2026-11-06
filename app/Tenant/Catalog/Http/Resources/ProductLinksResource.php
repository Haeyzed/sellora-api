<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Resources;

use App\Tenant\Catalog\Enums\ProductLinkType;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product's links for staff: every kind, each in the order shoppers see them, including links to products shoppers can't buy now.
 *
 * @property Product $resource
 */
final class ProductLinksResource extends JsonResource
{
    public const array RELATIONS = ['links.linkedProduct'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $ofType = fn (ProductLinkType $type): array => array_values($this->resource->links
            ->filter(static fn (ProductLink $link): bool => $link->type === $type)
            ->map(static fn (ProductLink $link): array => [
                'id' => $link->linkedProduct->public_id,
                'slug' => $link->linkedProduct->slug,
                'name' => $link->linkedProduct->getTranslation('name', $locale),
                'status' => $link->linkedProduct->status->value,
                /** Shoppers don't see a link to a product in the trash. */
                'in_trash' => $link->linkedProduct->trashed(),
            ])->all());

        return [
            /**
             * Products that go well with it or are alternatives.
             *
             * @var list<array{id: string, slug: string, name: string, status: string, in_trash: bool}>
             */
            'related' => $ofType(ProductLinkType::Related),
            /**
             * Better or bigger versions.
             *
             * @var list<array{id: string, slug: string, name: string, status: string, in_trash: bool}>
             */
            'upsell' => $ofType(ProductLinkType::Upsell),
            /**
             * Things to buy as well.
             *
             * @var list<array{id: string, slug: string, name: string, status: string, in_trash: bool}>
             */
            'cross_sell' => $ofType(ProductLinkType::CrossSell),
        ];
    }
}
