<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Services;

use App\Shared\Tenancy\TenantScopedKey;
use Illuminate\Cache\RateLimiter;

/**
 * How many messages a store may send a minute (config messaging.sending.per_minute), so one store can never flood the platform's mail provider or harm its reputation for everyone.
 *
 * A message over the limit is not dropped: SendMessage waits the time this
 * returns and tries again. The count may overshoot by one when two messages
 * are sent at the same moment, which never loses one.
 */
final readonly class SendingLimit
{
    public function __construct(private RateLimiter $rateLimiter) {}

    /**
     * Counts one message as sent now and returns 0, or returns how many seconds to wait first when the store has used up this minute's messages (and counts nothing).
     */
    public function secondsToWaitBeforeSending(): int
    {
        $key = TenantScopedKey::make('messaging', 'sent');

        if ($this->rateLimiter->tooManyAttempts($key, config()->integer('messaging.sending.per_minute'))) {
            return max(1, $this->rateLimiter->availableIn($key));
        }

        $this->rateLimiter->hit($key, 60);

        return 0;
    }
}
