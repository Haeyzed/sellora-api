<?php

declare(strict_types=1);

namespace App\Shared\Auth\TwoFactor;

use App\Shared\Tenancy\TenantScopedKey;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The short-lived step between a correct password and a correct two-factor code.
 *
 * After the password, the person gets a random challenge token instead of an
 * access token, and sends it back with their code. Only a hash of the token
 * is kept, in the cache under the current store's key, so a challenge from one
 * store or guard is never valid in another.
 */
final readonly class TwoFactorChallenges
{
    /**
     * The cache shared by every store, with the store in each key, as for rate limits.
     *
     * Taken once, when this class is first built (it is a singleton): inside a
     * store, tenancy swaps in a new cache manager on every request, whose
     * stores tag every entry with the store.
     */
    private CacheRepository $cache;

    public function __construct(
        CacheFactory $cacheFactory,
        private ConfigRepository $config,
    ) {
        $this->cache = $cacheFactory->store();
    }

    /**
     * Starts a challenge for an account whose password was correct.
     */
    public function start(Model $account, string $guard, string $deviceName): PendingTwoFactorChallenge
    {
        $challengeToken = Str::random(64);
        $expiresAt = CarbonImmutable::now()->addMinutes($this->config->integer('api.two_factor.challenge_minutes'));

        $this->cache->put($this->cacheKey($guard, $challengeToken), [
            'account_key' => $account->getKey(),
            'device_name' => $deviceName,
        ], $expiresAt);

        return new PendingTwoFactorChallenge($challengeToken, $expiresAt);
    }

    /**
     * The challenge for a token, or null when it is unknown, expired, already answered, or from another guard or store.
     */
    public function find(string $guard, string $challengeToken): ?StartedTwoFactorChallenge
    {
        $challenge = $this->cache->get($this->cacheKey($guard, $challengeToken));

        if (! is_array($challenge) || ! (is_int($challenge['account_key'] ?? null) || is_string($challenge['account_key'] ?? null)) || ! is_string($challenge['device_name'] ?? null)) {
            return null;
        }

        return new StartedTwoFactorChallenge($challenge['account_key'], $challenge['device_name']);
    }

    /**
     * Ends a challenge once answered, so its token can't be used again.
     */
    public function forget(string $guard, string $challengeToken): void
    {
        $this->cache->forget($this->cacheKey($guard, $challengeToken));
    }

    private function cacheKey(string $guard, string $challengeToken): string
    {
        return TenantScopedKey::make('two-factor-challenge', $guard, hash('sha256', $challengeToken));
    }
}
