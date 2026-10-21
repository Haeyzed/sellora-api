<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Services;

use App\Tenant\Customers\Data\SocialProfile;
use App\Tenant\Customers\Enums\SocialSignInTicketKind;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\SocialSignInTicket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Tickets for what a finished social sign-in still needs from the customer (Step 10b): proof they own the existing account with the provider's email, or an email when the provider gave none.
 *
 * A ticket holds what the provider sent, so the provider isn't asked again;
 * only its hash is stored, it works once, and it expires within minutes.
 */
final readonly class SocialSignInTickets
{
    /**
     * @return string The ticket, shown once.
     */
    public function issue(SocialSignInTicketKind $kind, SocialProfile $profile, ?Customer $account = null): string
    {
        $token = Str::random(64);

        $ticket = new SocialSignInTicket;
        $ticket->forceFill([
            'token_hash' => SocialSignInTicket::hash($token),
            'kind' => $kind,
            'provider' => $profile->provider,
            'provider_user_id' => $profile->providerUserId,
            'email' => $profile->email,
            'email_verified' => $profile->emailVerified,
            'is_private_relay' => $profile->isPrivateRelay,
            'name' => $profile->name,
            'customer_id' => $account?->id,
            'expires_at' => CarbonImmutable::now()->addMinutes(config()->integer('customers.social.ticket_expire_minutes')),
            'created_at' => CarbonImmutable::now(),
        ])->save();

        return $token;
    }

    /**
     * The usable ticket of this kind, locked until the transaction ends; null when unknown, used or expired.
     */
    public function find(string $token, SocialSignInTicketKind $kind): ?SocialSignInTicket
    {
        return SocialSignInTicket::query()
            ->where('token_hash', SocialSignInTicket::hash($token))
            ->where('kind', $kind->value)
            ->where('expires_at', '>', CarbonImmutable::now())
            ->lockForUpdate()
            ->first();
    }

    /**
     * What the provider sent, as kept on the ticket.
     */
    public function profile(SocialSignInTicket $ticket): SocialProfile
    {
        return new SocialProfile($ticket->provider, $ticket->provider_user_id, $ticket->email, $ticket->email_verified, $ticket->is_private_relay, $ticket->name);
    }
}
