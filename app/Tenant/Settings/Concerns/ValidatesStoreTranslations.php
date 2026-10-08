<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Concerns;

use App\Shared\Translations\Rules\TranslatedText;
use App\Tenant\Settings\StoreLocales;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates text sent in the store's languages, for form requests in any domain (public surface, section 6).
 *
 * The store's languages are looked up only when a request sends the text,
 * never while rules are merely listed (for example to generate the API docs,
 * where no store is current).
 *
 * @mixin FormRequest
 */
trait ValidatesStoreTranslations
{
    /**
     * A rule accepting text keyed by the store's languages, such as {"en": "Running shoes"}.
     *
     * @return Closure(string, mixed, Closure): void
     */
    protected function storeTranslatedText(int $maxLength, bool $requiresDefault = false): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($maxLength, $requiresDefault): void {
            $storeLocales = app(StoreLocales::class);

            (new TranslatedText($storeLocales->enabledLocales(), $storeLocales->defaultLocale(), $maxLength, $requiresDefault))
                ->validate($attribute, $value, $fail);
        };
    }
}
