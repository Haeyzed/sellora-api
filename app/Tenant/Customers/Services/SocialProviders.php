<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Services;

use App\Tenant\Customers\Enums\SocialProvider;
use App\Tenant\Customers\Exceptions\SocialProviderNotOfferedException;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\AbstractProvider;
use LogicException;
use SocialiteProviders\Apple\Provider as AppleProvider;

/**
 * The social sign-in providers the current store offers its customers (Step 10b), and Socialite set up for each: stateless, since the store keeps the one-time state itself, and answering to the central relay page.
 */
final readonly class SocialProviders
{
    public function __construct(
        private FindStoreSettings $findStoreSettings,
        private Socialite $socialite,
    ) {}

    /**
     * The providers the store offers that the platform has set up, in the store's order.
     *
     * @return list<SocialProvider>
     */
    public function offered(): array
    {
        $offered = [];

        foreach ($this->findStoreSettings->handle()->social_sign_in_providers as $value) {
            $provider = SocialProvider::tryFrom($value);

            if ($provider !== null && $provider->isAvailable()) {
                $offered[] = $provider;
            }
        }

        return $offered;
    }

    /**
     * @throws SocialProviderNotOfferedException When the store doesn't offer it.
     */
    public function ensureOffered(SocialProvider $provider): void
    {
        if (! in_array($provider, $this->offered(), true)) {
            throw new SocialProviderNotOfferedException;
        }
    }

    /**
     * A fresh Socialite driver for the provider, without its session state (the store keeps its own), answering to the relay page. Never one already used, so nothing set for one request carries into another.
     */
    public function driver(SocialProvider $provider, ?string $nonce = null): AbstractProvider
    {
        if ($this->socialite instanceof SocialiteManager) {
            $this->socialite->forgetDrivers();
        }

        $driver = $this->socialite->driver($provider->value);

        if (! $driver instanceof AbstractProvider) {
            throw new LogicException("The {$provider->value} sign-in driver is not an OAuth 2 provider.");
        }

        $driver->stateless()->redirectUrl(self::relayUrl($provider));

        // Apple checks the nonce in its signed identity token against the one the store kept for the sign-in.
        if ($driver instanceof AppleProvider && $nonce !== null) {
            $driver->setNonce($nonce);
        }

        return $driver;
    }

    /**
     * The one address every provider sends its answer to: the relay page on the central domain.
     */
    public static function relayUrl(SocialProvider $provider): string
    {
        return str_replace('{provider}', $provider->value, config()->string('customers.social.relay_url'));
    }
}
