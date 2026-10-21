<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Customers\Models\CustomerIdentity;
use App\Tenant\Customers\Models\SocialSignInAttempt;
use App\Tenant\Customers\Models\SocialSignInTicket;

/**
 * The providers a customer signs in with (Step 10b): exported without the provider's own ID for them, and removed by erasure, with any sign-in in progress for them.
 */
final readonly class SocialIdentityData implements PersonalDataHandler
{
    /**
     * @return list<array{provider: string, email: string|null, is_private_relay: bool, connected_at: string, last_used_at: string|null}>
     */
    public function export(DataSubject $subject): array
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer === null) {
            return [];
        }

        return array_values($customer->identities()->orderBy('id')->get()->map(static fn (CustomerIdentity $identity): array => [
            'provider' => $identity->provider->value,
            'email' => $identity->email,
            'is_private_relay' => $identity->is_private_relay,
            'connected_at' => $identity->created_at->toIso8601String(),
            'last_used_at' => $identity->last_used_at?->toIso8601String(),
        ])->all());
    }

    public function erase(DataSubject $subject): void
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer === null) {
            return;
        }

        $customer->identities()->delete();
        SocialSignInAttempt::query()->where('customer_id', $customer->id)->delete();
        SocialSignInTicket::query()->where('customer_id', $customer->id)->delete();
    }
}
