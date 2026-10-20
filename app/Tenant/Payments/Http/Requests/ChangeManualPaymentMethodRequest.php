<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Setting up a payment method paid by hand. Needs the payments.manage_gateways permission. A buyer's deadline stays within the platform's limits for the method: a bank transfer 24 hours to 7 days, cash on delivery 3 to 30 days (config/payments.php).
 */
final class ChangeManualPaymentMethodRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', PaymentGatewayConnection::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $method = $this->paymentMethod();
        $limits = 'payments.manual_methods.'.($method ?? PaymentMethod::BankTransfer)->value;
        $isOnDelivery = $method === PaymentMethod::CashOnDelivery;

        return [
            /** Whether buyers are offered it. Turning it off never touches payments already expected. */
            'enabled' => ['sometimes', 'boolean:strict'],
            /** How long a buyer has to pay, in hours: for cash on delivery, to get the order out of the door. Only orders placed afterwards get it. */
            'deadline_hours' => ['sometimes', 'integer:strict', 'between:'.config()->integer($limits.'.min_deadline_hours').','.config()->integer($limits.'.max_deadline_hours')],
            /** Cash on delivery only: how many hours after delivery staff are alerted that the cash isn't recorded as collected. */
            'collection_alert_hours' => [Rule::prohibitedIf(! $isOnDelivery), 'sometimes', 'integer:strict', 'between:1,'.config()->integer('payments.manual_methods.cash_on_delivery.max_collection_alert_hours')],
            /** What the buyer is told, such as the account to pay into; null to remove it. */
            'instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The method named in the URL; null while the API documentation is built.
     */
    public function paymentMethod(): ?PaymentMethod
    {
        $parameter = $this->route('method');
        $method = $parameter instanceof PaymentMethod ? $parameter : PaymentMethod::tryFrom(is_string($parameter) ? $parameter : '');

        return $method?->isManual() === true ? $method : null;
    }

    /**
     * @return array{enabled?: bool, deadline_hours?: int, collection_alert_hours?: int, instructions?: string|null}
     */
    public function changes(): array
    {
        /** @var array{enabled?: bool, deadline_hours?: int, collection_alert_hours?: int, instructions?: string|null} $changes */
        $changes = $this->safe()->only(['enabled', 'deadline_hours', 'collection_alert_hours', 'instructions']);

        return $changes;
    }
}
