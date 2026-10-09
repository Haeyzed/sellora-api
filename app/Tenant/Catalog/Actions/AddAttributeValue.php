<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Catalog\Models\AttributeValue;

/**
 * Adds a value to an attribute, such as "XL" to Size, after its other values.
 */
final readonly class AddAttributeValue
{
    /**
     * @param  array<string, string|null>  $label  By language.
     */
    public function handle(Attribute $attribute, array $label): AttributeValue
    {
        $value = new AttributeValue;
        $value->attribute_id = $attribute->id;
        $value->changeTranslations('label', $label);
        $value->position = (int) $attribute->values()->max('position') + 1;
        $value->save();

        return $value;
    }
}
