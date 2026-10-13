<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Tenant\Customers\Enums\MarketingChannel;
use App\Tenant\Customers\Models\MarketingConsent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer's current answer on one channel, with the consent text to show for it now.
 *
 * @property array{channel: MarketingChannel, contact: string|null, latest: MarketingConsent|null, wording: string} $resource
 */
final class MarketingConsentStateResource extends JsonResource
{
    /**
     * @param  array{channel: MarketingChannel, contact: string|null, latest: MarketingConsent|null, wording: string}  $state
     */
    public function __construct(array $state)
    {
        parent::__construct($state);
    }

    /**
     * @return array{channel: string, contact: string|null, granted: bool, answered_at: string|null, wording: string}
     */
    public function toArray(Request $request): array
    {
        $latest = $this->resource['latest'];

        return [
            'channel' => $this->resource['channel']->value,
            /** The email or phone the answer is for; null when the account has no phone. */
            'contact' => $this->resource['contact'],
            /** Whether the customer currently agrees to marketing on this channel. */
            'granted' => $latest?->status->value === 'granted',
            /** When the current answer was given; null when there is none yet. */
            'answered_at' => $latest?->created_at->toIso8601String(),
            /** The text to show next to the choice, in the request's language. Agreeing records exactly this text. */
            'wording' => $this->resource['wording'],
        ];
    }
}
