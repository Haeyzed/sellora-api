<?php

declare(strict_types=1);

namespace App\Tenant\Customers;

use App\Shared\Retention\Contracts\RetentionPolicy;
use App\Shared\Retention\RetentionScope;
use App\Tenant\Customers\Actions\EraseCustomer;
use App\Tenant\Customers\Models\Customer;
use Carbon\CarbonImmutable;

/**
 * Erases customers who have been in the trash for the whole retention period (90 days by default), in every store's daily purge (section 3.3).
 *
 * Only trashed customers: long-inactive accounts are never erased
 * automatically. Each customer is erased all or nothing, one at a time.
 */
final readonly class TrashedCustomerRetention implements RetentionPolicy
{
    public function __construct(private EraseCustomer $eraseCustomer) {}

    public function periodKey(): string
    {
        return 'trashed_customers';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    public function purgeOlderThan(CarbonImmutable $cutoff): int
    {
        $erased = 0;

        foreach (Customer::onlyTrashed()->whereNull('erased_at')->where('deleted_at', '<', $cutoff)->lazyById(100) as $customer) {
            $this->eraseCustomer->erase($customer);
            $erased++;
        }

        return $erased;
    }
}
