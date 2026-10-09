<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changing a value's label, which needs the catalog.manage permission.
 */
final class UpdateAttributeValueRequest extends FormRequest
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
             * By language. Only the languages sent change; null removes a language, except the default.
             *
             * @var array<string, string|null>
             */
            'label' => ['required', 'array', $this->storeTranslatedText(60)],
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
