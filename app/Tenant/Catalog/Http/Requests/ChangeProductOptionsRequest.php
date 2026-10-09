<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Catalog\Models\AttributeValue;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Setting a product's options and every variant's values in one step, which needs the catalog.manage permission.
 */
final class ChangeProductOptionsRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('update', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * The IDs of the attributes its variants differ by, in the order customers see them, such as Size then Colour. Empty for a product with a single variant and no options.
             *
             * @var list<string>
             */
            'attributes' => ['present', 'list', 'max:'.config()->integer('catalog.max_options_per_product')],
            'attributes.*' => ['string', 'distinct', Rule::exists(Attribute::class, 'public_id')],
            /**
             * Every variant of the product outside the trash, each with one value ID for each option.
             *
             * @var list<array{id: string, values: list<string>}>
             */
            'variants' => ['required', 'list'],
            'variants.*' => ['array:id,values'],
            'variants.*.id' => ['required', 'string', 'distinct'],
            'variants.*.values' => ['present', 'list'],
            'variants.*.values.*' => ['string', 'distinct', Rule::exists(AttributeValue::class, 'public_id')],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $known = $this->variantIdsByPublicId();
                foreach ($this->sentVariants() as $index => $sent) {
                    if (! isset($known[$sent['id']])) {
                        $validator->errors()->add("variants.{$index}.id", __('validation.exists', ['attribute' => 'variant']));
                    }
                }
            },
        ];
    }

    /**
     * The chosen attributes, in the order sent.
     *
     * @return list<Attribute>
     */
    public function chosenAttributes(): array
    {
        /** @var list<string> $publicIds */
        $publicIds = $this->validated('attributes');
        return array_values(Attribute::query()->whereIn('public_id', $publicIds)->get()
            ->sortBy(static fn (Attribute $attribute): int|false => array_search($attribute->public_id, $publicIds, true))
            ->all());
    }

    /**
     * Each variant's values, keyed by the variant's internal ID.
     *
     * @return array<int, list<AttributeValue>>
     */
    public function valuesByVariantId(): array
    {
        $variantIds = $this->variantIdsByPublicId();
        $sent = $this->sentVariants();
        $values = AttributeValue::query()->whereIn('public_id', array_merge([], ...array_column($sent, 'values')))->get()->keyBy('public_id');
        $valuesByVariantId = [];

        foreach ($sent as $variant) {
            $valuesByVariantId[$variantIds[$variant['id']]] = array_values(array_filter(array_map(static fn (string $publicId): ?AttributeValue => $values->get($publicId), $variant['values'])));
        }

        return $valuesByVariantId;
    }

    /**
     * The product's variants outside the trash, internal ID by public ID.
     *
     * @return array<string, int>
     */
    private function variantIdsByPublicId(): array
    {
        $product = $this->route('product');

        if (! $product instanceof Product) {
            return [];
        }

        /** @var array<string, int> $ids */
        $ids = ProductVariant::query()->where('product_id', $product->id)->pluck('id', 'public_id')->all();

        return $ids;
    }

    /**
     * @return list<array{id: string, values: list<string>}>
     */
    private function sentVariants(): array
    {
        /** @var list<array{id: string, values: list<string>}> $variants */
        $variants = $this->validated('variants');

        return $variants;
    }
}
