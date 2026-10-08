<?php

declare(strict_types=1);

namespace App\Shared\Geography;

use App\Shared\Money\Currencies;
use App\Shared\Money\TaxMode;
use Closure;
use DateTimeZone;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nnjeim\World\Models\Country as WorldCountry;
use Nnjeim\World\Models\Currency as WorldCurrency;
use Nnjeim\World\Models\State as WorldState;
use Nnjeim\World\Models\Timezone as WorldTimezone;

/**
 * Read-only access to the world reference data: countries, their states, currencies, timezones and content languages, and a country's defaults for a new store.
 *
 * The only code that may use nnjeim/world (an architecture rule enforces
 * it). Everything is identified by ISO codes, never by the package's
 * database IDs, which change when the data is reseeded. The data lives in
 * the central database and is the same for every store, so it is cached in
 * the untagged central cache store, from the central or a store's context.
 *
 * Sources: countries, states and timezones come from nnjeim/world;
 * currencies from ISO 4217 (App\Shared\Money\Currencies); content languages
 * and the tax mode a country starts with from config/geography.php.
 */
final class Geography
{
    private const string CACHE_PREFIX = 'geography:';

    /**
     * @var array<string, Country>|null
     */
    private ?array $countries = null;

    public function __construct(
        private readonly CacheFactory $cacheFactory,
        private readonly ConfigRepository $config,
    ) {}

    /**
     * Every country stores can be registered in or deliver to, by name.
     *
     * @return list<Country>
     */
    public function countries(): array
    {
        return array_values($this->countryIndex());
    }

    /**
     * The country with the ISO 3166-1 alpha-2 code (any case), or null when it isn't one we support.
     */
    public function country(string $countryCode): ?Country
    {
        return $this->countryIndex()[mb_strtoupper(trim($countryCode))] ?? null;
    }

    /**
     * The country's states, provinces or regions, by name. Empty for an unknown country or one without any.
     *
     * @return list<State>
     */
    public function states(string $countryCode): array
    {
        $country = $this->country($countryCode);

        if ($country === null) {
            return [];
        }

        /** @var list<array{code: string, name: string, type: string|null}> $states */
        $states = $this->remember("states:{$country->code}", static fn (): array => self::loadStates($country->code));

        return array_map(static fn (array $state): State => new State($country->code, $state['code'], $state['name'], $state['type']), $states);
    }

    /**
     * The state with the code (any case) in the country, or null when the country has no such state.
     */
    public function state(string $countryCode, string $stateCode): ?State
    {
        $stateCode = mb_strtoupper(trim($stateCode));

        foreach ($this->states($countryCode) as $state) {
            if ($state->code === $stateCode) {
                return $state;
            }
        }

        return null;
    }

    /**
     * Every current IANA timezone, such as "Africa/Lagos". Deprecated aliases such as "US/Eastern" aren't included.
     *
     * @return list<string>
     */
    public function timezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    public function isTimezone(string $timezone): bool
    {
        return in_array($timezone, $this->timezones(), true);
    }

    /**
     * Every ISO 4217 currency in use today, by code.
     *
     * @return list<Currency>
     */
    public function currencies(): array
    {
        return array_map(
            static fn (string $code): Currency => new Currency($code, Currencies::name($code), Currencies::decimalPlaces($code)),
            Currencies::codes(),
        );
    }

    public function isCurrency(string $currencyCode): bool
    {
        return Currencies::isKnown($currencyCode);
    }

    /**
     * The languages a store can publish its content in, such as "en" and "fr".
     *
     * @return list<string>
     */
    public function contentLocales(): array
    {
        /** @var list<string> */
        return array_values($this->config->array('geography.content_locales'));
    }

    public function isContentLocale(string $locale): bool
    {
        return in_array($locale, $this->contentLocales(), true);
    }

    /**
     * What a new store in the country starts with, or null when stores can't be registered there (no currency or timezone in use).
     */
    public function defaultsFor(string $countryCode): ?CountryDefaults
    {
        $country = $this->country($countryCode);

        if ($country === null || $country->currencyCode === null || $country->timezones === []) {
            return null;
        }

        $locale = $this->config->array('geography.country_locales')[$country->code] ?? null;

        return new CountryDefaults(
            countryCode: $country->code,
            currencyCode: $country->currencyCode,
            timezones: $country->timezones,
            locale: is_string($locale) && $this->isContentLocale($locale) ? $locale : $this->config->string('geography.default_locale'),
            taxMode: in_array($country->code, $this->config->array('geography.tax_exclusive_countries'), true) ? TaxMode::Exclusive : TaxMode::Inclusive,
        );
    }

