<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Tenant\Orders\Enums\TaxDocumentKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The last number given in one series of invoices or credit notes: a year, or "all" when the store numbers continuously. It only ever moves on by one, and is never deleted (the database refuses it).
 *
 * @property int $id
 * @property TaxDocumentKind $kind
 * @property string $series A year, such as "2026", or "all".
 * @property int $last_number 0 before the first document.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class DocumentSequence extends Model
{
    /** The series of a store that numbers continuously. */
    public const string CONTINUOUS_SERIES = 'all';

    /** Times are saved with their offset, so a time in any timezone means the same moment. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TaxDocumentKind::class,
            'last_number' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
