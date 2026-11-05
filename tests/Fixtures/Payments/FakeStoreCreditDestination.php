<?php

declare(strict_types=1);

namespace Tests\Fixtures\Payments;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Contracts\RefundDestination;
use App\Tenant\Payments\Data\DestinationRefund;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A module's refund destination for the tests, as store credit would be: offered to the customers it is told to offer, paying each refund out once however often it is asked, and recording every time it was asked.
 */
final class FakeStoreCreditDestination implements RefundDestination
{
    public const string KEY = 'store_credit';

    public const string FEATURE = 'wallet';

    /** Whether its own rules offer it to a customer. */
    public static bool $isOffered = true;

    /** What paying out throws, as when the wallet service refuses or can't be reached; null when it works. */
    public static ?Throwable $failure = null;

    /** Run while it is paying out, before it pays: to act as a second caller arriving at the same moment. */
    public static ?Closure $whilePaying = null;

    /** @var list<array{refund: string, order: string, customer: string, amount: int, currency: string, is_test: bool, transaction_level: int}> Every time it was asked to pay out. */
    public static array $asked = [];

    /** @var array<string, string> What it paid out, its reference by refund. */
    public static array $paid = [];

    public static function reset(): void
    {
        self::$isOffered = true;
        self::$failure = null;
        self::$whilePaying = null;
        self::$asked = [];
        self::$paid = [];
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return app()->getLocale() === 'fr' ? 'Avoir en boutique' : 'Store credit';
    }

    public function isOfferedFor(Order $order, Customer $customer): bool
    {
        return self::$isOffered;
    }

    public function giveBack(DestinationRefund $refund): string
    {
        self::$asked[] = [
            'refund' => $refund->refundId,
            'order' => $refund->orderId,
            'customer' => $refund->customerId,
            'amount' => $refund->amount->minorAmount(),
            'currency' => $refund->amount->currency(),
            'is_test' => $refund->isTest,
            'transaction_level' => DB::transactionLevel(),
        ];

        if (self::$whilePaying !== null) {
            $whilePaying = self::$whilePaying;
            self::$whilePaying = null;
            $whilePaying();
        }

        if (self::$failure !== null) {
            throw self::$failure;
        }

        return self::$paid[$refund->refundId] ??= 'sc_'.$refund->refundId;
    }
}
