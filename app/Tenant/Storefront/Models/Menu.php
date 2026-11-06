<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Models;

use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the store's storefront menus, such as its main menu or its footer, which the storefront asks for by its key.
 *
 * @property int $id
 * @property string $public_id
 * @property string $key Such as "main" or "footer".
 * @property string $name For staff; shoppers never see it.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, MenuItem> $items
 */
final class Menu extends Model
{
    /** The most links a menu holds, at both levels together. */
    public const int MAX_ITEMS = 100;

    /** Lower-case letters, numbers and single hyphens, such as "main" or "footer-help". */
    public const string KEY_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    use HasPublicId;

    protected $fillable = [];

    /**
     * Its top-level links in order, each with its own links under it.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->whereNull('parent_id')->orderBy('position');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
