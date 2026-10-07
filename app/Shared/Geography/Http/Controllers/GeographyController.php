<?php

declare(strict_types=1);

namespace App\Shared\Geography\Http\Controllers;

use App\Shared\Geography\Country;
use App\Shared\Geography\Currency;
use App\Shared\Geography\Exceptions\CountryNotFoundException;
use App\Shared\Geography\Geography;
use App\Shared\Geography\Http\Resources\CountryResource;
use App\Shared\Geography\Http\Resources\CurrencyResource;
use App\Shared\Geography\Http\Resources\StateResource;
use App\Shared\Geography\State;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Reference data for forms: countries, their states, currencies, timezones and content languages.
 *
 * Served on the central domain (the sign-up form) and on every store's
 * domain (address forms), from the same code. Read-only and the same for
 * everyone, so it is cached and needs no sign-in.
 */
final class GeographyController extends Controller
{
    /**
     * List countries.
     *
     * Every country stores can be registered in or deliver to, by name, with what a new store there starts with.
     *
     * @unauthenticated
     */
    public function countries(Geography $geography): AnonymousResourceCollection
    {
        return CountryResource::collection(array_map(
            static fn (Country $country): CountryResource => new CountryResource($country, $geography->defaultsFor($country->code)),
            $geography->countries(),
        ));
    }

    /**
     * Show a country.
     *
     * One country, by ISO 3166-1 alpha-2 code (such as "NG"), with what a new store there starts with.
     *
     * @unauthenticated
     *
     * @throws CountryNotFoundException When it isn't a country we support.
     */
    public function country(Geography $geography, string $country): CountryResource
    {
        $found = $geography->country($country) ?? throw new CountryNotFoundException;

        return new CountryResource($found, $geography->defaultsFor($found->code));
    }

    /**
     * List a country's states.
     *
     * Its states, provinces or regions, by name; empty when it has none.
     *
     * @unauthenticated
     *
     * @throws CountryNotFoundException When it isn't a country we support.
     */
    public function states(Geography $geography, string $country): AnonymousResourceCollection
    {
        $found = $geography->country($country) ?? throw new CountryNotFoundException;

        return StateResource::collection(array_map(static fn (State $state): StateResource => new StateResource($state), $geography->states($found->code)));
    }

    /**
     * List currencies.
     *
     * Every ISO 4217 currency in use today, by code.
     *
     * @unauthenticated
     */
    public function currencies(Geography $geography): AnonymousResourceCollection
    {
        return CurrencyResource::collection(array_map(static fn (Currency $currency): CurrencyResource => new CurrencyResource($currency), $geography->currencies()));
    }

    /**
     * List timezones.
     *
     * Every current IANA timezone, such as "Africa/Lagos".
     *
     * @unauthenticated
     *
     * @response array{data: list<string>}
     */
    public function timezones(Geography $geography): JsonResponse
    {
        return new JsonResponse(['data' => $geography->timezones()]);
    }

    /**
     * List content languages.
     *
     * The languages a store can publish its content in, such as "en" and "fr".
     *
     * @unauthenticated
     *
     * @response array{data: list<string>}
     */
    public function locales(Geography $geography): JsonResponse
    {
        return new JsonResponse(['data' => $geography->contentLocales()]);
    }
}
