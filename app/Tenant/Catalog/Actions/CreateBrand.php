<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Data\BrandData;
use App\Tenant\Catalog\Exceptions\BrandSlugTakenException;
use App\Tenant\Catalog\Models\Brand;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Adds a brand to the store's catalog, such as "Adidas", with its name in the store's languages.
 *
 * Without a chosen slug, one is made from the name in the default language.
 */
final readonly class CreateBrand
{
    /**
     * @throws BrandSlugTakenException When the chosen slug is used by another brand outside the trash.
     */
    public function handle(BrandData $brandData): Brand
    {
        $brand = new Brand;
        $brand->changeTranslations('name', $brandData->name ?? []);

        if ($brandData->description !== null) {
            $brand->changeTranslations('description', $brandData->description);
        }

        if ($brandData->slug !== null) {
            $brand->slug = $brandData->slug;
        }

        try {
            $brand->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new BrandSlugTakenException(previous: $exception);
        }

        return $brand;
    }
}
