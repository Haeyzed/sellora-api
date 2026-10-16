<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Customers\Actions\FindMarketingConsents;
use App\Tenant\Customers\Enums\MarketingChannel;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\MarketingConsent;
use Illuminate\Database\Eloquent\Builder;

/**
 * The customer's marketing consent events: the account's own, and any given for its current email or phone, for example as a guest at checkout.
 *
 * Erasure is the only thing allowed to delete consent events: the registry
 * runs every handler through PersonalDataErasure, which tells the database
 * so for that transaction (marketing_consents trigger).
 */
final readonly class MarketingConsentData implements PersonalDataHandler
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

        return array_values(self::eventsOf($customer)->orderBy('id')->get()->map(static fn (MarketingConsent $event): array => [
            'channel' => $event->channel->value,
            'contact' => $event->contact,
            'status' => $event->status->value,
            'source' => $event->source->value,
            'wording' => $event->wording,
            'locale' => $event->locale,
            'created_at' => $event->created_at->toIso8601String(),
        ])->all());
    }

    public function erase(DataSubject $subject): void
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer === null) {
            return;
        }

        self::eventsOf($customer)->delete();
    }

    /**
     * @return Builder<MarketingConsent>
     */
    private static function eventsOf(Customer $customer): Builder
    {
        return MarketingConsent::query()->where(static function (Builder $query) use ($customer): void {
            $query->where('customer_id', $customer->id);

            foreach (MarketingChannel::cases() as $channel) {
                $contact = FindMarketingConsents::contactOf($customer, $channel);

                if ($contact !== null) {
                    $query->orWhere(static fn (Builder $byContact) => $byContact->where('channel', $channel->value)->where('contact', $contact));
                }
            }
        });
    }
}
