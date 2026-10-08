<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Data\BrandData;
use App\Tenant\Catalog\Exceptions\BrandSlugTakenException;
use App\Tenant\Catalog\Models\Brand;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Changes a brand's name, description or slug. Languages that aren't sent keep their text.
 *
 * Renaming a brand doesn't change its slug, so existing links keep working;
 * staff change the slug on purpose.
 */
final readonly class UpdateBrand
{
    /**
     * @throws BrandSlugTakenException When the new slug is used by another brand outside the trash.
     */
    public function handle(Brand $brand, BrandData $changes): Brand
    {
        if ($changes->name !== null) {
            $brand->changeTranslations('name', $changes->name);
        }

        if ($changes->removesDescription) {
            $brand->description = null;
        } elseif ($changes->description !== null) {
            $brand->changeTranslations('description', $changes->description);
        }

        if ($changes->slug !== null) {
            $brand->slug = $changes->slug;
        }

        try {
            $brand->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new BrandSlugTakenException(previous: $exception);
        }

        return $brand;
    }
}
