<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\FindMarketingConsents;
use App\Tenant\Customers\Actions\RecordMarketingConsent;
use App\Tenant\Customers\Enums\MarketingChannel;
use App\Tenant\Customers\Enums\MarketingConsentSource;
use App\Tenant\Customers\Enums\MarketingConsentStatus;
use App\Tenant\Customers\Exceptions\MarketingContactMissingException;
use App\Tenant\Customers\Http\Requests\AnswerMarketingConsentRequest;
use App\Tenant\Customers\Http\Resources\MarketingConsentStateResource;
use App\Tenant\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\App;
use LogicException;

/**
 * The signed-in customer's marketing consent, one answer per channel.
 */
final class MarketingConsentController extends Controller
{
    /**
     * List my marketing consent.
     *
     * Each channel's current answer for the account's email and phone, with
     * the consent text to show next to it in the request's language.
     */
    public function index(Request $request, FindMarketingConsents $findMarketingConsents, RecordMarketingConsent $recordMarketingConsent): AnonymousResourceCollection
    {
        $states = array_map(
            static fn (array $state): MarketingConsentStateResource => new MarketingConsentStateResource([...$state, 'wording' => $recordMarketingConsent->wording($state['channel'], App::getLocale())['text']]),
            array_values($findMarketingConsents->forCustomer($this->customer($request))),
        );

        return MarketingConsentStateResource::collection($states);
    }

    /**
     * Answer for a channel.
     *
     * Agreeing records the consent text from "list my marketing consent" in
     * the same language; false withdraws. Each answer is kept as it was
     * given. Text messages need a phone number on the account.
     *
     * @param  string  $channel  email or sms.
     *
     * @throws MarketingContactMissingException
     */
    public function update(AnswerMarketingConsentRequest $request, FindMarketingConsents $findMarketingConsents, RecordMarketingConsent $recordMarketingConsent, string $channel): MarketingConsentStateResource
    {
        $customer = $request->customer();
        $marketingChannel = MarketingChannel::tryFrom($channel) ?? abort(404);
        $contact = FindMarketingConsents::contactOf($customer, $marketingChannel) ?? throw new MarketingContactMissingException;

        $recordMarketingConsent->handle(
            channel: $marketingChannel,
            contact: $contact,
            status: $request->boolean('granted') ? MarketingConsentStatus::Granted : MarketingConsentStatus::Withdrawn,
            source: MarketingConsentSource::Account,
            locale: App::getLocale(),
            customer: $customer,
        );

        $state = $findMarketingConsents->forCustomer($customer)[$marketingChannel->value];

        return new MarketingConsentStateResource([...$state, 'wording' => $recordMarketingConsent->wording($marketingChannel, App::getLocale())['text']]);
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            throw new LogicException('This route must be protected by auth:customer.');
        }

        return $customer;
    }
}
