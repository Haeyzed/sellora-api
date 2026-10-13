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
use App\Tenant\Customers\Http\Requests\ListMarketingConsentsRequest;
use App\Tenant\Customers\Http\Requests\WithdrawMarketingConsentRequest;
use App\Tenant\Customers\Http\Resources\MarketingConsentEventResource;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\MarketingConsent;
use App\Tenant\Customers\Services\CustomerActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * Staff looking at a customer's marketing consent history, and recording a withdrawal the customer asked for. Staff never grant consent.
 */
final class StaffMarketingConsentController extends Controller
{
    /**
     * List a customer's consent history.
     *
     * Needs the customers.view permission. Newest first: every answer the
     * account gave, and every answer given for its current email or phone,
     * for example as a guest at checkout.
     */
    public function index(ListMarketingConsentsRequest $request, Customer $customer): AnonymousResourceCollection
    {
        $events = MarketingConsent::query()
            ->where(static function (Builder $query) use ($customer): void {
                $query->where('customer_id', $customer->id);

                foreach (MarketingChannel::cases() as $channel) {
                    $contact = FindMarketingConsents::contactOf($customer, $channel);

                    if ($contact !== null) {
                        $query->orWhere(static fn (Builder $byContact) => $byContact->where('channel', $channel->value)->where('contact', $contact));
                    }
                }
            })
            ->orderByDesc('id');

        return MarketingConsentEventResource::collection($events->cursorPaginate($request->perPage()));
    }

    /**
     * Record a withdrawal.
     *
     * Needs the customers.manage permission. For when the customer asks staff
     * to stop marketing on a channel, for example by phone. It goes in the
     * activity log too.
     *
     * @throws MarketingContactMissingException
     */
    public function withdraw(WithdrawMarketingConsentRequest $request, Customer $customer, RecordMarketingConsent $recordMarketingConsent, CustomerActivity $customerActivity): JsonResponse
    {
        $channel = $request->channel();
        $contact = FindMarketingConsents::contactOf($customer, $channel) ?? throw new MarketingContactMissingException;

        $consent = DB::transaction(static function () use ($request, $customer, $channel, $contact, $recordMarketingConsent, $customerActivity): MarketingConsent {
            $consent = $recordMarketingConsent->handle(
                channel: $channel,
                contact: $contact,
                status: MarketingConsentStatus::Withdrawn,
                source: MarketingConsentSource::Staff,
                locale: App::getLocale(),
                customer: $customer,
                recordedBy: $request->actor(),
            );
            $customerActivity->record($request->actor(), $customer, 'marketing_consent_withdrawn', "Recorded a {$channel->value} marketing withdrawal for");

            return $consent;
        });

        return (new MarketingConsentEventResource($consent))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
