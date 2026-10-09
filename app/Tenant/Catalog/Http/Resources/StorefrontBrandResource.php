<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Resources;

use App\Shared\Media\Http\Resources\StorefrontImageResource;
use App\Tenant\Catalog\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A brand as customers see it, in the language they asked for (or the store's default).
 *
 * @property Brand $resource
 */
final class StorefrontBrandResource extends JsonResource
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
        $locale = app()->getLocale();
        $description = $brand->getTranslation('description', $locale);
        $logo = $brand->getFirstMedia(Brand::LOGO);

        return [
            'id' => $brand->public_id,
            'slug' => $brand->slug,
            'name' => $brand->getTranslation('name', $locale),
            'description' => $description === '' ? null : $description,
            'logo' => $logo === null ? null : new StorefrontImageResource($logo),
        ];
    }
}
