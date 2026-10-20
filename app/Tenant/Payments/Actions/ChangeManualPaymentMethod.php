<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Payments\Data\ManualPaymentMethod;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Models\PaymentMethodSetting;
use App\Tenant\Payments\Services\PaymentActivity;
use Illuminate\Support\Facades\DB;

/**
 * Sets up a payment method paid by hand: whether buyers are offered it, how long they have to pay (within the limits in config/payments.php, already validated), and what they are told. Only orders placed afterwards get the new deadline.
 */
final readonly class ChangeManualPaymentMethod
{
    public function __construct(
        private FindManualPaymentMethods $findManualPaymentMethods,
        private PaymentActivity $paymentActivity,
    ) {}

    /**
     * @param  array{enabled?: bool, deadline_hours?: int, collection_alert_hours?: int, instructions?: string|null}  $changes
     */
    public function handle(StaffMember $actor, PaymentMethod $method, array $changes): ManualPaymentMethod
    {
        $setting = DB::transaction(function () use ($method, $changes): PaymentMethodSetting {
            $current = $this->findManualPaymentMethods->find($method);
            $setting = PaymentMethodSetting::query()->where('method', $method->value)->lockForUpdate()->first() ?? new PaymentMethodSetting;

            $setting->forceFill([
                'method' => $method,
                'enabled' => $changes['enabled'] ?? $current->enabled,
                'deadline_hours' => $changes['deadline_hours'] ?? $current->deadlineHours,
                'collection_alert_hours' => $method === PaymentMethod::CashOnDelivery ? ($changes['collection_alert_hours'] ?? $current->collectionAlertHours) : null,
                'instructions' => array_key_exists('instructions', $changes) ? $changes['instructions'] : $current->instructions,
            ])->save();

            return $setting;
        });

        $this->paymentActivity->record($actor, $setting, 'payment_method_changed', 'Changed how '.str_replace('_', ' ', $method->value).' is offered', array_diff_key($changes, ['instructions' => true]));

        return $this->findManualPaymentMethods->find($method);
    }
}
