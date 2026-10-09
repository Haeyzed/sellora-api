<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Adding a value to an attribute, which needs the catalog.manage permission.
 */
final class StoreAttributeValueRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesStoreTranslations;

    public function authorize(): bool
    {
        return $this->actor()->can('update', Attribute::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * By language, such as {"en": "Blue", "fr": "Bleu"}; must include the store's default language. Up to 60 characters each.
             *
             * @var array<string, string>
             */
            'label' => ['required', 'array', $this->storeTranslatedText(60, requiresDefault: true)],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function label(): array
    {
        /** @var array<string, string|null> $label */
        $label = $this->validated('label');

        return $label;
    }
}
