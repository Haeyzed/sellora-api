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
     * Exchanges the code for who the person is, with the verifier kept when the sign-in started.
     *
     * @throws SocialSignInFailedException When the provider refuses the code or doesn't answer.
     */
    public function profile(SocialSignInAttempt $attempt): SocialProfile
    {
        try {
            $user = $this->socialProviders->driver($attempt->provider)->with(['code_verifier' => $attempt->code_verifier])->user();
        } catch (Throwable $exception) {
            Log::warning('A social sign-in provider refused or failed the sign-in.', ['provider' => $attempt->provider->value, 'exception' => $exception::class]);

            throw new SocialSignInFailedException;
        }

        return $this->fromProvider($attempt->provider, $user);
    }

    /**
     * What the provider said, as the store reads it: Google's "email_verified" claim says whether the email is verified.
     */
    private function fromProvider(SocialProvider $provider, SocialiteUser $user): SocialProfile
    {
        $raw = method_exists($user, 'getRaw') ? $user->getRaw() : [];
        $email = $user->getEmail();
        $email = is_string($email) && trim($email) !== '' ? mb_strtolower(trim($email)) : null;

        return new SocialProfile(
            provider: $provider,
            providerUserId: (string) $user->getId(),
            email: $email,
            emailVerified: ($raw['email_verified'] ?? false) === true || ($raw['email_verified'] ?? false) === 'true',
            isPrivateRelay: false,
            name: is_string($user->getName()) ? mb_substr(trim($user->getName()), 0, 120) : null,
        );
    }
}
