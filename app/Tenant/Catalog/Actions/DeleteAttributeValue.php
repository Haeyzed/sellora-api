<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Exceptions\AttributeValueInUseException;
use App\Tenant\Catalog\Models\AttributeValue;
use Illuminate\Database\QueryException;

/**
 * Deletes an attribute value for good, only while no variant, even one in the trash, has it. The database refuses otherwise.
 */
final readonly class DeleteAttributeValue
{
    /** PostgreSQL's errors for a delete a foreign key forbids: restrict_violation and foreign_key_violation. */
    private const array STILL_REFERENCED = ['23001', '23503'];

    /**
     * @throws AttributeValueInUseException When a variant still has it.
     */
    public function handle(AttributeValue $value): void
    {
        try {
            $value->getConnection()->transaction(static fn (): ?bool => $value->delete());
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), self::STILL_REFERENCED, true)) {
                throw new AttributeValueInUseException(previous: $exception);
            }

            throw $exception;
        }
    }
}
