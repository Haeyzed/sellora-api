<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Counts wrong secrets (passwords, PINs, two-factor codes) per key and pauses further tries after too many, with the limits in config/api.php.
 *
 * Callers build keys with TenantScopedKey, so stores never share a counter.
 */
final readonly class AttemptLockout
{
    public function __construct(
        private RateLimiter $rateLimiter,
        private ConfigRepository $config,
    ) {}

    /**
     * How long until tries are allowed again, or null when they are allowed now.
     */
    public function secondsUntilUnlocked(string $key): ?int
    {
        if (! $this->rateLimiter->tooManyAttempts($key, $this->config->integer('api.sign_in_lockout.max_failed_attempts'))) {
            return null;
        }

        return $this->rateLimiter->availableIn($key);
    }

    public function recordFailure(string $key): void
    {
        $this->rateLimiter->hit($key, $this->config->integer('api.sign_in_lockout.lockout_minutes') * 60);
    }

    public function clear(string $key): void
    {
        $this->rateLimiter->clear($key);
    }
}
