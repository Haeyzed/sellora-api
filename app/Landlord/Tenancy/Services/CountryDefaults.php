<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Services;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;

/**
 * The defaults a store's country gives it at registration (currency and timezone), from the world reference data in the central database.
 *
 * The merchant can change them later in the store settings.
 */
final readonly class CountryDefaults
{
    public function __construct(private DatabaseManager $database) {}

    /**
     * Whether stores can be registered in the country.
     */
    public function isKnown(string $countryCode): bool
    {
        return $this->country($countryCode)->where('countries.status', 1)->exists();
    }

    /**
     * The country's currency, such as "NGN" for Nigeria.
     */
    public function currencyCode(string $countryCode): ?string
    {
        $currencyCode = $this->country($countryCode)
            ->join($this->table('currencies').' as currencies', 'currencies.country_id', '=', 'countries.id')
            ->orderBy('currencies.id')
            ->value('currencies.code');

        return is_string($currencyCode) ? $currencyCode : null;
    }

    /**
     * The country's timezones, such as ["Africa/Lagos"]. A country with several needs the merchant to choose one.
     *
     * @return list<string>
     */
    public function timezones(string $countryCode): array
    {
        /** @var list<string> */
        return $this->country($countryCode)
            ->join($this->table('timezones').' as timezones', 'timezones.country_id', '=', 'countries.id')
            ->orderBy('timezones.name')
            ->pluck('timezones.name')
            ->all();
    }

    private function country(string $countryCode): Builder
    {
        return $this->database->connection(config()->string('tenancy.database.central_connection'))
            ->table($this->table('countries').' as countries')
            ->where('countries.iso2', $countryCode);
    }

    /**
     * The world package's table names are configurable, so they come from config/world.php.
     */
    private function table(string $name): string
    {
        return config()->string("world.migrations.{$name}.table_name");
    }
}
