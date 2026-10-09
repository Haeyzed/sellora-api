<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Models\Attribute;

/**
 * Renames an attribute. Languages that aren't sent keep their text; every variant with its values shows the new name.
 */
final readonly class UpdateAttribute
{
    /**
     * @param  array<string, string|null>  $name  By language; a language set to null loses its translation.
     */
    public function handle(Attribute $attribute, array $name): Attribute
    {
        $attribute->changeTranslations('name', $name);
        $attribute->save();

        return $attribute;
    }
}
