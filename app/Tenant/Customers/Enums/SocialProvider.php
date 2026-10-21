<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Enums;

/**
 * A provider customers can sign in with (Step 10b). Customers only: never staff, drivers or platform admins, whose two-factor sign-in it would bypass.
 */
enum SocialProvider: string
{
    case Google = 'google';
    case Apple = 'apple';

    /**
     * The providers the platform can offer: listed in config/customers.php and with credentials set in config/services.php.
     *
     * @return list<self>
     */
    public static function available(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $provider): bool => $provider->isAvailable()));
    }

    public function isAvailable(): bool
    {
        return in_array($this->value, config()->array('customers.social.providers'), true)
            && filled(config('services.'.$this->value.'.client_id'));
    }

    /**
     * Whether the store believes this provider when it says an email is verified (config/customers.php).
     */
    public function isTrustedForEmail(): bool
    {
        return in_array($this->value, config()->array('customers.social.trusted_email_providers'), true);
    }
}
