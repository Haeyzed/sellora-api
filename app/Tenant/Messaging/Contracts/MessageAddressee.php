<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Contracts;

use App\Tenant\Messaging\Data\MessageRecipient;

/**
 * Who a message is for, kept as an ID while it waits in the queue and looked up only when it is sent.
 *
 * Looking them up late means a message goes to their address and language as
 * they are when it is sent, and to nobody once they can no longer receive it
 * (a staff member deactivated in the meantime).
 */
interface MessageAddressee
{
    /**
     * Where and in which language to reach them now; null when the message should no longer go to them.
     */
    public function recipient(): ?MessageRecipient;
}
