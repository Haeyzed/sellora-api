<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Resources;

use App\Tenant\Catalog\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One value of an attribute as staff see it, such as "Blue", with its label in every language it was written in.
 *
 * @property AttributeValue $resource
 */
final class AttributeValueResource extends JsonResource
{
    public function __construct(AttributeValue $value)
    {
        parent::__construct($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $value = $this->resource;

        return [
            'id' => $value->public_id,
            /**
             * By language, such as {"en": "Blue", "fr": "Bleu"}.
             *
             * @var array<string, string>
             */
            'label' => $value->getTranslations('label'),
            /** Its place among the attribute's values, from 0. */
            'position' => $value->position,
        ];
    }
}
