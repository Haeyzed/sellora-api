<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use App\Tenant\Storefront\Data\StorefrontSettingsChanges;
use App\Tenant\Storefront\Enums\PolicyPage;
use App\Tenant\Storefront\Enums\RobotsDirective;
use App\Tenant\Storefront\Models\Page;
use App\Tenant\Storefront\Models\StorefrontSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changing the storefront settings, which needs the storefront.manage permission. Send only what changes.
 */
final class ChangeStorefrontSettingsRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesStoreTranslations;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', StorefrontSettings::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** How a page is titled for search engines and browser tabs, such as "{title} · {store}": {title} (required) is the page's own title and {store} the store's name. */
            'seo_title_pattern' => ['sometimes', 'string', 'max:120', 'regex:/\{title\}/'],
            /**
             * By language: what search engines show for pages without a description of their own, up to 320 characters each. Only the languages sent change; null for a language removes it, and null for the whole field removes it in every language.
             *
             * @var array<string, string|null>|null
             */
            'seo_description' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(320)],
            /** Whether search engines may list the storefront and follow its links: "index_follow", "noindex_follow" or "noindex_nofollow". */
            'robots' => ['sometimes', 'string', Rule::enum(RobotsDirective::class)],
            /** Which of the store's pages serves each policy the storefront links to: "privacy", "terms", "refunds" and "shipping", each a page's ID, or null for none. Only the policies sent change; shoppers see only a published page. */
            'policy_pages' => ['sometimes', 'array:'.implode(',', array_column(PolicyPage::cases(), 'value'))],
            'policy_pages.privacy' => self::policyPageRule(),
            'policy_pages.terms' => self::policyPageRule(),
            'policy_pages.refunds' => self::policyPageRule(),
            'policy_pages.shipping' => self::policyPageRule(),
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function policyPageRule(): array
    {
        return ['sometimes', 'nullable', 'string', Rule::exists(Page::class, 'public_id')->whereNull('deleted_at')];
    }

    /**
     * @return array<string, string|null> The page (public ID) sent for each policy, by PolicyPage value.
     */
    private function policyPages(): array
    {
        $policyPages = [];

        foreach (PolicyPage::cases() as $policy) {
            $key = 'policy_pages.'.$policy->value;

            if ($this->has($key)) {
                $pageId = $this->validated($key);
                $policyPages[$policy->value] = is_string($pageId) ? $pageId : null;
            }
        }

        return $policyPages;
    }

    public function changes(): StorefrontSettingsChanges
    {
        $pattern = $this->validated('seo_title_pattern');
        $seoDescription = $this->validatedTranslations('seo_description');

        return new StorefrontSettingsChanges(
            seoTitlePattern: is_string($pattern) ? $pattern : null,
            seoDescription: $seoDescription,
            removesSeoDescription: $this->has('seo_description') && $seoDescription === null,
            robots: $this->has('robots') ? $this->enum('robots', RobotsDirective::class) : null,
            policyPages: $this->policyPages(),
        );
    }
}
