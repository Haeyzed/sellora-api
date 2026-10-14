<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The account itself. Erasing it clears every personal field and keeps the row, so past orders still point at something and the email is free again (section 3.3).
 *
 * Registered last, so the other customer handlers still find the email and
 * phone they need to erase contact-based records (marketing consent) first.
 */
final readonly class CustomerAccountData implements PersonalDataHandler
{
    /**
     * @return list<array<string, mixed>>
     */
    public function export(DataSubject $subject): array
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer === null) {
            return [];
        }

        return [[
            'id' => $customer->public_id,
            'name' => $customer->name,
            'email' => $customer->email,
            'email_verified_at' => $customer->email_verified_at?->toIso8601String(),
            'phone' => $customer->phone,
            'locale' => $customer->locale,
            'tax_id' => $customer->tax_id,
            'tax_id_country' => $customer->tax_id_country,
            'is_tax_exempt' => $customer->is_tax_exempt,
            'is_active' => $customer->is_active,
            'last_signed_in_at' => $customer->last_signed_in_at?->toIso8601String(),
            'created_at' => $customer->created_at?->toIso8601String(),
        ]];
    }

    /**
     * Clears every personal field, signs the customer out everywhere, and deletes their pending links and password resets. The row is kept, in the trash.
     */
    public function erase(DataSubject $subject): void
    {
        $customer = CustomerDataSubject::customer($subject, lock: true);

        if ($customer === null || $customer->isErased()) {
            return;
        }

        $customer->tokens()->delete();
        $customer->emailTokens()->delete();
        DB::table(config()->string('auth.passwords.'.Customer::PASSWORD_BROKER.'.table'))->where('email', $customer->email)->delete();

        $customer->forceFill([
            'name' => null,
            'email' => null,
            'password' => null,
            'phone' => null,
            'locale' => null,
            'tax_id' => null,
            'tax_id_country' => null,
            'is_tax_exempt' => false,
            'is_active' => false,
            'default_shipping_address_id' => null,
            'default_billing_address_id' => null,
            'erased_at' => CarbonImmutable::now(),
            'deleted_at' => $customer->deleted_at ?? CarbonImmutable::now(),
        ])->save();
    }
}
