<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Enums;

/**
 * Where a customer's export of their personal data is.
 */
enum CustomerDataExportStatus: string
{
    /** Being built in the background. */
    case Pending = 'pending';

    /** Ready to download until it expires. */
    case Ready = 'ready';

    /** Building it failed; the customer can ask again. */
    case Failed = 'failed';
}
