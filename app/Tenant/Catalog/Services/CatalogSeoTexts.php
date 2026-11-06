<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Services;

use App\Tenant\Catalog\Data\SeoTexts;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;

/**
 * Applies staff's search engine texts to a product, category or brand, without saving it, for adding and changing them alike.
 */
final readonly class CatalogSeoTexts
{
    public function apply(Product|Category|Brand $model, SeoTexts $seoTexts): void
    {
        foreach ($seoTexts->removes as $field) {
            // In every language: setting a translated field to null would clear only the current language's text.
            $model->forgetTranslations($field, asNull: true);
        }

        if ($seoTexts->title !== null) {
            $model->changeTranslations('seo_title', $seoTexts->title);
        }

        if ($seoTexts->description !== null) {
            $model->changeTranslations('seo_description', $seoTexts->description);
        }
    }
}
