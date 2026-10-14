<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Data;

use App\Tenant\Customers\Enums\MarketingChannel;
use App\Tenant\Customers\Models\MarketingConsent;

/**
 * A customer's current answer on one channel, with the consent text to show for it now.
 */
final readonly class MarketingConsentState
{
    /**
     * @param  string|null  $contact  The email or phone the answer is for; null when the account has no phone.
     * @param  MarketingConsent|null  $latest  The latest event for the contact; null when there is no answer yet.
     * @param  string  $wording  The consent text in the request's language.
     */
    public function __construct(
        public MarketingChannel $channel,
        public ?string $contact,
        public ?MarketingConsent $latest,
        public string $wording,
    ) {}
}
