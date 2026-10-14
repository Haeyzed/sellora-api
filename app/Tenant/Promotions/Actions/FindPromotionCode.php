<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Actions;

use App\Shared\Tenancy\TenantScopedKey;
use App\Tenant\Promotions\Enums\PromotionMethod;
use App\Tenant\Promotions\Enums\PromotionStatus;
use App\Tenant\Promotions\Exceptions\CodeNotValidException;
use App\Tenant\Promotions\Exceptions\TooManyCodeAttemptsException;
use App\Tenant\Promotions\Models\PromotionCode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Finds the code a shopper entered, for Cart and Checkout (section 6): matched ignoring case, and usable now.
 *
 * Every code that can't be used now gets the same answer, code_not_valid,
 * whatever the reason, so trying codes reveals nothing about which exist.
 * Each shopper (account, cart or IP address) may enter codes only so often
 * (the coupon_redemption limit, per minute). Codes that weren't valid are
 * also counted for the whole store, per hour; past a threshold a warning is
 * logged, without the codes tried. That count never affects shoppers.
 */
final readonly class FindPromotionCode
{
    private const string FAILURES_KEY = 'promotion-code-failures';

    public function __construct(private CacheFactory $cacheFactory) {}

    /**
     * A code a shopper entered.
     *
     * @param  string  $requesterKey  Who is entering it: the signed-in customer, the guest's cart, or the IP address.
     *
     * @throws TooManyCodeAttemptsException When the shopper has entered codes too often this minute.
     * @throws CodeNotValidException When the code can't be used now, for any reason.
     */
    public function forShopper(string $code, string $requesterKey, ?CarbonImmutable $at = null): PromotionCode
    {
        // Hashed: the requester may be an IP address, which never goes into the cache as it is.
        $limiterKey = TenantScopedKey::forIdentifier('coupon-redemption', $requesterKey);

        if (RateLimiter::tooManyAttempts($limiterKey, config()->integer('api.rate_limits.coupon_redemption'))) {
            throw new TooManyCodeAttemptsException;
        }

        RateLimiter::hit($limiterKey);

        try {
            return $this->find($code, $at);
        } catch (CodeNotValidException $exception) {
            $this->countFailure();

            throw $exception;
        }
    }

    /**
     * A code staff entered, for previews: the same checks, without the shopper's rate limit.
     *
     * @throws CodeNotValidException When the code can't be used now, for any reason.
     */
    public function find(string $code, ?CarbonImmutable $at = null): PromotionCode
    {
        $code = trim($code);

        if (preg_match(PromotionCode::FORMAT, $code) !== 1) {
            throw new CodeNotValidException;
        }

        $found = PromotionCode::query()->with('promotion')->whereRaw('upper(code) = ?', [mb_strtoupper($code)])->first();

        if ($found === null || ! $found->is_active || $found->promotion->method !== PromotionMethod::Code || $found->promotion->status($at) !== PromotionStatus::Running) {
            throw new CodeNotValidException;
        }

        return $found;
    }

    private function countFailure(): void
    {
        $cache = $this->cacheFactory->store();
        $key = TenantScopedKey::make(self::FAILURES_KEY, CarbonImmutable::now()->format('YmdH'));
        $cache->add($key, 0, 7200);
        $failures = (int) $cache->increment($key);

        if ($failures === config()->integer('promotions.failed_code_attempts_warning_per_hour')) {
            Log::warning('Many promotion codes that are not valid were entered in this store this hour.', ['failed_attempts' => $failures]);
        }
    }
}
