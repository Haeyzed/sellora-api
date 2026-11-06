<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Resources;

use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;

/**
 * The "seo" field of the product, category and brand resources: their search engine title and description, for staff in every language, for shoppers in theirs.
 */
final class SeoTextsFields
{
    /**
     * For staff: every language the texts were written in; null when there is none.
     *
     * @return array{title: array<string, string>|null, description: array<string, string>|null}
     */
    public static function forStaff(Product|Category|Brand $model): array
    {
        $title = $model->getTranslations('seo_title');
        $description = $model->getTranslations('seo_description');

        return [
            'title' => $title === [] ? null : $title,
            'description' => $description === [] ? null : $description,
        ];
    }

    /**
     * For the storefront's page head: in the language asked for, or the store's default; null when there is none (the title then falls back to the name).
     *
     * @return array{title: string|null, description: string|null}
     */
    public static function forShoppers(Product|Category|Brand $model): array
    {
        $locale = app()->getLocale();
        $title = $model->getTranslation('seo_title', $locale);
        $description = $model->getTranslation('seo_description', $locale);

        return [
            'title' => is_string($title) && $title !== '' ? $title : null,
            'description' => is_string($description) && $description !== '' ? $description : null,
        ];
    }
}
