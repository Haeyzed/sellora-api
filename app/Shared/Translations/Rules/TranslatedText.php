<?php

declare(strict_types=1);

namespace App\Shared\Translations\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Text in one or more of the store's languages, keyed by language, such as {"en": "Running shoes", "fr": "Chaussures de course"}.
 *
 * Only the given languages are accepted. A language set to null removes its
 * translation, except the default language, which always keeps its text so
 * every other language has something to fall back to. When creating, the
 * default language must be sent.
 */
final readonly class TranslatedText implements ValidationRule
{
    /**
     * @param  list<string>  $locales  The languages the store publishes in, including the default.
     */
    public function __construct(
        private array $locales,
        private string $defaultLocale,
        private int $maxLength,
        private bool $requiresDefault = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === [] || array_is_list($value)) {
            $fail('translations.not_translations')->translate();

            return;
        }

        foreach ($value as $locale => $text) {
            $this->validateTranslation((string) $locale, $text, $fail);
        }

        if ($this->requiresDefault && ! array_key_exists($this->defaultLocale, $value)) {
            $fail('translations.default_required')->translate(['locale' => $this->defaultLocale]);
        }
    }

    private function validateTranslation(string $locale, mixed $text, Closure $fail): void
    {
        if (! in_array($locale, $this->locales, true)) {
            $fail('translations.locale_not_enabled')->translate(['locale' => $locale]);

            return;
        }

        if ($text === null) {
            if ($locale === $this->defaultLocale) {
                $fail('translations.default_required')->translate(['locale' => $locale]);
            }

            return;
        }

        if (! is_string($text) || trim($text) === '') {
            $fail('translations.empty')->translate(['locale' => $locale]);

            return;
        }

        if (mb_strlen(trim($text)) > $this->maxLength) {
            $fail('translations.too_long')->translate(['locale' => $locale, 'max' => $this->maxLength]);
        }
    }
}
