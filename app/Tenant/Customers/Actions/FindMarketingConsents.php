<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Enums\MarketingChannel;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\MarketingConsent;

/**
 * A customer's current answer on each channel: the latest event for their email, and for their phone if they have one.
 *
 * Consent belongs to the contact, so an answer given as a guest at checkout
 * with the same email counts, and changing the email starts with no answer.
 */
final readonly class FindMarketingConsents
{
    /**
     * @return array<string, array{channel: MarketingChannel, contact: string|null, latest: MarketingConsent|null}> By channel.
     */
    public function forCustomer(Customer $customer): array
    {
        $current = [];

        foreach (MarketingChannel::cases() as $channel) {
            $contact = self::contactOf($customer, $channel);

            $current[$channel->value] = [
                'channel' => $channel,
                'contact' => $contact,
                'latest' => $contact === null ? null : MarketingConsent::query()->latestFor($channel, $contact)->first(),
            ];
        }

        return $current;
    }

    /**
     * The customer's contact on a channel: their email, or their phone (null without one).
     */
    public static function contactOf(Customer $customer, MarketingChannel $channel): ?string
    {
        return match ($channel) {
            MarketingChannel::Email => $customer->email,
            MarketingChannel::Sms => $customer->phone,
        };
    }
}
