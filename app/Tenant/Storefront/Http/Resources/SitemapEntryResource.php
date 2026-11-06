<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Resources;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One address in the storefront's sitemap: a page, product, category or brand, with what the storefront builds the address from and when it last changed.
 *
 * @property Model $resource
 */
final class SitemapEntryResource extends JsonResource
{
    public function __construct(Model $model)
    {
        parent::__construct($model);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $slug = $this->resource->getAttribute('slug');
        $updatedAt = $this->resource->getAttribute('updated_at');

        return [
            /**
             * Such as "linen-shirt"; the storefront builds the address from it.
             *
             * @var string
             */
            'slug' => is_string($slug) ? $slug : '',
            /**
             * When it last changed, for the sitemap's "lastmod".
             *
             * @var string|null
             */
            'updated_at' => $updatedAt instanceof CarbonInterface ? $updatedAt->toIso8601String() : null,
        ];
    }
}
