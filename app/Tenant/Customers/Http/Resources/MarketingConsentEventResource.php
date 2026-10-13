<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Tenant\Customers\Models\MarketingConsent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One recorded consent answer, as staff see it in a customer's consent history.
 *
 * @property MarketingConsent $resource
 */
final class MarketingConsentEventResource extends JsonResource
{
    public function __construct(MarketingConsent $consent)
    {
        parent::__construct($consent);
    }

    /**
     * @return array<string, string|bool|null>
     */
    public function toArray(Request $request): array
    {
        $consent = $this->resource;

        return [
            'id' => $consent->public_id,
            'channel' => $consent->channel->value,
            'contact' => $consent->contact,
            'status' => $consent->status->value,
            /** account, checkout (perhaps as a guest) or staff. */
            'source' => $consent->source->value,
            /** The exact text shown; null for a withdrawal staff recorded. */
            'wording' => $consent->wording,
            'locale' => $consent->locale,
            /** Whether a staff member recorded it, at the customer's request. */
            'recorded_by_staff' => $consent->recorded_by_staff_id !== null,
            'created_at' => $consent->created_at->toIso8601String(),
        ];
    }
}
