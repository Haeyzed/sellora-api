<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

/**
 * Whether a gateway connection uses test or live keys, worked out from the keys. Test payments never pay a live order, and live payments never pay a test order.
 */
enum GatewayMode: string
{
    case Test = 'test';
    case Live = 'live';

    public function isTest(): bool
    {
        return $this === self::Test;
    }

    public static function forTest(bool $isTest): self
    {
        return $isTest ? self::Test : self::Live;
    }
}
