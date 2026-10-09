<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Catalog\Models\AttributeValue;

/**
 * Adds an attribute the store's variants can differ by, such as "Size", optionally with its first values, such as S, M and L.
 */
final readonly class CreateAttribute
{
    /**
     * @param  array<string, string|null>  $name  By language.
     * @param  list<array<string, string|null>>  $valueLabels  Each value's label by language, in order.
     */
    public function handle(array $name, array $valueLabels = []): Attribute
    {
        return Attribute::query()->getConnection()->transaction(static function () use ($name, $valueLabels): Attribute {
            $attribute = new Attribute;
            $attribute->changeTranslations('name', $name);
            $attribute->position = (int) Attribute::query()->max('position') + 1;
            $attribute->save();

            foreach ($valueLabels as $position => $label) {
                $value = new AttributeValue;
                $value->attribute_id = $attribute->id;
                $value->changeTranslations('label', $label);
                $value->position = $position;
                $value->save();
            }

            return $attribute->load('values');
        });
    }
}
