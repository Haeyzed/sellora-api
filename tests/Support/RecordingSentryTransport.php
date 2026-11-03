<?php

declare(strict_types=1);

namespace Tests\Support;

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * Stands in for Sentry's network transport in tests: keeps every event the application would send, exactly as it would leave, and sends nothing.
 */
final class RecordingSentryTransport implements TransportInterface
{
    /**
     * @var list<Event>
     */
    public array $events = [];

    /**
     * Makes the application send to Sentry as it would with a DSN configured, through this transport, using the app's own Sentry settings (before_send included).
     */
    public static function install(): self
    {
        config(['sentry.dsn' => 'https://public@sentry.example.test/1']);
        $transport = new self;
        $hub = new Hub(app(ClientBuilder::class)->setTransport($transport)->getClient());

        app()->instance(HubInterface::class, $hub);
        SentrySdk::setCurrentHub($hub);

        return $transport;
    }

    /**
     * Puts back a Sentry with no client, so nothing installed by a test outlives it.
     */
    public static function uninstall(): void
    {
        SentrySdk::setCurrentHub(new Hub);
    }

    public function send(Event $event): Result
    {
        $this->events[] = $event;

        return new Result(ResultStatus::success(), $event);
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}
