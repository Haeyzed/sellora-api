<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A purged store's subdomain, held back from new stores for a while (365 days, from config), so nobody can take over the old store's links and traffic.
 *
 * @property int $id
 * @property string $subdomain
 * @property CarbonImmutable $released_at
 * @property CarbonImmutable $held_until
 */
final class ReleasedSubdomain extends Model
{
    use CentralConnection;

    public $timestamps = false;

    protected $fillable = [
        'subdomain',
        'released_at',
        'held_until',
    ];

    /**
     * Subdomains still held back.
     *
     * @param  Builder<self>  $query
     */
    protected function scopeHeld(Builder $query): void
    {
        $query->where('held_until', '>', CarbonImmutable::now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'released_at' => 'immutable_datetime',
            'held_until' => 'immutable_datetime',
        ];
    }
}
