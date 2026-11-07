<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Actions;

use App\Shared\Tenancy\TenantScopedKey;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Messaging\Data\StaffAlertData;
use App\Tenant\Messaging\Enums\StaffAlertKind;
use App\Tenant\Messaging\Jobs\SendMessage;
use App\Tenant\Messaging\Models\MessagingSettings;
use App\Tenant\Messaging\Models\StaffAlert;
use App\Tenant\Messaging\Services\StaffAlertEmailCap;
use App\Tenant\Messaging\Services\StaffAlertRecipients;
use App\Tenant\Messaging\StaffAddressee;
use App\Tenant\Messaging\StaffAlertMessage;
use App\Tenant\Messaging\StaffAlertsWaitingMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Raises a staff alert: lists it for the staff holding its permission and emails them, once per thing it is about (or once a day for kinds that may come again).
 *
 * The repeat key is unique in the database, so the same alert raised twice,
 * even at the same moment, is saved and emailed once. A new-order alert is
 * emailed only while the messaging settings say so. Past the hourly email cap
 * an alert is only listed, and each recipient gets one "alerts waiting" email
 * that hour instead; money alerts are always emailed.
 */
final readonly class RaiseStaffAlert
{
    public function __construct(
        private StaffAlertRecipients $recipients,
        private StaffAlertEmailCap $emailCap,
    ) {}

    /**
     * @return StaffAlert|null The alert raised; null when it was raised before.
     */
    public function handle(StaffAlertData $data): ?StaffAlert
    {
        $alert = $this->save($data);

        if ($alert === null) {
            return null;
        }

        $this->email($alert);

        return $alert;
    }

    private function save(StaffAlertData $data): ?StaffAlert
    {
        $now = CarbonImmutable::now();
        $repeatKey = implode(':', array_filter([$data->kind->value, $data->subjectType, $data->subjectId, $data->kind->repeatsDaily() ? $now->toDateString() : null]));

        $inserted = StaffAlert::query()->insertOrIgnore([
            'public_id' => (string) Str::ulid(),
            'kind' => $data->kind->value,
            'repeat_key' => $repeatKey,
            'subject_type' => $data->subjectType,
            'subject_id' => $data->subjectId,
            'details' => json_encode($data->details, JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        return $inserted === 0 ? null : StaffAlert::query()->where('repeat_key', $repeatKey)->sole();
    }

    private function email(StaffAlert $alert): void
    {
        if ($alert->kind === StaffAlertKind::NewOrder && ! MessagingSettings::current()->has_new_order_alert_emails) {
            return;
        }

        $recipients = $this->recipients->for($alert->kind);

        if ($recipients === []) {
            return;
        }

        if (! $this->emailCap->claimEmail($alert)) {
            $this->tellAlertsAreWaiting($recipients);

            return;
        }

        foreach ($recipients as $recipient) {
            SendMessage::dispatch(new StaffAlertMessage($alert->id), new StaffAddressee($recipient->id));
        }
    }

    /**
     * One "alerts waiting" email an hour per recipient, while their alerts are held back.
     *
     * @param  list<StaffMember>  $recipients
     */
    private function tellAlertsAreWaiting(array $recipients): void
    {
        $hour = CarbonImmutable::now()->format('Y-m-d-H');

        foreach ($recipients as $recipient) {
            if (Cache::add(TenantScopedKey::make('staff-alerts-waiting', (string) $recipient->id, $hour), true, 3600)) {
                SendMessage::dispatch(new StaffAlertsWaitingMessage, new StaffAddressee($recipient->id));
            }
        }
    }
}
