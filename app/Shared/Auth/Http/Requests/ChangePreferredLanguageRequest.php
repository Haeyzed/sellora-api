<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Requests;

use App\Shared\Http\Middleware\UseDashboardLanguage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Choosing the signed-in account's language for what is sent to it: one the application is translated into, or null for the default.
 */
final class ChangePreferredLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** A language the dashboards are translated into, such as "fr" or "pt"; null to go back to the default (the store's default language for staff, the platform's for platform admins). */
            'locale' => ['present', 'nullable', 'string', Rule::in(UseDashboardLanguage::availableLocales())],
        ];
    }

    public function locale(): ?string
    {
        $locale = $this->validated('locale');

        return is_string($locale) ? $locale : null;
    }
}
