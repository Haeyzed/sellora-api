<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Services;

use App\Tenant\Customers\Data\SocialProfile;
use App\Tenant\Customers\Enums\SocialProvider;
use App\Tenant\Customers\Enums\SocialSignInIntent;
use App\Tenant\Customers\Exceptions\InvalidSocialSignInException;
use App\Tenant\Customers\Exceptions\SocialSignInFailedException;
use App\Tenant\Customers\Models\SocialSignInAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Throwable;

/**
 * Finishing a social sign-in this store started (Step 10b): its one-time state is used up once, then the provider's code is exchanged, with the PKCE verifier the store kept, for who the person is.
 *
 * A state this store never started (another store's, say), one already
 * used or expired, or one for another provider or purpose, is refused the
 * same way. Provider failures are logged by kind only: never a code, state
 * or token.
 */
final readonly class SocialSignInAttempts
{
    public function __construct(private SocialProviders $socialProviders) {}

    /**
     * Uses the state up, once, even when two requests bring it at the same moment.
     *
     * @throws InvalidSocialSignInException
     */
    public function consume(SocialProvider $provider, string $state, SocialSignInIntent $intent, ?int $customerId = null): SocialSignInAttempt
    {
        return DB::transaction(static function () use ($provider, $state, $intent, $customerId): SocialSignInAttempt {
            $attempt = SocialSignInAttempt::query()->where('state_hash', SocialSignInAttempt::hash($state))->lockForUpdate()->first();

            if ($attempt === null || $attempt->provider !== $provider || $attempt->intent !== $intent || $attempt->customer_id !== $customerId
                || $attempt->consumed_at !== null || ! $attempt->expires_at->isFuture()) {
                throw new InvalidSocialSignInException;
            }

            $attempt->forceFill(['consumed_at' => CarbonImmutable::now()])->save();

            return $attempt;
        });
    }

    /**
     * Exchanges the code for who the person is: with the PKCE verifier kept when the sign-in started (Google), or checking the nonce kept then against Apple's signed identity token.
     *
     * @param  string|null  $displayName  For Apple: the name the storefront sends back from Apple's unsigned answer, already checked, as display text only.
     *
     * @throws SocialSignInFailedException When the provider refuses the code or doesn't answer, or its identity token doesn't check out.
     */
    public function profile(SocialSignInAttempt $attempt, ?string $displayName = null): SocialProfile
    {
        try {
            $driver = $this->socialProviders->driver($attempt->provider, $attempt->nonce);
            $user = ($attempt->provider->usesPkce() ? $driver->with(['code_verifier' => $attempt->code_verifier]) : $driver)->user();
        } catch (Throwable $exception) {
            Log::warning('A social sign-in provider refused or failed the sign-in.', ['provider' => $attempt->provider->value, 'exception' => $exception::class]);

            throw new SocialSignInFailedException;
        }

        return $this->fromProvider($attempt->provider, $user, $displayName);
    }

    /**
     * What the provider said, as the store reads it. Google's userinfo and Apple's signed identity token both say whether the email is verified ("email_verified"); Apple's also says whether it is a private relay address ("is_private_email"). Apple's name never comes from the provider driver, which reads it from the unsigned request.
     */
    private function fromProvider(SocialProvider $provider, SocialiteUser $user, ?string $displayName): SocialProfile
    {
        $raw = method_exists($user, 'getRaw') ? $user->getRaw() : [];
        $email = $user->getEmail();
        $email = is_string($email) && trim($email) !== '' ? mb_strtolower(trim($email)) : null;
        $claim = static fn (string $key): bool => ($raw[$key] ?? false) === true || ($raw[$key] ?? false) === 'true';
        $name = $provider === SocialProvider::Apple ? $displayName : $user->getName();

        return new SocialProfile(
            provider: $provider,
            providerUserId: (string) $user->getId(),
            email: $email,
            emailVerified: $claim('email_verified'),
            isPrivateRelay: $provider === SocialProvider::Apple && ($claim('is_private_email') || ($email !== null && str_ends_with($email, '@privaterelay.appleid.com'))),
            name: is_string($name) && trim($name) !== '' ? mb_substr(trim($name), 0, 120) : null,
        );
    }
}
