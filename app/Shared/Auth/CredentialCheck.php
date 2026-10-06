<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use App\Shared\Auth\Exceptions\InvalidCredentialsException;
use App\Shared\Auth\Exceptions\SignInTemporarilyLockedException;
use App\Shared\Tenancy\TenantScopedKey;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * Checks a password or PIN without revealing whether the account exists, and locks the account's sign-in after repeated wrong attempts.
 *
 * - Unknown accounts take as long to check as real ones, so response timing
 *   can't be used to discover registered emails or phone numbers.
 * - Wrong attempts are counted per account identifier in the current store,
 *   from any IP address, so a slow guessing attack spread over many
 *   addresses still stops. Unknown identifiers lock too, so locking reveals
 *   nothing either.
 */
final readonly class CredentialCheck
{
    public function __construct(
        private Hasher $hasher,
        private RateLimiter $rateLimiter,
        private ConfigRepository $config,
    ) {}

    /**
     * Returns the account when the secret matches its stored hash.
     *
     * @template TAccount of Authenticatable
     *
     * @param  string  $guard  Which kind of account this is, so each guard counts its own attempts.
     * @param  string  $identifier  The normalised email or phone the person typed.
     * @param  TAccount|null  $account  The account found for the identifier, if any.
     * @return TAccount
     *
     * @throws SignInTemporarilyLockedException When there have been too many wrong attempts for this identifier.
     * @throws InvalidCredentialsException When the account doesn't exist or the secret is wrong.
     */
    public function ensureValid(string $guard, string $identifier, ?Authenticatable $account, string $secret): Authenticatable
    {
        $lockoutKey = TenantScopedKey::forIdentifier('sign-in-lockout:'.$guard, $identifier);

        if ($this->rateLimiter->tooManyAttempts($lockoutKey, $this->config->integer('api.sign_in_lockout.max_failed_attempts'))) {
            throw new SignInTemporarilyLockedException($this->rateLimiter->availableIn($lockoutKey));
        }

        if ($account === null || ! $this->matches($account->getAuthPassword(), $secret)) {
            $this->rateLimiter->hit($lockoutKey, $this->config->integer('api.sign_in_lockout.lockout_minutes') * 60);

            throw new InvalidCredentialsException;
        }

        $this->rateLimiter->clear($lockoutKey);

        return $account;
    }

    private function matches(mixed $storedHash, string $secret): bool
    {
        if (! is_string($storedHash) || $storedHash === '') {
            $this->hasher->check($secret, $this->timingEqualiserHash());

            return false;
        }

        return $this->hasher->check($secret, $storedHash);
    }

    /**
     * A real hash to check against when there is no account, so both paths cost the same.
     */
    private function timingEqualiserHash(): string
    {
        static $hash = null;

        return $hash ??= $this->hasher->make('timing-equaliser');
    }
}
