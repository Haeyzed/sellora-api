<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Http\Resources;

use App\Tenant\Messaging\Models\MessagingSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The messaging settings as staff see them.
 *
 * @property MessagingSettings $resource
 */
final class MessagingSettingsResource extends JsonResource
{
    public function __construct(MessagingSettings $settings)
    {
        parent::__construct($settings);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->resource;

        return [
            /** The name the store's emails come from; null while it is the store's own name. */
            'sender_name' => $settings->sender_name,
            /** Where replies go; null while it is the store's contact email. */
            'reply_to_email' => $settings->reply_to_email,
            /** Whether staff who fulfil orders are also emailed about each new order. */
            'has_new_order_alert_emails' => $settings->has_new_order_alert_emails,
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ];
    }
}
