<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Privacy;

use App\Shared\Privacy\DataSubject;
use App\Tenant\Delivery\Models\Driver;

/**
 * Drivers as data-protection subjects: the "driver" type, identified by public ID.
 */
final class DriverDataSubject
{
    public const string TYPE = 'driver';

    public static function of(Driver $driver): DataSubject
    {
        return new DataSubject(self::TYPE, $driver->public_id);
    }

    /**
     * The driver a request is about, locked for the erasure's transaction when asked; null when there is none.
     */
    public static function driver(DataSubject $subject, bool $lock = false): ?Driver
    {
        $query = Driver::query()->where('public_id', $subject->publicId);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }
}
