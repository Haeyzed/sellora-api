<?php

declare(strict_types=1);

namespace App\Shared\Integrations;

/**
 * What became of a webhook a store was handed.
 */
enum WebhookReceipt: string
{
    /** Its signature checked out; it is handled, or was already. */
    case Accepted = 'accepted';
    /** Not signed by the provider for this store's account: nothing in it was read or kept. */
    case SignatureInvalid = 'signature_invalid';
    /** The endpoint leads to no connection in the store, such as one left behind by a connection that never finished saving. */
    case UnknownEndpoint = 'unknown_endpoint';
    /** The store has had more webhooks this minute than its limit: nothing in it was read, and the provider sends it again later. */
    case Throttled = 'throttled';
}
