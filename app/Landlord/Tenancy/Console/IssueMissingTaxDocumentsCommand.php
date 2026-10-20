<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Contracts\StoreTaxDocuments;
use Illuminate\Console\Command;
use Throwable;

/**
 * Issues every store's tax documents that fell due but weren't issued, such as an invoice whose queued job failed.
 *
 * Runs hourly, behind the queue that issues each document as it falls due.
 * Stores without a database are skipped; one store failing never stops the
 * others.
 */
final class IssueMissingTaxDocumentsCommand extends Command
{
    protected $signature = 'orders:issue-missing-tax-documents';

    protected $description = 'Issue invoices and credit notes that fell due but were not issued, in every store';

    public function handle(StoreTaxDocuments $storeTaxDocuments): int
    {
        $failures = 0;

        foreach (Tenant::query()->whereNotIn('status', TenantStatus::withoutDatabase())->lazyById(100) as $tenant) {
            try {
                $issued = $tenant->run(static fn (): int => $storeTaxDocuments->issueMissing());
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error("Store {$tenant->id}: {$exception->getMessage()}");
                $failures++;

                continue;
            }

            if ($issued > 0) {
                $this->components->twoColumnDetail("Store {$tenant->id}", "{$issued} missing tax documents issued");
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
