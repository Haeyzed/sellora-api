<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Orders\Privacy\GuestDataSubject;
use Illuminate\Database\Eloquent\Builder;

/**
 * A person's checkouts (Step 16): what they told the store on the way to an order, abandoned or placed. A customer's are theirs by account; a guest's by the email they gave. Exported as they stand, and deleted on erasure: an order keeps its own copy.
 */
final readonly class CheckoutData implements PersonalDataHandler
{
    /**
     * @return list<array<string, mixed>>
     */
    public function export(DataSubject $subject): array
    {
        $checkouts = $this->checkoutsOf($subject);

        if ($checkouts === null) {
            return [];
        }

        return array_values($checkouts->orderBy('id')->get()->map(static fn (Checkout $checkout): array => [
            'id' => $checkout->public_id,
            'status' => $checkout->status->value,
            'contact' => ['email' => $checkout->contact_email, 'name' => $checkout->contact_name, 'phone' => $checkout->contact_phone],
            'shipping_address' => $checkout->shipping_address,
            'billing_address' => $checkout->billing_address,
            'tax_id' => $checkout->buyer_tax_id === null ? null : ['id' => $checkout->buyer_tax_id, 'country' => $checkout->buyer_tax_id_country],
            'marketing_email_consent' => $checkout->marketing_email_consent,
            'marketing_sms_consent' => $checkout->marketing_sms_consent,
            'note' => $checkout->customer_note,
            'last_activity_at' => $checkout->last_activity_at->toIso8601String(),
        ])->all());
    }

    public function erase(DataSubject $subject): void
    {
        $this->checkoutsOf($subject)?->delete();
    }

    /**
     * @return Builder<Checkout>|null
     */
    private function checkoutsOf(DataSubject $subject): ?Builder
    {
        if ($subject->type === GuestDataSubject::TYPE) {
            return Checkout::query()->whereNull('customer_id')->where('contact_email', $subject->publicId);
        }

        $customer = CustomerDataSubject::customer($subject);

        return $customer === null ? null : Checkout::query()->where('customer_id', $customer->id);
    }
}
