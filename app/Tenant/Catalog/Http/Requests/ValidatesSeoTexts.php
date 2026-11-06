<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Data\SeoTexts;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;

/**
 * The rules for a product's, category's or brand's search engine title and description, for adding and changing them.
 */
trait ValidatesSeoTexts
{
    use ValidatesStoreTranslations;

    /**
     * @return array<string, mixed>
     */
    protected function seoTextRules(): array
    {
        return [
            /**
             * By language: the title search engines show, when it differs from the name; up to 200 characters each. Null removes it in every language.
             *
             * @var array<string, string|null>|null
             */
            'seo_title' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(200)],
            /**
             * By language: the description search engines show; up to 320 characters each. Null removes it in every language.
             *
             * @var array<string, string|null>|null
             */
            'seo_description' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(320)],
        ];
    }

    public function seoTexts(): SeoTexts
    {
        $removes = [];

        foreach (['seo_title', 'seo_description'] as $field) {
            if ($this->has($field) && $this->validated($field) === null) {
                $removes[] = $field;
            }
        }

        return new SeoTexts(
            title: $this->validatedTranslations('seo_title'),
            description: $this->validatedTranslations('seo_description'),
            removes: $removes,
        );
    }
}
