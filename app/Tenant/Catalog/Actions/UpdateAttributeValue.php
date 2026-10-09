<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Models\AttributeValue;

/**
 * Changes a value's label. Languages that aren't sent keep their text; every variant with the value shows the new label.
 */
final readonly class UpdateAttributeValue
{
    /**
     * @param  array<string, string|null>  $label  By language; a language set to null loses its translation.
     */
    public function handle(AttributeValue $value, array $label): AttributeValue
    {
        $value->changeTranslations('label', $label);
        $value->save();

        return $value;
    }
}
