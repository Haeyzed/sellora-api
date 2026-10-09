<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Adding an attribute, optionally with its first values, which needs the catalog.manage permission.
 */
final class StoreAttributeRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesStoreTranslations;

    public function authorize(): bool
    {
        return $this->actor()->can('create', Attribute::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * By language, such as {"en": "Size", "fr": "Taille"}; must include the store's default language. Up to 60 characters each.
             *
             * @var array<string, string>
             */
            'name' => ['required', 'array', $this->storeTranslatedText(60, requiresDefault: true)],
            /**
             * Its first values, in order, each a label by language such as {"en": "Blue"}. Up to 100.
             *
             * @var list<array<string, string>>
             */
            'values' => ['sometimes', 'list', 'max:100'],
            'values.*' => ['array', $this->storeTranslatedText(60, requiresDefault: true)],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function name(): array
    {
        /** @var array<string, string|null> $name */
        $name = $this->validated('name');

        return $name;
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function valueLabels(): array
    {
        /** @var list<array<string, string|null>> $labels */
        $labels = $this->validated('values', []);

        return $labels;
    }
}
