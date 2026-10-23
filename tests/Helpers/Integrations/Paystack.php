<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Features;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Integrations\Paystack\PaystackGateway;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
|--------------------------------------------------------------------------
| For integrations/Paystack/tests
|--------------------------------------------------------------------------
*/

/*
 * Made-up Paystack secret keys: the shape of one, and never a real one. Each
 * is put together from two parts so that no key-shaped text is in the source,
 * which secret scanners (GitHub's push protection among them) rightly refuse.
 */

/** A made-up Paystack test secret key. */
const PAYSTACK_FAKE_TEST_KEY = 'sk_test_'.'fakepaystackkey0123456789';

/** A made-up Paystack live secret key. */
const PAYSTACK_FAKE_LIVE_KEY = 'sk_live_'.'fakepaystackkey0123456789';

/**
 * Puts Paystack on the test store's plan (or takes it off), with Paystack's API at a test address that only faked HTTP answers.
 */
function usePaystack(bool $onPlan = true): void
{
    config(['paystack.base_url' => 'https://paystack.test']);
    $storeId = (string) test()->store->getTenantKey();
    $snapshot = $onPlan ? FakeFeatureSource::snapshot(entitled: [PaystackGateway::KEY]) : FakeFeatureSource::snapshot();

    app()->instance(FeatureSource::class, (new FakeFeatureSource)->give($storeId, $snapshot));
    app(Features::class)->forget($storeId);
}

/**
 * Every message logged from now on, with its context, so a test can check a secret never reaches the logs.
 *
 * @return ArrayObject<int, string>
 */
function capturedLogs(): ArrayObject
{
    /** @var ArrayObject<int, string> $logged */
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, static fn (MessageLogged $message) => $logged->append($message->message.' '.json_encode($message->context)));

    return $logged;
}

/**
 * The requests sent to Paystack so far, by method and URL, with their Authorization header.
 *
 * @return list<array{0: string, 1: string, 2: string}>
 */
function paystackRequests(): array
{
    return app(HttpFactory::class)
        ->recorded(static fn (Request $request): bool => str_starts_with($request->url(), 'https://paystack.test/'))
        ->map(static fn (array $sent): array => [$sent[0]->method(), $sent[0]->url(), $sent[0]->header('Authorization')[0] ?? ''])
        ->values()
        ->all();
}
