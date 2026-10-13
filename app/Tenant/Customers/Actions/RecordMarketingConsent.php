<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Enums\MarketingChannel;
use App\Tenant\Customers\Enums\MarketingConsentSource;
use App\Tenant\Customers\Enums\MarketingConsentStatus;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\MarketingConsent;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;

/**
 * Records a marketing consent answer for a channel and contact, with the exact wording shown, in the language shown (section 3.3).
 *
 * Single opt-in for now: a grant counts at once. Checkout (Step 16) calls it
 * for guests with no customer. Staff only ever record a withdrawal.
 */
final readonly class RecordMarketingConsent
{
    public function __construct(private FindStoreSettings $findStoreSettings) {}

    /**
     * The consent text for a channel, with the store's name, exactly as it must be shown and stored, and the language it is really in.
     *
     * Without a translation for the language asked for, the text is the
     * fallback language's, and that is the language recorded.
     *
     * @return array{text: string, locale: string}
     */
    public function wording(MarketingChannel $channel, string $locale): array
    {
        $key = 'customers.marketing_consent.'.$channel->value;
        $shownIn = Lang::hasForLocale($key, $locale) ? $locale : config()->string('app.fallback_locale');
        $text = __($key, ['store' => $this->findStoreSettings->handle()->name], $shownIn);

        return is_string($text) ? ['text' => $text, 'locale' => $shownIn] : throw new InvalidArgumentException("No consent wording for the {$channel->value} channel.");
    }

    /**
     * @param  string  $contact  An email in lower case, or a phone in E.164.
     * @param  string  $locale  The language the wording was shown in; the wording is stored for grants and for withdrawals given by the person themselves.
     *
     * @throws InvalidArgumentException When staff would grant consent, which they never may.
     */
    public function handle(
        MarketingChannel $channel,
        string $contact,
        MarketingConsentStatus $status,
        MarketingConsentSource $source,
        string $locale,
        ?Customer $customer = null,
        ?StaffMember $recordedBy = null,
    ): MarketingConsent {
        if (($source === MarketingConsentSource::Staff) !== ($recordedBy !== null) || ($recordedBy !== null && $status === MarketingConsentStatus::Granted)) {
            throw new InvalidArgumentException('Staff only ever record a withdrawal, and only staff record with the staff source.');
        }

        // Staff record what the customer told them; nobody was shown a text.
        $wording = $recordedBy === null ? $this->wording($channel, $locale) : null;

        return MarketingConsent::query()->create([
            'customer_id' => $customer?->id,
            'channel' => $channel,
            'contact' => $contact,
            'status' => $status,
            'source' => $source,
            'wording' => $wording['text'] ?? null,
            'locale' => $wording['locale'] ?? null,
            'recorded_by_staff_id' => $recordedBy?->id,
        ]);
    }
}
