<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Resources;

use App\Tenant\Catalog\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An attribute as staff see it, such as "Size", with its name in every language it was written in and, where shown, its values.
 *
 * @property Attribute $resource
 */
final class AttributeResource extends JsonResource
{
    public function __construct(Attribute $attribute)
    {
        parent::__construct($attribute);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attribute = $this->resource;

        return [
            'id' => $attribute->public_id,
            /**
             * By language, such as {"en": "Size"}, including languages the store no longer publishes in.
             *
             * @var array<string, string>
             */
            'name' => $attribute->getTranslations('name'),
            /** Its values, such as S, M and L, in their order. */
            'values' => AttributeValueResource::collection($this->whenLoaded('values')),
        ];
    }
}
