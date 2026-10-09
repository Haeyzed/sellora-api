<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Exceptions\AttributeInUseException;
use App\Tenant\Catalog\Models\Attribute;
use Illuminate\Database\QueryException;

/**
 * Deletes an attribute and its values for good, only while no product varies by it and no variant, even one in the trash, has one of its values.
 *
 * The database refuses the delete otherwise (foreign keys), so a variant
 * added at the same moment can never lose its value.
 */
final readonly class DeleteAttribute
{
    /** PostgreSQL's errors for a delete a foreign key forbids: restrict_violation and foreign_key_violation. */
    private const array STILL_REFERENCED = ['23001', '23503'];

    /**
     * @throws AttributeInUseException When a product or variant still uses it.
     */
    public function handle(Attribute $attribute): void
    {
        try {
            $attribute->getConnection()->transaction(static fn (): ?bool => $attribute->delete());
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), self::STILL_REFERENCED, true)) {
                throw new AttributeInUseException(previous: $exception);
            }

            throw $exception;
        }
    }
}
