<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Privacy;

use App\Shared\Privacy\DataSubject;
use App\Tenant\Orders\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * Guests as data-protection subjects: the "guest" type, identified by the email (in lower case) their orders were placed with, since a guest has no account.
 */
final class GuestDataSubject
{
    public const string TYPE = 'guest';

    public static function of(string $email): DataSubject
    {
        return new DataSubject(self::TYPE, mb_strtolower(trim($email)));
    }

    /**
     * The guest's orders: placed with their email and not part of any account.
     *
     * @return Builder<Order>
     */
    public static function orders(DataSubject $subject): Builder
    {
        return Order::query()->whereNull('customer_id')->where('contact_email', $subject->publicId);
    }
}
