<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Shared\Tenancy\Contracts\StoreSocialSignInRoutes;
use App\Tenant\Customers\Enums\SocialProvider;
use App\Tenant\Customers\Enums\SocialSignInIntent;
use App\Tenant\Customers\Exceptions\SocialProviderNotOfferedException;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\SocialSignInAttempt;
use App\Tenant\Customers\Services\SocialProviders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Starts a customer's social sign-in, or the signed-in customer's linking of a provider (Step 10b), and returns the provider's address to send the browser to.
 *
 * A new one-time state is made for it. The store keeps, keyed by the state's
 * hash, what the sign-in is for, the customer linking, the PKCE verifier and
 * a nonce. The platform is told only which store the state belongs to, so
 * its relay page can send the provider's answer back here and nowhere else.
 */
final readonly class StartSocialSignIn
{
    public function __construct(
        private SocialProviders $socialProviders,
        private StoreSocialSignInRoutes $storeSocialSignInRoutes,
    ) {}

    /**
     * @param  string|null  $returnPath  A path in the storefront, already checked to be one, handed back when the sign-in finishes.
     *
     * @throws SocialProviderNotOfferedException When the store doesn't offer the provider.
     */
    public function handle(SocialProvider $provider, ?Customer $linking, ?string $returnPath): string
    {
        $this->socialProviders->ensureOffered($provider);

        $state = Str::random(64);
        $verifier = Str::random(96);
        $nonce = Str::random(40);
        $expiresAt = CarbonImmutable::now()->addMinutes(config()->integer('customers.social.state_expire_minutes'));

        $attempt = new SocialSignInAttempt;
        $attempt->forceFill([
            'state_hash' => SocialSignInAttempt::hash($state),
            'provider' => $provider,
            'intent' => $linking === null ? SocialSignInIntent::SignIn : SocialSignInIntent::Link,
            'customer_id' => $linking?->id,
            'code_verifier' => $verifier,
            'nonce' => $nonce,
            'return_path' => $returnPath,
            'expires_at' => $expiresAt,
            'created_at' => CarbonImmutable::now(),
        ])->save();

        $this->storeSocialSignInRoutes->open(SocialSignInAttempt::hash($state), $provider->value, $expiresAt);

        $parameters = ['state' => $state, 'nonce' => $nonce];

        if ($provider->usesPkce()) {
            $parameters['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            $parameters['code_challenge_method'] = 'S256';
        }

        return $this->socialProviders->driver($provider, $nonce)->with($parameters)->redirect()->getTargetUrl();
    }
}
