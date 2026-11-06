<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Actions;

use App\Tenant\Messaging\Data\MessagingSettingsChanges;
use App\Tenant\Messaging\Exceptions\MissingMessagingSettingsException;
use App\Tenant\Messaging\Models\MessagingSettings;
use Illuminate\Support\Facades\DB;

/**
 * Changes who the store's emails come from, where replies go, and whether staff are emailed about each new order. What is left out stays as it is; every change is audited.
 *
 * The row is changed under its lock, so two changes at once both land, one
 * after the other. It always exists: it is created with the store.
 */
final readonly class ChangeMessagingSettings
{
    /**
     * @throws MissingMessagingSettingsException When the store has no messaging settings: a bug, never filled in with defaults.
     */
    public function handle(MessagingSettingsChanges $changes): MessagingSettings
    {
        return DB::transaction(static function () use ($changes): MessagingSettings {
            $settings = MessagingSettings::query()->whereKey(1)->lockForUpdate()->first() ?? throw new MissingMessagingSettingsException;

            if ($changes->changesSenderName) {
                $settings->sender_name = $changes->senderName;
            }

            if ($changes->changesReplyToEmail) {
                $settings->reply_to_email = $changes->replyToEmail;
            }

            if ($changes->hasNewOrderAlertEmails !== null) {
                $settings->has_new_order_alert_emails = $changes->hasNewOrderAlertEmails;
            }

            $settings->save();

            return $settings;
        });
    }
}
