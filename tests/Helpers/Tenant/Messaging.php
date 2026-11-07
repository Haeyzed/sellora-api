<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\FeatureDefinition;
use App\Shared\Features\FeatureKind;
use App\Shared\Features\FeatureRegistry;
use App\Shared\Features\Features;
use App\Shared\Money\Money;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Messaging\MessageChannels;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Models\Refund;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Messaging\FakeTextChannel;

/*
|--------------------------------------------------------------------------
| For the Messaging tests (Step 20)
|--------------------------------------------------------------------------
*/

/**
 * A store for the messaging tests. Sets test()->store.
 */
function messagingStore(string $subdomain = 'messaging-store'): Tenant
{
    $store = createStore($subdomain, ['country_code' => 'NG']);
    test()->store = $store;

    return $store;
}

/**
 * Registers the fake text channel under its integration's feature key and puts that integration in the state given for test()->store ("enabled", "locked", "disabled", "suspended" or "unavailable").
 */
function useFakeTextChannel(string $state): void
{
    $features = app(FeatureRegistry::class);

    if (! $features->has(FakeTextChannel::FEATURE)) {
        $features->register(new FeatureDefinition(FakeTextChannel::FEATURE, FeatureKind::Integration));
    }

    FakeTextChannel::$sent = [];
    app(MessageChannels::class)->add(FakeTextChannel::class, FakeTextChannel::FEATURE);

    $source = app(FeatureSource::class);

    if (! $source instanceof FakeFeatureSource) {
        throw new LogicException('The channel tests need the fake feature source.');
    }

    $integration = FakeTextChannel::FEATURE;
    $source->give(test()->store->getTenantKey(), match ($state) {
        'enabled' => FakeFeatureSource::snapshot(entitled: [$integration]),
        'locked' => FakeFeatureSource::snapshot(removed: [$integration]),
        'disabled' => FakeFeatureSource::snapshot(entitled: [$integration], merchantDisabled: [$integration]),
        'suspended' => FakeFeatureSource::snapshot(entitled: [$integration], isStoreSuspended: true),
        'unavailable' => FakeFeatureSource::snapshot(),
    });
    app(Features::class)->forget(test()->store->getTenantKey());
}

/**
 * What the callback returns while the application's language is the one given, as a message is written when it is sent.
 *
 * @template TResult
 *
 * @param  Closure(): TResult  $write
 * @return TResult
 */
function writtenIn(string $locale, Closure $write): mixed
{
    $previous = app()->getLocale();
    app()->setLocale($locale);

    try {
        return $write();
    } finally {
        app()->setLocale($previous);
    }
}

/**
 * A staff request under /api/v1/staff/messaging, as the staff member given or signed out.
 *
 * @param  array<string, mixed>  $data
 */
function messagingStaffRequest(string $method, string $path, array $data = [], ?StaffMember $as = null, string $subdomain = 'messaging-store'): TestResponse
{
    forgetSignIns();
    $request = $as === null ? test()->withoutToken() : test()->withToken(staffTokenFor($as, $subdomain));
    $response = $request->json($method, storeUrl($subdomain, '/api/v1/staff/messaging/'.ltrim($path, '/')), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * Every email really sent so far (the array mailer of the tests), oldest first.
 *
 * @return list<SentMessage>
 */
function sentEmails(): array
{
    $transport = app('mailer')->getSymfonyTransport();

    if (! $transport instanceof ArrayTransport) {
        throw new LogicException('The tests send email with the array mailer.');
    }

    return array_values($transport->messages()->all());
}

/**
 * The email that was sent, with its headers and body.
 */
function sentEmail(SentMessage $sent): Email
{
    $email = $sent->getOriginalMessage();

    if (! $email instanceof Email) {
        throw new LogicException('Only emails are sent.');
    }

    return $email;
}

/**
 * Order #1042 (public ID "order-1") for 2,500 naira, built in memory, for what staff alerts are about.
 */
function alertOrder(): Order
{
    return (new Order)->forceFill(['public_id' => 'order-1', 'number' => 1042, 'is_test' => false, 'total' => Money::ofMinor(250000, 'NGN')]);
}

/**
 * A 2,500 naira payment (public ID "payment-1") for order #1042, built in memory.
 */
function alertPayment(): Payment
{
    return (new Payment)->forceFill(['public_id' => 'payment-1', 'amount' => Money::ofMinor(250000, 'NGN')])->setRelation('order', alertOrder());
}

/**
 * A 30 naira part of a refund (public ID "payment-refund-1") of order #1042, built in memory.
 */
function alertPaymentRefund(): PaymentRefund
{
    return (new PaymentRefund)->forceFill(['public_id' => 'payment-refund-1', 'amount' => Money::ofMinor(3000, 'NGN')])
        ->setRelation('refund', (new Refund)->setRelation('order', alertOrder()));
}

/**
 * A 10 naira gift card hold (public ID "hold-1") for order #1042, built in memory.
 */
function alertHold(): CheckoutPaymentHold
{
    return (new CheckoutPaymentHold)->forceFill(['public_id' => 'hold-1', 'method' => 'gift_card', 'amount' => Money::ofMinor(1000, 'NGN')])->setRelation('order', alertOrder());
}

/**
 * Every store message emailed so far (Notification::fake()), in the order sent, as [address, language, subject], each subject written in its email's language inside test()->store.
 *
 * @return list<array{0: string, 1: string, 2: string}>
 */
function alertEmailsSent(): array
{
    $fake = Illuminate\Support\Facades\Notification::getFacadeRoot();

    if (! $fake instanceof Illuminate\Support\Testing\Fakes\NotificationFake) {
        throw new LogicException('Fake notifications first (Notification::fake()).');
    }

    $sent = [];

    foreach ($fake->sentNotifications()[Illuminate\Notifications\AnonymousNotifiable::class] ?? [] as $byNotification) {
        foreach ($byNotification[App\Tenant\Messaging\MessageEmailNotification::class] ?? [] as $record) {
            $sent[] = $record;
        }
    }

    return test()->store->run(static fn (): array => array_map(static function (array $record): array {
        $notifiable = $record['notifiable'];
        $route = $notifiable->routeNotificationFor('mail');
        $address = is_array($route) ? (string) array_key_first($route) : (string) $route;
        $locale = (string) $record['locale'];

        return [$address, $locale, (string) writtenIn($locale, static fn () => $record['notification']->toMail($notifiable))->subject];
    }, $sent));
}
