<?php

declare(strict_types=1);

namespace App\Tenant\Messaging;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * Which columns of Messaging's tables a store export contains.
 */
final class MessagingStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('messaging_settings', include: [
            'id', 'sender_name', 'reply_to_email', 'has_new_order_alert_emails', 'created_at', 'updated_at',
        ]);
    }
}
