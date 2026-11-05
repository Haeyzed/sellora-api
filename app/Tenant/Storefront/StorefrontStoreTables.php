<?php

declare(strict_types=1);

namespace App\Tenant\Storefront;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How the storefront's content and settings appear in a store export. Nothing in them is a secret or personal data.
 */
final class StorefrontStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('storefront_settings', include: ['id', 'seo_title_pattern', 'seo_description', 'robots', 'created_at', 'updated_at']);
    }
}
