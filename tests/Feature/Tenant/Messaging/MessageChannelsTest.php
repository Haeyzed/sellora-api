<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Messaging\Data\MessageRecipient;
use App\Tenant\Messaging\EmailChannel;
use App\Tenant\Messaging\Jobs\SendMessage;
use App\Tenant\Messaging\MessageChannels;
use App\Tenant\Messaging\MessageEmailNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Messaging\FakeAddressee;
use Tests\Fixtures\Messaging\FakeMessage;
use Tests\Fixtures\Messaging\FakeTextChannel;

/*
 * Step 20a: how a store's messages are sent. Each one is queued on the
 * messages queue, encrypted, once the change it is about has committed, and
 * written when it is sent, in the recipient's language. Core sends email;
 * an integration's channel (SMS, WhatsApp) is used only while its
 * integration is Enabled. Within a store's sending limit messages go at once;
 * over it they wait and go later, never dropped.
 */

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    messagingStore();
    Notification::fake();
});

afterEach(function (): void {
    deleteAllStores();
});

it('sends an email in the recipient\'s language, written when it is sent', function (): void {
    $this->store->run(static fn () => app()->call([new SendMessage(new FakeMessage('order 1042'), new FakeAddressee(new MessageRecipient('fr', 'ada@example.com', 'Ada'))), 'handle']));

    Notification::assertSentOnDemand(MessageEmailNotification::class, static function (MessageEmailNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        $mail = writtenIn((string) $notification->locale, static fn () => $notification->toMail($notifiable));

        return $notifiable->routeNotificationFor('mail') === ['ada@example.com' => 'Ada'] && $notification->locale === 'fr' && $mail->subject === 'Bonjour' && $mail->introLines === ['order 1042'];
    });
});

it('sends nothing to someone who can no longer receive messages, or through a channel that can\'t reach them', function (): void {
    useFakeTextChannel('enabled');

    $this->store->run(static function (): void {
        app()->call([new SendMessage(new FakeMessage, new FakeAddressee(null)), 'handle']);
        app()->call([new SendMessage(new FakeMessage, new FakeAddressee(new MessageRecipient('en', phone: '+2348012345678'))), 'handle']);
    });

    Notification::assertNothingSent();
    expect(FakeTextChannel::$sent)->toBe([['phone' => '+2348012345678', 'text' => 'Hello: test']]);
});

it('uses an integration\'s channel only while the integration is enabled for the store', function (string $state, bool $isUsed): void {
    useFakeTextChannel($state);

    $this->store->run(static fn () => app()->call([new SendMessage(new FakeMessage, new FakeAddressee(new MessageRecipient('fr', 'ada@example.com', phone: '+2348012345678'))), 'handle']));

    expect(FakeTextChannel::$sent)->toBe($isUsed ? [['phone' => '+2348012345678', 'text' => 'Bonjour: test']] : []);
    Notification::assertSentOnDemandTimes(MessageEmailNotification::class, 1);
})->with([
    'enabled' => ['enabled', true],
    'locked' => ['locked', false],
    'disabled' => ['disabled', false],
    'suspended' => ['suspended', false],
    'unavailable' => ['unavailable', false],
]);

it('holds a message over the store\'s sending limit and sends it later, never dropping it', function (): void {
    config(['messaging.sending.per_minute' => 1]);
    $recipient = new FakeAddressee(new MessageRecipient('en', 'ada@example.com'));

    [$first, $second] = $this->store->run(static function () use ($recipient): array {
        $first = (new SendMessage(new FakeMessage('first'), $recipient))->withFakeQueueInteractions();
        $second = (new SendMessage(new FakeMessage('second'), $recipient))->withFakeQueueInteractions();
        app()->call([$first, 'handle']);
        app()->call([$second, 'handle']);

        return [$first, $second];
    });

    $first->assertNotReleased();
    $second->assertReleased();
    Notification::assertSentOnDemandTimes(MessageEmailNotification::class, 1);

    $this->travel(61)->seconds();
    $retried = $this->store->run(static function () use ($second): SendMessage {
        $retried = (new SendMessage($second->message, $second->addressee))->withFakeQueueInteractions();
        app()->call([$retried, 'handle']);

        return $retried;
    });

    $retried->assertNotReleased();
    Notification::assertSentOnDemand(MessageEmailNotification::class, static fn (MessageEmailNotification $notification): bool => $notification->message() instanceof FakeMessage && $notification->message()->reference === 'second');
});

it('counts each store\'s messages apart, so one store\'s burst never holds up another\'s', function (): void {
    // Redis, which every store shares, as in production: the test cache starts empty in each store.
    useRedisCache();
    config(['messaging.sending.per_minute' => 1]);
    $other = createStore('other-messaging-store');
    $recipient = new FakeAddressee(new MessageRecipient('en', 'ada@example.com'));

    $this->store->run(static fn () => app()->call([new SendMessage(new FakeMessage, $recipient), 'handle']));
    $job = $other->run(static function () use ($recipient): SendMessage {
        $job = (new SendMessage(new FakeMessage, $recipient))->withFakeQueueInteractions();
        app()->call([$job, 'handle']);

        return $job;
    });

    $job->assertNotReleased();
    Notification::assertSentOnDemandTimes(MessageEmailNotification::class, 2);
});

it('waits on the messages queue, encrypted, and only once the change it is about has committed', function (): void {
    Queue::fake();

    $this->store->run(static fn () => SendMessage::dispatch(new FakeMessage, new FakeAddressee(new MessageRecipient('en', 'ada@example.com'))));

    Queue::assertPushedOn('messages', SendMessage::class, static fn (SendMessage $job): bool => $job->afterCommit === true && $job->retryUntil() > now()->addHours(23));
});

it('refuses a second channel with a key already taken, so a clash stops the app at boot', function (): void {
    $channels = app(MessageChannels::class);

    expect(static fn () => $channels->add(EmailChannel::class, 'email_again'))->toThrow(LogicException::class, 'both use the key "email"');
});
