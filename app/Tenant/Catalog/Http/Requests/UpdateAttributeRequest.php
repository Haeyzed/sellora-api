<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Renaming an attribute, which needs the catalog.manage permission.
 */
final class UpdateAttributeRequest extends FormRequest
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
            'name' => ['required', 'array', $this->storeTranslatedText(60)],
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
}
