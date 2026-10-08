<?php

declare(strict_types=1);

namespace App\Tenant\Settings;

use App\Tenant\Settings\Actions\FindStoreSettings;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;

/**
 * The languages the current store publishes in, and which one a request should get.
 *
 * Read once per store and remembered for the rest of the request or job,
 * because every translated text asks for the default language. Saving the
 * settings forgets what was remembered. Kept per store, so code that works
 * in several stores one after the other never gets another store's
 * languages.
 */
final class StoreLocales
{
    /**
     * @var array<string, array{default: string, enabled: list<string>}>
     */
    private array $localesByStore = [];

    public function __construct(
        private readonly FindStoreSettings $findStoreSettings,
        private readonly DatabaseManager $database,
    ) {}

    /**
     * The language every translated text falls back to.
     */
    public function defaultLocale(): string
    {
        return $this->current()['default'];
    }

    /**
     * Every language the store publishes in, the default first.
     *
     * @return list<string>
     */
    public function enabledLocales(): array
    {
        return $this->current()['enabled'];
    }

    /**
     * The enabled language that best matches the request's Accept-Language header, or the store's default.
     */
    public function preferredBy(Request $request): string
    {
        $preferred = $request->getPreferredLanguage($this->enabledLocales());

        return is_string($preferred) && in_array($preferred, $this->enabledLocales(), true) ? $preferred : $this->defaultLocale();
    }

    /**
     * Forgets every store's remembered languages, after its settings change.
     */
    public function forget(): void
    {
        $this->localesByStore = [];
    }

    /**
     * @return array{default: string, enabled: list<string>}
     */
    private function current(): array
    {
        // Each store has its own database, so its name tells the current store apart.
        $storeDatabase = $this->database->connection()->getDatabaseName();

        return $this->localesByStore[$storeDatabase] ??= $this->load();
    }

    /**
     * @return array{default: string, enabled: list<string>}
     */
    private function load(): array
    {
        $settings = $this->findStoreSettings->handle();
        $others = array_values(array_diff($settings->enabled_locales, [$settings->default_locale]));

        return ['default' => $settings->default_locale, 'enabled' => [$settings->default_locale, ...$others]];
    }
}
