<?php

declare(strict_types=1);

namespace App\Shared\Geography;

use App\Shared\Money\Currencies;
use App\Shared\Money\TaxMode;
use Closure;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use DateTimeZone;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nnjeim\World\Models\Country as WorldCountry;
use Nnjeim\World\Models\Currency as WorldCurrency;
use Nnjeim\World\Models\Timezone as WorldTimezone;

/**
 * Read-only access to the world reference data: countries, their states, currencies, timezones and content languages, and a country's defaults for a new store.
 *
 * With AddressFormats, the only code that may use nnjeim/world and
 * commerceguys/addressing (architecture rules enforce it). Everything is
 * identified by codes, never by nnjeim/world's database IDs, which change
 * when the data is reseeded. That data lives in the central database and is
 * the same for every store, so it is cached in the untagged central cache
 * store, from the central or a store's context.
 *
 * Sources (section 3.3): countries and timezones come from nnjeim/world;
 * states only from commerceguys/addressing, so store addresses, customer
 * addresses and tax rates all use the same codes; currencies from ISO 4217
 * (App\Shared\Money\Currencies); content languages and the tax mode a
 * country starts with from config/geography.php.
 */
final class Geography
{
    private const string CACHE_PREFIX = 'geography:';

    /**
     * @var array<string, Country>|null
     */
    private ?array $countries = null;

    /**
     * @var array<string, list<State>>
     */
    private array $states = [];

    private ?AddressFormatRepository $addressFormatRepository = null;

    private ?SubdivisionRepository $subdivisionRepository = null;

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
     * The country's states, provinces or regions, in the address data's order (by name; Japan's prefectures by number). Empty for an unknown country or one the address data lists none for.
     *
     * @return list<State>
     */
    public function states(string $countryCode): array
    {
        $country = $this->country($countryCode);

        if ($country === null) {
            return [];
        }

        return $this->states[$country->code] ??= $this->loadStates($country->code);
    }

    /**
     * Whether the address data lists the country's states. Where it doesn't, an address's state is free text and only country-wide tax rates apply.
     */
    public function listsStates(string $countryCode): bool
    {
        return $this->states($countryCode) !== [];
    }

    /**
     * The state with the code (any case) in the country, or null when the country has no such state.
     */
    public function state(string $countryCode, string $stateCode): ?State
    {
        $stateCode = mb_strtolower(trim($stateCode));

        foreach ($this->states($countryCode) as $state) {
            if (mb_strtolower($state->code) === $stateCode) {
                return $state;
            }
        }

        return null;
    }

    /**
     * Every subdivision code the address data has, by country, for every country it knows (supported by stores or not), sorted.
     *
     * These are the codes stored on addresses and tax rates, so a package
     * update that changes one needs a migration of the stored data; see
     * ListSubdivisionChangesCommand.
     *
     * @return array<string, list<string>>
     */
    public function subdivisionCodes(): array
    {
        $codes = [];

        foreach (array_keys((new CountryRepository)->getList()) as $countryCode) {
            $countryCodes = array_map(strval(...), array_keys($this->subdivisionRepository()->getList([$countryCode])));

            if ($countryCodes !== []) {
                sort($countryCodes);
                $codes[$countryCode] = $countryCodes;
            }
        }

        ksort($codes);

        return $codes;
    }

    /**
     * The state as it is stored: the listed code when it is one of the country's states in any case, otherwise the text as given, trimmed (free text where the country lists none).
     */
    public function stateCode(string $countryCode, string $state): string
    {
        return $this->state($countryCode, $state)->code ?? trim($state);
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
     * A country's first-level subdivisions from the address data, identified by the data's own key for each.
     *
     * The key is what the address data looks subdivisions up by, and it is
     * the ISO 3166-2 code's subdivision part for nearly all of them ("LA" for
     * Lagos, "CA" for California). Where the data has no ISO code it uses
     * another stable key: a postal abbreviation (Telangana is "TS") or the
     * name ("Boa Vista" in Cape Verde). The data's "code" field is not used:
     * it is the label printed on envelopes, often the name.
     *
     * @return list<State>
     */
    private function loadStates(string $countryCode): array
    {
        $type = $this->addressFormatRepository()->get($countryCode)->getAdministrativeAreaType();
        $states = [];

        foreach ($this->subdivisionRepository()->getList([$countryCode]) as $code => $name) {
            $states[] = new State($countryCode, (string) $code, $name, $type);
        }

        return $states;
    }

    private function addressFormatRepository(): AddressFormatRepository
    {
        return $this->addressFormatRepository ??= new AddressFormatRepository;
    }

    private function subdivisionRepository(): SubdivisionRepository
    {
        return $this->subdivisionRepository ??= new SubdivisionRepository($this->addressFormatRepository());
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
