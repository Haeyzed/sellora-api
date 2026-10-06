<?php

declare(strict_types=1);

namespace App\Shared\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Gives a model a random public identifier (a ULID in "public_id") used in URLs and API responses instead of its database ID.
 *
 * Sequential IDs let anyone guess or count records ("order 1042, so 1041
 * exists"). The numeric ID stays the primary key for joins; only public_id
 * leaves the API, and route model binding looks records up by it.
 */
trait HasPublicId
{
    use HasUlids;

    /**
     * The columns that get a new ULID when the model is created: only public_id, never the primary key.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * Route model binding finds records by their public ID.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
