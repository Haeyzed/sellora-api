<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Data;

use App\Tenant\Customers\Enums\SocialProvider;

/**
 * What a provider told the store about the person signing in (Step 10b). Only the provider's user ID identifies them; the email is believed verified only when the provider is trusted and says so.
 */
final readonly class SocialProfile
{
    /**
     * @param  string|null  $email  Trimmed and lower-cased; null when the provider gave none.
     * @param  bool  $emailVerified  Whether the provider said the email is verified (not yet whether the store believes it).
     * @param  string|null  $name  Display text only.
     */
    public function __construct(
        public SocialProvider $provider,
        public string $providerUserId,
        public ?string $email,
        public bool $emailVerified,
        public bool $isPrivateRelay,
        public ?string $name,
    ) {}

    /**
     * Whether the store believes the email is the person's: the provider is trusted and says it is verified.
     */
    public function hasTrustedEmail(): bool
    {
        return $this->email !== null && $this->emailVerified && $this->provider->isTrustedForEmail();
    }
}
