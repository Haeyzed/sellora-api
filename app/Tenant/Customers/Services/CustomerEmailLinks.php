<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Services;

use App\Tenant\Customers\CustomerEmailLinkNotification;
use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerEmailToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Emails a customer a fresh one-time link. Any earlier link of theirs for the same purpose stops working.
 */
final readonly class CustomerEmailLinks
{
    /**
     * Saves the new link's hash and queues the email to the address for after the transaction commits.
     */
    public function send(Customer $customer, CustomerEmailTokenPurpose $purpose, string $email): void
    {
        $token = Str::random(64);

        CustomerEmailToken::query()->updateOrCreate(
            ['customer_id' => $customer->id, 'purpose' => $purpose->value],
            [
                'email' => $email,
                'token_hash' => CustomerEmailToken::hash($token),
                'expires_at' => CarbonImmutable::now()->addMinutes($purpose->expiresInMinutes()),
                'created_at' => CarbonImmutable::now(),
            ],
        );

        $notification = CustomerEmailLinkNotification::forToken($purpose, $token);

        if ($customer->locale !== null) {
            $notification->locale($customer->locale);
        }

        Notification::route('mail', $email)->notify($notification);
    }
}
