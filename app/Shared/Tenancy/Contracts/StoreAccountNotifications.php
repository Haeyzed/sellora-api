<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

use App\Shared\Auth\AccountReference;
use Illuminate\Notifications\Notification;

/**
 * Sends a notification to an account in the current store's database, known to the platform only as account type plus public ID.
 *
 * Implemented by the store zone, so platform code that must tell a store's
 * account something (for example that the export they asked for is ready)
 * never reads store tables itself (section 7.2).
 */
interface StoreAccountNotifications
{
    /**
     * Sends the notification if the account exists in the current store and is active.
     *
     * @return bool Whether it was sent.
     */
    public function send(AccountReference $account, Notification $notification): bool;
}
