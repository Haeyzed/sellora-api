<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Resources;

use App\Tenant\Returns\Models\ReturnSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How the store takes returns.
 *
 * @mixin ReturnSettings
 */
final class ReturnSettingsResource extends JsonResource
{
    public function __construct(ReturnSettings $settings)
    {
        parent::__construct($settings);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** How many days after a line's delivery a buyer may ask to return it; 0 means only staff can start returns. */
            'window_days' => $this->window_days,
            /** Whether customers and guests may ask for returns themselves; staff always can. */
            'customer_requests' => $this->customer_requests,
            /** Whether a buyer must add a photo when the reason claims the store was at fault. */
            'photos_required' => $this->photos_required,
            /** Whether the buyer pays for an exchange's replacement delivery when the store wasn't at fault; always free when it was. */
            'charge_shipping_on_exchanges' => $this->charge_shipping_on_exchanges,
        ];
    }
}
