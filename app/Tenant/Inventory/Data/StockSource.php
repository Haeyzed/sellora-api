<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Data;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * What a stock movement belongs to, such as a reservation, an order or a return, by morph name and public ID.
 */
final readonly class StockSource
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
            throw new LogicException('Only records with a public ID can be the source of a stock movement.');
        }

        return new self($record->getMorphClass(), $publicId);
    }
}
