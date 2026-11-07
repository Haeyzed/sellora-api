<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Enums;

/**
 * What a platform alert is about: something on the platform side that Sellora's team must look at.
 */
enum PlatformAlertKind: string
{
    /** No exchange rate fetch has succeeded for 12 hours; every store's extra currencies are heading towards being withdrawn. */
    case ExchangeRatesOverdue = 'exchange_rates_overdue';

    /** Setting up a new store failed after every retry. */
    case StoreSetupFailed = 'store_setup_failed';

    /** The scheduled check found a store's stock levels not matching its stock movement ledger. */
    case StockLevelsMismatch = 'stock_levels_mismatch';

    /** The scheduled check found a store's invoices not matching the sale money kept after credit notes. */
    case InvoiceBalancesMismatch = 'invoice_balances_mismatch';

    /**
     * The permission whose holders are alerted, besides super admins: the one that lets them act on it (retry a store's setup, look into a store).
     */
    public function permission(): PlatformPermission
    {
        return PlatformPermission::StoresManage;
    }

    /**
     * Whether it may come again day after day (a check that keeps failing), and so is sent at most once a day; the others are sent each time.
     */
    public function repeatsDaily(): bool
    {
        return $this !== self::StoreSetupFailed;
    }
}
