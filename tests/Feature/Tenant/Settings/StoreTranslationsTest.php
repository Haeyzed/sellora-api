<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Translations\TranslatedSample;

/*
 * Section 3.3: customer-facing text is stored per language and falls back to
 * the store's default language, then to any language it has. Each store's
 * default is its own, even when one process works in several stores.
 * Disabling a language never deletes its translations.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    seedWorld();

    $this->englishStore = createStore('english-store');
    $this->frenchStore = createStore('french-store');

    foreach ([$this->englishStore, $this->frenchStore] as $store) {
        $store->run(static function (): void {
            Schema::create(TranslatedSample::TABLE, static function (Blueprint $table): void {
                $table->id();
                $table->jsonb('name');
            });
        });
    }

    useLanguages($this->englishStore, 'en', ['en', 'fr']);
    useLanguages($this->frenchStore, 'fr', ['fr', 'en']);
});

afterEach(function (): void {
    deleteAllStores();
});

/**
 * @param  list<string>  $enabledLocales
 */
function useLanguages(Tenant $store, string $defaultLocale, array $enabledLocales): void
{
    $store->run(static fn (): bool => StoreSettings::query()->sole()->update(['default_locale' => $defaultLocale, 'enabled_locales' => $enabledLocales]));
}

it('falls back to the store\'s default language, then to any language the text has', function (): void {
    $this->englishStore->run(static function (): void {
        $sample = TranslatedSample::query()->create(['name' => ['en' => 'Running shoes', 'fr' => 'Chaussures de course']]);
        $englishOnly = TranslatedSample::query()->create(['name' => ['en' => 'Socks']]);
        $germanOnly = TranslatedSample::query()->create(['name' => ['de' => 'Mütze']]);

        expect($sample->getTranslation('name', 'fr'))->toBe('Chaussures de course')
            ->and($englishOnly->getTranslation('name', 'fr'))->toBe('Socks')
            ->and($germanOnly->getTranslation('name', 'fr'))->toBe('Mütze');
    });
});

it('uses each store\'s own default language when one process reads from several stores', function (): void {
    $this->englishStore->run(static fn () => TranslatedSample::query()->create(['name' => ['en' => 'Hat', 'fr' => 'Chapeau']]));
    $this->frenchStore->run(static fn () => TranslatedSample::query()->create(['name' => ['en' => 'Hat', 'fr' => 'Chapeau']]));

    $inGerman = static fn (): string => TranslatedSample::query()->sole()->getTranslation('name', 'de');

    expect($this->englishStore->run($inGerman))->toBe('Hat')
        ->and($this->frenchStore->run($inGerman))->toBe('Chapeau')
        ->and($this->englishStore->run($inGerman))->toBe('Hat');
});

it('follows a change of the default language at once', function (): void {
    $this->englishStore->run(static function (): void {
        $sample = TranslatedSample::query()->create(['name' => ['en' => 'Hat', 'fr' => 'Chapeau']]);
        expect($sample->getTranslation('name', 'de'))->toBe('Hat');

        StoreSettings::query()->sole()->update(['default_locale' => 'fr']);

        expect($sample->getTranslation('name', 'de'))->toBe('Chapeau');
    });
});

it('changes only the languages sent, removes a language set to null, and keeps translations in disabled languages', function (): void {
    useLanguages($this->englishStore, 'en', ['en']);

    $this->englishStore->run(static function (): void {
        $sample = TranslatedSample::query()->create(['name' => ['en' => 'Hat', 'fr' => 'Chapeau', 'de' => 'Hut']]);

        $sample->changeTranslations('name', ['en' => '  Sun hat  ', 'de' => null])->save();

        expect($sample->refresh()->getTranslations('name'))->toBe(['en' => 'Sun hat', 'fr' => 'Chapeau']);
    });
});
