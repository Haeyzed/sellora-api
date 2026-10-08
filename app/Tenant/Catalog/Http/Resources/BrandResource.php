<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Resources;

use App\Tenant\Catalog\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A brand as staff see it, with its texts in every language they were written in.
 *
 * @property Brand $resource
 */
final class BrandResource extends JsonResource
{
    public function __construct(Brand $brand)
    {
        parent::__construct($brand);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $brand = $this->resource;
        $description = $brand->getTranslations('description');

        return [
            'id' => $brand->public_id,
            /**
             * By language, such as {"en": "Adidas"}, including languages the store no longer publishes in.
             *
             * @var array<string, string>
             */
            'name' => $brand->getTranslations('name'),
            /**
             * By language; null when the brand has no description.
             *
             * @var array<string, string>|null
             */
            'description' => $description === [] ? null : $description,
            /** Used in the brand's storefront address, such as "adidas". */
            'slug' => $brand->slug,
            /** When the brand was moved to the trash; null when it isn't in the trash. */
            'trashed_at' => $brand->deleted_at?->toIso8601String(),
            'created_at' => $brand->created_at?->toIso8601String(),
            'updated_at' => $brand->updated_at?->toIso8601String(),
        ];
    }
}
