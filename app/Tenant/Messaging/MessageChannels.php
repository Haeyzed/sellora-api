<?php

declare(strict_types=1);

namespace App\Tenant\Messaging;

use App\Shared\Features\Features;
use App\Tenant\Messaging\Contracts\MessageChannel;
use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * Every way a store can reach people: core's email first, then each channel an integration registers from its service provider under its feature key (SMS, WhatsApp, push).
 *
 * An integration's channel is used only while its integration is Enabled for
 * the store; Locked, Suspended, Disabled and Unavailable all leave it out.
 */
final class MessageChannels
{
    /** What a channel's key looks like. */
    public const string KEY_PATTERN = '/^[a-z][a-z0-9_]{0,31}$/';

    /** @var array<string, array{class-string<MessageChannel>, string|null}> Each channel's class and feature key (null for core's own), by channel key. */
    private array $channels = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Registers a channel. Core registers email with no feature key; an integration passes its own, so a clashing or malformed key stops the app at boot.
     *
     * @param  class-string<MessageChannel>  $channel
     *
     * @throws LogicException When its key is malformed or another channel's.
     */
    public function add(string $channel, ?string $featureKey): void
    {
        $key = $this->make($channel)->key();

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new LogicException("The message channel {$channel} has the key \"{$key}\": keys are lowercase letters, digits and underscores.");
        }

        if (isset($this->channels[$key])) {
            throw new LogicException("The message channels {$this->channels[$key][0]} and {$channel} both use the key \"{$key}\": each key must belong to one channel only.");
        }

        $this->channels[$key] = [$channel, $featureKey];
    }

    /**
     * The channels this store may send through now, in the order they were registered: core's always, an integration's only while it is Enabled.
     *
     * @return list<MessageChannel>
     */
    public function usable(): array
    {
        $usable = [];

        foreach ($this->channels as [$class, $featureKey]) {
            if ($featureKey === null || $this->container->make(Features::class)->isEnabled($featureKey)) {
                $usable[] = $this->make($class);
            }
        }

        return $usable;
    }

    /**
     * @param  class-string<MessageChannel>  $class
     */
    private function make(string $class): MessageChannel
    {
        return $this->container->make($class);
    }
}
