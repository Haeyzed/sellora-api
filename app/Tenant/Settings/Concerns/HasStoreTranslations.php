<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Concerns;

use App\Tenant\Settings\StoreLocales;
use Spatie\Translatable\HasTranslations;

/**
 * Text a model shows customers in several languages (names, descriptions), stored as JSON keyed by language, falling back to the store's default language.
 *
 * When a text is missing in the requested language, the store's default
 * language is used, then any language the text has, so changing the default
 * language never leaves a name blank (section 3.3). Models list their
 * translated columns in `$translatable`.
 */
trait HasStoreTranslations
{
    use HasTranslations;

    /**
     * The store's default language: what a missing translation falls back to.
     */
    public function getFallbackLocale(): string
    {
        return app(StoreLocales::class)->defaultLocale();
    }

    /**
     * Applies changed translations of one text, keeping the languages that weren't sent.
     *
     * A language set to null loses its translation. Translations in languages
     * the store no longer publishes in are kept, so enabling the language
     * again brings them back.
     *
     * @param  array<string, string|null>  $translations  Validated by TranslatedText.
     */
    public function changeTranslations(string $key, array $translations): static
    {
        foreach ($translations as $locale => $text) {
            if ($text === null) {
                $this->forgetTranslation($key, $locale);

                continue;
            }

            $this->setTranslation($key, $locale, trim($text));
        }

        return $this;
    }
}
