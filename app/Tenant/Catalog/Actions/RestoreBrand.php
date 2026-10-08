<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Exceptions\BrandRestoreConflictException;
use App\Tenant\Catalog\Models\Brand;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Brings a brand back from the trash. Restoring a brand that isn't in the trash changes nothing.
 */
final readonly class RestoreBrand
{
    /**
     * @throws BrandRestoreConflictException When another brand now uses its slug.
     */
    public function handle(Brand $brand): Brand
    {
        if (! $brand->trashed()) {
            return $brand;
        }

        try {
            $brand->restore();
        } catch (UniqueConstraintViolationException $exception) {
            throw new BrandRestoreConflictException(previous: $exception);
        }

        return $brand;
    }
}
