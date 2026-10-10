<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Models;

use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A place stock is kept, such as "Main location".
 *
 * Every store has exactly one default location, always active, created with
 * the store. Core never adds another; the MultiLocation module does.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property bool $is_default
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class StockLocation extends Model
{
    use HasPublicId;

    protected $fillable = [
        'name',
    ];

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
