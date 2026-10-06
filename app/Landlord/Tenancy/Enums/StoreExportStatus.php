<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Enums;

/**
 * Where a store export is: waiting, being built, ready to download, or failed.
 */
enum StoreExportStatus: string
{
    /** Waiting for its turn on the bulk queue. */
    case Queued = 'queued';

    /** Being written. */
    case Building = 'building';

    /** Ready to download until it expires. */
    case Ready = 'ready';

    /** Building it failed; request a new one. */
    case Failed = 'failed';

    /** Past its 7 days; the file is deleted. Never stored, only shown. */
    case Expired = 'expired';

    /**
     * @return list<self>
     */
    public static function inProgress(): array
    {
        return [self::Queued, self::Building];
    }
}
