<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Messaging\Data\MessagingSettingsChanges;
use App\Tenant\Messaging\Models\MessagingSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changing the messaging settings, which needs the settings.manage permission. Send only what changes.
 */
final class ChangeMessagingSettingsRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', MessagingSettings::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The name the store's emails come from, up to 80 characters; null (or blank) to use the store's own name. The address they come from is the platform's. */
            'sender_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            /** Where replies to the store's emails go; null to use the store's contact email. */
            'reply_to_email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:254'],
            /** Whether staff who fulfil orders are also emailed about each new order. A new order always appears in their alert list. */
            'has_new_order_alert_emails' => ['sometimes', 'boolean'],
        ];
    }

    public function changes(): MessagingSettingsChanges
    {
        $senderName = $this->validated('sender_name');
        $replyToEmail = $this->validated('reply_to_email');

        return new MessagingSettingsChanges(
            changesSenderName: $this->has('sender_name'),
            senderName: is_string($senderName) ? trim($senderName) : null,
            changesReplyToEmail: $this->has('reply_to_email'),
            replyToEmail: is_string($replyToEmail) ? mb_strtolower($replyToEmail) : null,
            hasNewOrderAlertEmails: $this->has('has_new_order_alert_emails') ? $this->boolean('has_new_order_alert_emails') : null,
        );
    }
}
