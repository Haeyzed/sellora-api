<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Tenancy\TenantScopedKey;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Defines the named rate limits that protect the platform from abuse, such as password guessing or checkout flooding.
 *
 * Every limit is counted per store (see TenantScopedKey), so one store's
 * traffic never uses up another store's limit. Limits are per minute and
 * configured in config/api.php. Routes opt in with `throttle:<name>`.
 */
final class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * Registers every named rate limit.
     */
    public function boot(): void
    {
        $this->defineRequestLimits();
        $this->defineSignInLimits();
        $this->defineStoreWideLimits();
    }

    private function defineRequestLimits(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => $this->perMinute('api')->by($this->requesterKey('api', $request)));
        RateLimiter::for('public', fn (Request $request): Limit => $this->perMinute('public')->by($this->ipKey('public', $request)));
        RateLimiter::for('registration', fn (Request $request): Limit => $this->perMinute('registration')->by($this->ipKey('registration', $request)));
        RateLimiter::for('checkout', fn (Request $request): Limit => $this->perMinute('checkout')->by($this->requesterKey('checkout', $request)));
        RateLimiter::for('coupon-redemption', fn (Request $request): Limit => $this->perMinute('coupon_redemption')->by($this->requesterKey('coupon-redemption', $request)));
    }

    /**
     * Sign-in limits count per IP address and per account identifier together,
     * so an attacker can't spread guesses across accounts from one address, and
     * formatting an email or phone differently doesn't reset the count.
     */
    private function defineSignInLimits(): void
    {
        RateLimiter::for('login', fn (Request $request): Limit => $this->perMinute('login')->by($this->identifierKey('login', $request)));
        RateLimiter::for('password-reset', fn (Request $request): Limit => $this->perMinute('password_reset')->by($this->identifierKey('password-reset', $request)));
        RateLimiter::for('one-time-password', fn (Request $request): Limit => $this->perMinute('one_time_password')->by($this->identifierKey('one-time-password', $request)));
    }

    /**
     * Store-wide limits stop one busy store from filling shared queues or webhook capacity.
     * Queued bulk jobs use them through Laravel's `RateLimited` job middleware.
     */
    private function defineStoreWideLimits(): void
    {
        RateLimiter::for('tenant-webhooks', fn (): Limit => $this->perMinute('tenant_webhooks')->by(TenantScopedKey::make('webhooks')));
        RateLimiter::for('tenant-bulk-jobs', fn (): Limit => $this->perMinute('tenant_bulk_jobs')->by(TenantScopedKey::make('bulk-jobs')));
    }

    private function perMinute(string $limitName): Limit
    {
        return Limit::perMinute(config()->integer('api.rate_limits.'.$limitName));
    }

    private function requesterKey(string $limitName, Request $request): string
    {
        $userId = Auth::id();

        if ($userId === null) {
            return $this->ipKey($limitName, $request);
        }

        return TenantScopedKey::make($limitName, Auth::getDefaultDriver(), (string) $userId);
    }

    private function ipKey(string $limitName, Request $request): string
    {
        return TenantScopedKey::make($limitName, 'ip', (string) $request->ip());
    }

    private function identifierKey(string $limitName, Request $request): string
    {
        return TenantScopedKey::forIdentifier($limitName, (string) $request->ip().'|'.$this->normalisedIdentifier($request));
    }

    /**
     * The email (lower-cased) or phone (digits only) the request is about, so "Ada@Mail.com" and "ada@mail.com" count as one.
     */
    private function normalisedIdentifier(Request $request): string
    {
        $email = $request->string('email')->trim()->lower()->value();

        if ($email !== '') {
            return $email;
        }

        return (string) preg_replace('/\D+/', '', $request->string('phone')->value());
    }
}
