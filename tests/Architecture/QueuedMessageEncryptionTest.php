<?php

declare(strict_types=1);

use App\Shared\Auth\PasswordResetLinkNotification;
use Tests\Support\QueuedMessages\Listeners\EncryptedQueuedListener;
use Tests\Support\QueuedMessages\Listeners\UnencryptedQueuedListener;
use Tests\Support\QueuedMessages\UnencryptedQueuedMailable;
use Tests\Support\QueuedMessages\UnencryptedQueuedNotification;

/*
 * A queued notification, mailable or listener carries what it sends or reacts
 * to (links with tokens, codes, emails, names) into the queue, and into
 * failed_jobs when it fails. Encrypted, the stored job holds none of it in
 * readable form, so every one implements ShouldBeEncrypted.
 */

it('encrypts every queued notification, mailable and listener', function (): void {
    expect(unencryptedQueuedMessages(productionClasses()))->toBe([]);
});

it('looks at the queued notifications of the production code', function (): void {
    expect(productionClasses())->toContain(PasswordResetLinkNotification::class);
});

it('finds a queued notification, mailable or listener that is not encrypted, and accepts one that is', function (): void {
    expect(unencryptedQueuedMessages([UnencryptedQueuedNotification::class, UnencryptedQueuedMailable::class, UnencryptedQueuedListener::class, EncryptedQueuedListener::class]))
        ->toBe([UnencryptedQueuedNotification::class, UnencryptedQueuedMailable::class, UnencryptedQueuedListener::class]);
});
