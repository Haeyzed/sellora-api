<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Data;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * What stock is reserved for, such as a checkout or an order, by morph name and public ID.
 */
final readonly class StockHolder
{
    public function __construct(
        public string $type,
        public string $id,
    ) {}

    /**
     * @throws LogicException When the record has no public ID, which is a programming mistake.
     */
    public static function of(Model $record): self
    {
        $publicId = $record->getAttribute('public_id');

        if (! is_string($publicId)) {
            throw new LogicException('Only records with a public ID can hold stock.');
        }

        return new self($record->getMorphClass(), $publicId);
    }
}
