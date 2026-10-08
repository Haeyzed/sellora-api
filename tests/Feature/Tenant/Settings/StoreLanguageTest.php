<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Tenancy\TenantRoutes;
use App\Shared\Translations\Rules\TranslatedText;
use App\Tenant\Settings\Http\Middleware\UseStoreLanguage;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

/*
 * Section 3.3: each store has a default language and may publish in more.
 * The client asks for a language with Accept-Language and gets the store's
 * default when the store doesn't publish in it. Text sent in several
 * languages is accepted only in the store's languages, and always keeps the
 * default language.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    seedWorld();

    $this->englishStore = createStore('english-store');
    $this->frenchStore = createStore('french-store');

    publishIn($this->englishStore, 'en', ['en', 'fr']);
    publishIn($this->frenchStore, 'fr', ['fr', 'en']);

    Route::middleware([...TenantRoutes::MIDDLEWARE, UseStoreLanguage::class])
        ->prefix(TenantRoutes::PREFIX)
        ->get('/sample-language', static fn (): array => ['locale' => App::currentLocale()]);
});

afterEach(function (): void {
    deleteAllStores();
});

/**
 * @param  list<string>  $enabledLocales
 */
function publishIn(Tenant $store, string $defaultLocale, array $enabledLocales): void
{
    $store->run(static fn (): bool => StoreSettings::query()->sole()->update(['default_locale' => $defaultLocale, 'enabled_locales' => $enabledLocales]));
}

function sampleLanguage(string $subdomain, ?string $acceptLanguage = null): Illuminate\Testing\TestResponse
{
    $request = $acceptLanguage === null ? test() : test()->withHeader('Accept-Language', $acceptLanguage);
    $response = $request->getJson(storeUrl($subdomain, '/api/v1/sample-language'));
    tenancy()->end();

    return $response;
}

it('answers in the requested language when the store publishes in it, and in the store\'s default otherwise', function (): void {
    sampleLanguage('english-store', 'fr-CA,fr;q=0.9')->assertOk()->assertExactJson(['locale' => 'fr'])->assertHeader('Content-Language', 'fr');
    sampleLanguage('english-store', 'de-DE')->assertExactJson(['locale' => 'en'])->assertHeader('Content-Language', 'en');
    sampleLanguage('french-store')->assertExactJson(['locale' => 'fr'])->assertHeader('Content-Language', 'fr');
});

it('falls back from a regional language to its base language when the store publishes in that instead', function (): void {
    sampleLanguage('english-store', 'fr-CA')->assertExactJson(['locale' => 'fr'])->assertHeader('Content-Language', 'fr');
    sampleLanguage('english-store', 'fr-CA, en;q=0.9')->assertExactJson(['locale' => 'fr']);
    sampleLanguage('english-store', 'de-AT, fr-CA;q=0.8, en;q=0.5')->assertExactJson(['locale' => 'fr']);
    sampleLanguage('french-store', 'en-GB')->assertExactJson(['locale' => 'en']);

    publishIn($this->englishStore, 'en', ['en']);
    sampleLanguage('english-store', 'fr-CA')->assertExactJson(['locale' => 'en']);
});

it('follows a change of the store\'s languages at once', function (): void {
    sampleLanguage('english-store', 'fr')->assertExactJson(['locale' => 'fr']);

    publishIn($this->englishStore, 'en', ['en']);

    sampleLanguage('english-store', 'fr')->assertExactJson(['locale' => 'en']);
});

it('accepts text only in the store\'s languages, keeps the default language, and checks each text', function (array $value, bool $creating, ?string $error): void {
    $validator = Validator::make(['name' => $value], ['name' => [new TranslatedText(['en', 'fr'], 'en', 10, requiresDefault: $creating)]]);

    expect($validator->errors()->first('name'))->toBe($error ?? '');
})->with([
    'every language' => [['en' => 'Hat', 'fr' => 'Chapeau'], true, null],
    'only another language when changing' => [['fr' => 'Chapeau'], false, null],
    'removing another language' => [['fr' => null], false, null],
    'no default when creating' => [['fr' => 'Chapeau'], true, 'The name needs a text in the store\'s default language (en).'],
    'removing the default' => [['en' => null], false, 'The name needs a text in the store\'s default language (en).'],
    'a language the store doesn\'t publish in' => [['en' => 'Hat', 'de' => 'Hut'], true, 'The store does not publish in "de". Enable the language in the store settings first.'],
    'a blank text' => [['en' => '   '], false, 'The name in "en" cannot be empty. Send null to remove that translation.'],
    'a text too long' => [['en' => 'A very long hat'], false, 'The name in "en" may not be longer than 10 characters.'],
    'a plain list' => [['Hat'], false, 'The name must be an object of texts keyed by language, such as {"en": "…"}.'],
]);