    /**
     * @return array<string, Country>
     */
    private function countryIndex(): array
    {
        if ($this->countries !== null) {
            return $this->countries;
        }

        /** @var list<array{code: string, name: string, iso3: string|null, phone_code: string|null, emoji: string|null, currency_code: string|null, timezones: list<string>}> $countries */
        $countries = $this->remember('countries', fn (): array => $this->loadCountries());

        $index = [];

        foreach ($countries as $country) {
            $index[$country['code']] = new Country(
                $country['code'],
                $country['name'],
                $country['iso3'],
                $country['phone_code'],
                $country['emoji'],
                $country['currency_code'],
                $country['timezones'],
            );
        }

        return $this->countries = $index;
    }

    /**
     * @return list<array{code: string, name: string, iso3: string|null, phone_code: string|null, emoji: string|null, currency_code: string|null, timezones: list<string>}>
     */
    private function loadCountries(): array
    {
        $knownTimezones = array_flip($this->timezones());
        $worldCountries = WorldCountry::query()->where('status', 1)->orderBy('name')->get();
        $countryIds = $worldCountries->map(static fn (WorldCountry $worldCountry): int => $worldCountry->id)->all();

        $timezonesByCountry = WorldTimezone::query()->whereIn('country_id', $countryIds)->orderBy('name')->get()
            ->filter(static fn (WorldTimezone $timezone): bool => isset($knownTimezones[$timezone->name]))
            ->groupBy('country_id');
        $currencyByCountry = WorldCurrency::query()->whereIn('country_id', $countryIds)->orderBy('id')->get()
            ->unique('country_id')
            ->keyBy('country_id');

        $countries = [];

        foreach ($worldCountries as $worldCountry) {
            $code = mb_strtoupper($worldCountry->iso2);
            $worldCurrency = $currencyByCountry->get($worldCountry->id);

            $countries[] = [
                'code' => $code,
                'name' => $worldCountry->name,
                'iso3' => $worldCountry->iso3,
                'phone_code' => $worldCountry->phone_code,
                'emoji' => $worldCountry->emoji,
                'currency_code' => self::currencyFor($code, $worldCurrency?->code),
                'timezones' => array_values(array_unique(($timezonesByCountry->get($worldCountry->id) ?? collect())
                    ->map(static fn (WorldTimezone $timezone): string => $timezone->name)
                    ->all())),
            ];
        }

        return $countries;
    }

    /**
     * The country's ISO 4217 currency. Where it uses several (Bhutan: BTN and INR), the reference data's choice wins if it is one of them.
     */
    private static function currencyFor(string $countryCode, ?string $worldCurrencyCode): ?string
    {
        $isoCurrencies = Currencies::forCountry($countryCode);

        if ($isoCurrencies === []) {
            return $worldCurrencyCode !== null && Currencies::isKnown($worldCurrencyCode) ? $worldCurrencyCode : null;
        }

        return in_array($worldCurrencyCode, $isoCurrencies, true) ? $worldCurrencyCode : $isoCurrencies[0];
    }

    /**
     * A country's states, one per code, by name: only those whose ISO 3166-2 code belongs to the country.
     *
     * States are matched to their country by the country's row, because the
     * dataset's own country code is sometimes wrong (a Hong Kong district is
     * labelled Cambodia). Some states carry another country's full ISO 3166-2
     * code (Sevastopol is "UA-40" under Russia); ISO 3166 decides, so those
     * are left out.
     *
     * @return list<array{code: string, name: string, type: string|null}>
     */
    private static function loadStates(string $countryCode): array
    {
        $countryIds = WorldCountry::query()->select('id')->where('iso2', $countryCode);
        $states = [];

        foreach (WorldState::query()->whereIn('country_id', $countryIds)->whereNotNull('state_code')->orderBy('name')->get() as $worldState) {
            $code = self::subdivisionCode($countryCode, (string) $worldState->state_code);

            if ($code !== null && ! isset($states[$code])) {
                $states[$code] = ['code' => $code, 'name' => $worldState->name, 'type' => $worldState->type];
            }
        }

        return array_values($states);
    }

    /**
     * The state's code within the country ("LA" for "NG-LA"), or null when the code is empty or its ISO 3166-2 code names another country.
     */
    private static function subdivisionCode(string $countryCode, string $stateCode): ?string
    {
        $stateCode = mb_strtoupper(trim($stateCode));
        $isoCode = str_contains($stateCode, '-') ? $stateCode : "{$countryCode}-{$stateCode}";

        if ($stateCode === '' || ! str_starts_with($isoCode, "{$countryCode}-")) {
            return null;
        }

        $code = mb_substr($isoCode, mb_strlen($countryCode) + 1);

        return $code === '' ? null : $code;
    }

    /**
     * @param  Closure(): array<mixed>  $load
     * @return array<mixed>
     */
    private function remember(string $key, Closure $load): array
    {
        /** @var array<mixed> */
        return $this->cacheFactory->store()->remember(self::CACHE_PREFIX.$key, $this->config->integer('geography.cache_ttl_in_seconds'), $load);
    }
}
