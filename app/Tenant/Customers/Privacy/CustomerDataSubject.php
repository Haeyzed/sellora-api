<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Privacy;

use App\Shared\Privacy\DataSubject;
use App\Tenant\Customers\Models\Customer;

/**
 * Customers as data-protection subjects: the "customer" type, identified by public ID, trashed or not.
 */
final class CustomerDataSubject
{
    public const string TYPE = 'customer';

    public static function of(Customer $customer): DataSubject
    {
        return new DataSubject(self::TYPE, $customer->public_id);
    }

    /**
     * The customer a request is about, locked for the erasure's transaction when asked; null when there is none.
     */
    public static function customer(DataSubject $subject, bool $lock = false): ?Customer
    {
        $query = Customer::withTrashed()->where('public_id', $subject->publicId);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }
}
