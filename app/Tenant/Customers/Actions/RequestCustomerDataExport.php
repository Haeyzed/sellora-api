<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Enums\CustomerDataExportStatus;
use App\Tenant\Customers\Exceptions\CustomerEmailNotVerifiedException;
use App\Tenant\Customers\Jobs\BuildCustomerDataExport;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerDataExport;
use Illuminate\Support\Facades\DB;

/**
 * Starts building a customer's export of their personal data in the background. Only a customer with a verified email may, and only one is built at a time.
 */
final readonly class RequestCustomerDataExport
{
    /**
     * @throws CustomerEmailNotVerifiedException
     */
    public function handle(Customer $customer): CustomerDataExport
    {
        if (! $customer->hasVerifiedEmail()) {
            throw new CustomerEmailNotVerifiedException;
        }

        return DB::transaction(static function () use ($customer): CustomerDataExport {
            Customer::query()->lockForUpdate()->findOrFail($customer->id);

            $pending = $customer->dataExports()->where('status', CustomerDataExportStatus::Pending->value)->first();

            if ($pending !== null) {
                return $pending;
            }

            $export = $customer->dataExports()->make();
            $export->forceFill([
                'status' => CustomerDataExportStatus::Pending,
                'disk' => config()->string('customers.data_exports.disk'),
                'created_at' => now(),
            ])->save();

            BuildCustomerDataExport::dispatch($export->id)->afterCommit();

            return $export;
        });
    }
}
