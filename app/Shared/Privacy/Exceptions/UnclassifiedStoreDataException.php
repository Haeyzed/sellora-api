<?php

declare(strict_types=1);

namespace App\Shared\Privacy\Exceptions;

use RuntimeException;

/**
 * Raised when a store table or column isn't classified for exports, so the export stops instead of guessing.
 *
 * A programming mistake: the domain that added the column must classify it.
 */
final class UnclassifiedStoreDataException extends RuntimeException
{
    /**
     * @param  list<string>  $unclassified  "table.column", or "table.*" for a whole table.
     */
    public function __construct(public readonly array $unclassified)
    {
        parent::__construct('Store data not classified for exports: '.implode(', ', $unclassified).'.');
    }
}
